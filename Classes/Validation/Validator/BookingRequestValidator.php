<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Validation\Validator;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEvents\Domain\Repository\OccurrenceRepository;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingRequest;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingSettingsRepository;
use Greenfieldr\GolbEventsBooking\Event\ValidateBookingEvent;
use Greenfieldr\GolbEventsBooking\Service\AvailabilityService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * The rules of the booking form that depend on the event: the date must
 * exist, be bookable and have room (or a waiting list), the seats must not
 * exceed the allowed number, the privacy policy must be accepted, the
 * honeypot must be empty and the form must not be submitted faster than a
 * human can fill it. Finally other extensions add their rules through the
 * ValidateBookingEvent.
 */
final class BookingRequestValidator extends AbstractValidator
{
    private int $minimumFillTime = 0;

    public function __construct(
        private readonly OccurrenceRepository $occurrenceRepository,
        private readonly BookingSettingsRepository $settingsRepository,
        private readonly AvailabilityService $availabilityService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Context $context,
    ) {}

    /**
     * The minimum fill time in seconds (0 disables the check). The request
     * for the ValidateBookingEvent comes from setRequest().
     */
    public function setMinimumFillTime(int $minimumFillTime): void
    {
        $this->minimumFillTime = max(0, $minimumFillTime);
    }

    protected function isValid(mixed $value): void
    {
        if (!$value instanceof BookingRequest) {
            $this->addError($this->translate('validator.invalidRequest'), 1762300200);

            return;
        }

        if ($value->getWebsite() !== '') {
            $this->addError($this->translate('validator.spam'), 1762300201);

            return;
        }
        $now = $this->now();
        if ($this->minimumFillTime > 0 && $value->getStartedAt() > $now->getTimestamp() - $this->minimumFillTime) {
            $this->addError($this->translate('validator.spam'), 1762300202);

            return;
        }

        if (!$value->isPrivacy()) {
            $this->addErrorForProperty('privacy', $this->translate('validator.privacy'), 1762300203);
        }

        $occurrence = $value->getOccurrence() > 0 ? $this->occurrenceRepository->findByUid($value->getOccurrence()) : null;
        if (!$occurrence instanceof Occurrence || !$occurrence->getEvent() instanceof Event) {
            $this->addErrorForProperty('occurrence', $this->translate('validator.occurrence'), 1762300204);

            return;
        }
        $settings = $this->settingsRepository->findByEventUid((int)$occurrence->getEvent()->getUid());
        $availability = $this->availabilityService->availability($settings, $occurrence, $now);

        if (!$availability->open) {
            $this->addErrorForProperty('occurrence', $this->translate('error.closed'), 1762300205);
        } elseif (!$availability->hasRoomFor($value->getSeats()) && !$availability->waitingList) {
            $this->addErrorForProperty('occurrence', $this->translate('error.full'), 1762300206);
        }
        if ($value->getSeats() > $settings->maxSeats) {
            $this->addErrorForProperty('seats', $this->translate('validator.seats', [$settings->maxSeats]), 1762300207);
        }

        $this->dispatchValidateEvent($value, $settings);
    }

    private function dispatchValidateEvent(BookingRequest $bookingRequest, BookingSettings $settings): void
    {
        $request = $this->getRequest();
        if (!$request instanceof ServerRequestInterface) {
            return;
        }
        $event = new ValidateBookingEvent($bookingRequest, $settings, $request);
        $this->eventDispatcher->dispatch($event);
        foreach ($event->getErrors() as $index => $error) {
            if ($error['property'] === '') {
                $this->addError($error['message'], 1762300210 + $index);
            } else {
                $this->addErrorForProperty($error['property'], $error['message'], 1762300210 + $index);
            }
        }
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function translate(string $key, array $arguments = []): string
    {
        return $this->translateErrorMessage($key, 'golb_events_booking', $arguments);
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->context->getPropertyFromAspect('date', 'full');

        return $now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable();
    }
}
