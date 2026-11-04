<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Controller;

use Greenfieldr\Golb\Controller\BaseController;
use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEvents\Domain\Repository\EventRepository;
use Greenfieldr\GolbEvents\Domain\Repository\OccurrenceRepository;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingRequest;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\OccurrenceAvailability;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingRepository;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingSettingsRepository;
use Greenfieldr\GolbEventsBooking\Exception\BookingException;
use Greenfieldr\GolbEventsBooking\Service\AvailabilityService;
use Greenfieldr\GolbEventsBooking\Service\BookingStateService;
use Greenfieldr\GolbEventsBooking\Service\TokenService;
use Greenfieldr\GolbEventsBooking\Validation\Validator\BookingRequestValidator;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Attribute\IgnoreValidation;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extbase\Validation\Validator\ConjunctionValidator;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * The "Booking form" plugin: shows the dates of an event with their free
 * seats, takes the booking, and handles the confirmation and cancellation
 * links of the mails. The event is the one selected in the plugin or the
 * current page.
 */
class BookingController extends BaseController
{
    public function __construct(
        protected readonly EventRepository $eventRepository,
        protected readonly OccurrenceRepository $occurrenceRepository,
        protected readonly BookingRepository $bookingRepository,
        protected readonly BookingSettingsRepository $settingsRepository,
        protected readonly AvailabilityService $availabilityService,
        protected readonly BookingStateService $bookingStateService,
        protected readonly TokenService $tokenService,
        protected readonly BookingRequestValidator $bookingRequestValidator,
        protected readonly Context $context,
    ) {}

    /**
     * Shows the booking form with the upcoming dates of the event. After a
     * failed validation the entered data comes back as argument.
     */
    public function formAction(#[IgnoreValidation] ?BookingRequest $bookingRequest = null): ResponseInterface
    {
        $event = $this->resolveEvent();
        $settings = $this->settingsRepository->findByEventUid((int)$event?->getUid());
        $this->view->assign('event', $event);
        $this->view->assign('settings', $this->settings + ['booking' => $settings]);

        if (!$event instanceof Event || !$settings->enabled) {
            return $this->htmlResponse($this->view->render('Unavailable'));
        }

        $availabilities = $this->availabilities($event, $settings);
        $bookable = array_filter($availabilities, static fn(OccurrenceAvailability $availability): bool => $availability->isBookable());
        if (!$bookingRequest instanceof BookingRequest) {
            $bookingRequest = $this->prefilledRequest();
            $first = reset($bookable);
            if ($first instanceof OccurrenceAvailability) {
                $bookingRequest->setOccurrence((int)$first->occurrence->getUid());
            }
        }
        $bookingRequest->setStartedAt($this->now()->getTimestamp());

        $this->view->assign('bookingRequest', $bookingRequest);
        $this->view->assign('salutationOptions', $this->salutationOptions());
        $this->view->assign('availabilities', $availabilities);
        $this->view->assign('bookable', $bookable !== []);
        $this->view->assign('frontendUser', $this->frontendUserRow());

        return $this->htmlResponse();
    }

    public function initializeFormAction(): void
    {
        parent::initializeAction();
        $this->allowBookingRequestMapping();
    }

    public function initializeCreateAction(): void
    {
        parent::initializeAction();
        $this->allowBookingRequestMapping();
        $this->bookingRequestValidator->setRequest($this->request);
        $this->bookingRequestValidator->setMinimumFillTime((int)($this->settings['minimumFillTime'] ?? 0));
        $validator = $this->arguments->getArgument('bookingRequest')->getValidator();
        if ($validator instanceof ConjunctionValidator) {
            $validator->addValidator($this->bookingRequestValidator);
        }
    }

    /**
     * The request object is the list of accepted form fields, so every
     * property of it may be mapped from the request.
     */
    protected function allowBookingRequestMapping(): void
    {
        if ($this->arguments->hasArgument('bookingRequest')) {
            $this->arguments->getArgument('bookingRequest')->getPropertyMappingConfiguration()->allowAllProperties();
        }
    }

    /**
     * Shows the form again with the entered data and the validation errors.
     * Extbase would forward to the referring request, which needs the signed
     * referrer fields of the default form; this works with any template.
     */
    protected function errorAction(): ResponseInterface
    {
        return (new ForwardResponse('form'))
            ->withControllerName('Booking')
            ->withExtensionName('GolbEventsBooking')
            ->withArguments($this->request->getArguments())
            ->withArgumentsValidationResult($this->arguments->validate());
    }

    /**
     * Stores the booking and redirects to the success page.
     */
    public function createAction(BookingRequest $bookingRequest): ResponseInterface
    {
        $occurrence = $this->occurrenceRepository->findByUid($bookingRequest->getOccurrence());
        $event = $occurrence instanceof Occurrence ? $occurrence->getEvent() : null;
        if (!$occurrence instanceof Occurrence || !$event instanceof Event) {
            $this->addFlashMessage($this->translate('error.occurrence'), '', ContextualFeedbackSeverity::ERROR);

            return $this->redirect('form');
        }
        $settings = $this->settingsRepository->findByEventUid((int)$event->getUid());

        $booking = new Booking();
        $bookingRequest->applyTo($booking, $this->now());
        $booking->setFrontendUser($this->frontendUserUid());

        try {
            $this->bookingStateService->book($booking, $occurrence, $settings);
        } catch (BookingException $exception) {
            $this->addFlashMessage($this->translate('error.' . $exception->getMessageKey()), '', ContextualFeedbackSeverity::ERROR);

            return $this->redirect('form');
        }

        return $this->redirect('success', null, null, ['token' => $booking->getToken()]);
    }

    /**
     * Tells the participant what happened: confirmed, waiting or pending.
     */
    public function successAction(string $token = ''): ResponseInterface
    {
        $booking = $this->bookingByToken($token);
        if (!$booking instanceof Booking) {
            return $this->htmlResponse($this->view->render('NotFound'));
        }
        $this->assignBooking($booking);

        return $this->htmlResponse();
    }

    /**
     * Target of the confirmation link of the opt-in mail.
     */
    public function confirmAction(string $token = ''): ResponseInterface
    {
        $booking = $this->bookingByToken($token);
        if (!$booking instanceof Booking) {
            return $this->htmlResponse($this->view->render('NotFound'));
        }
        $settings = $this->settingsOf($booking);
        $outcome = 'already';
        if ($booking->isPending()) {
            try {
                $this->bookingStateService->confirm($booking, $settings);
                $outcome = 'confirmed';
            } catch (BookingException) {
                $outcome = 'failed';
            }
        } elseif ($booking->isCancelled()) {
            $outcome = 'cancelled';
        }
        $this->assignBooking($booking);
        $this->view->assign('outcome', $outcome);

        return $this->htmlResponse();
    }

    /**
     * Target of the cancellation link: asks for confirmation, then cancels.
     */
    public function cancelAction(string $token = '', bool $confirmed = false): ResponseInterface
    {
        $booking = $this->bookingByToken($token);
        if (!$booking instanceof Booking) {
            return $this->htmlResponse($this->view->render('NotFound'));
        }
        $outcome = $booking->isActive() ? 'ask' : 'already';
        if ($booking->isActive() && $confirmed && $this->request->getMethod() === 'POST') {
            try {
                $this->bookingStateService->cancel($booking, $this->settingsOf($booking));
                $outcome = 'cancelled';
            } catch (BookingException) {
                $outcome = 'failed';
            }
        }
        $this->assignBooking($booking);
        $this->view->assign('outcome', $outcome);

        return $this->htmlResponse();
    }

    /**
     * The event of the plugin: the page selected in the plugin settings or
     * the current page, if it is an event.
     */
    protected function resolveEvent(): ?Event
    {
        $uid = (int)($this->settings['eventUid'] ?? 0);
        if ($uid <= 0) {
            $pageInformation = $this->request->getAttribute('frontend.page.information');
            $uid = $pageInformation instanceof PageInformation ? $pageInformation->getId() : 0;
        }
        $event = $uid > 0 ? $this->eventRepository->findByUid($uid) : null;

        return $event instanceof Event ? $event : null;
    }

    /**
     * The upcoming dates of the event with their booking situation.
     *
     * @return OccurrenceAvailability[]
     */
    protected function availabilities(Event $event, BookingSettings $settings): array
    {
        $now = $this->now();
        $limit = max(1, (int)($this->settings['occurrenceLimit'] ?? 12));
        $availabilities = [];
        foreach ($this->occurrenceRepository->findUpcomingOfEvent($event, $now, $limit) as $occurrence) {
            $availabilities[] = $this->availabilityService->availability($settings, $occurrence, $now);
        }

        return $availabilities;
    }

    /**
     * The options of the salutation select: an empty choice and the
     * translated salutations.
     *
     * @return array<string, string>
     */
    protected function salutationOptions(): array
    {
        $options = ['' => ''];
        foreach ([Booking::SALUTATION_MR, Booking::SALUTATION_MS, Booking::SALUTATION_MX] as $salutation) {
            $options[$salutation] = $this->translate('salutation.' . $salutation);
        }

        return $options;
    }

    /**
     * A new request prefilled with the data of the logged in frontend user.
     */
    protected function prefilledRequest(): BookingRequest
    {
        $bookingRequest = new BookingRequest();
        $user = $this->frontendUserRow();
        if ($user === []) {
            return $bookingRequest;
        }
        $string = static fn(string $field): string => is_string($user[$field] ?? null) ? $user[$field] : '';
        $bookingRequest->setFirstName($string('first_name'));
        $bookingRequest->setLastName($string('last_name'));
        if ($bookingRequest->getFirstName() === '' && $bookingRequest->getLastName() === '' && $string('name') !== '') {
            $parts = explode(' ', $string('name'), 2);
            $bookingRequest->setFirstName($parts[0]);
            $bookingRequest->setLastName($parts[1] ?? '');
        }
        $bookingRequest->setEmail($string('email'));
        $bookingRequest->setPhone($string('telephone'));
        $bookingRequest->setCompany($string('company'));

        return $bookingRequest;
    }

    /**
     * The uid of the logged in frontend user, 0 for guests.
     */
    protected function frontendUserUid(): int
    {
        $uid = $this->frontendUserRow()['uid'] ?? 0;

        return is_numeric($uid) ? (int)$uid : 0;
    }

    /**
     * The record of the logged in frontend user, [] for guests.
     *
     * @return array<string, mixed>
     */
    protected function frontendUserRow(): array
    {
        $frontendUser = $this->request->getAttribute('frontend.user');
        if (!$frontendUser instanceof FrontendUserAuthentication || !is_array($frontendUser->user) || (int)($frontendUser->user['uid'] ?? 0) <= 0) {
            return [];
        }

        /** @var array<string, mixed> $row */
        $row = $frontendUser->user;

        return $row;
    }

    protected function bookingByToken(string $token): ?Booking
    {
        $booking = $this->bookingRepository->findOneByToken($token);

        return $booking instanceof Booking && $this->tokenService->matches($booking, $token) ? $booking : null;
    }

    protected function settingsOf(Booking $booking): BookingSettings
    {
        return $this->settingsRepository->findByEventUid((int)$booking->getEvent()?->getUid());
    }

    protected function assignBooking(Booking $booking): void
    {
        $this->view->assign('booking', $booking);
        $this->view->assign('event', $booking->getEvent());
        $this->view->assign('occurrence', $booking->getOccurrence());
        $this->view->assign('settings', $this->settings + ['booking' => $this->settingsOf($booking)]);
    }

    protected function translate(string $key): string
    {
        return LocalizationUtility::translate($key, 'GolbEventsBooking') ?? $key;
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->context->getPropertyFromAspect('date', 'full');

        return $now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable();
    }
}
