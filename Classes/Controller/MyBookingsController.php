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
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingRepository;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingSettingsRepository;
use Greenfieldr\GolbEventsBooking\Exception\BookingException;
use Greenfieldr\GolbEventsBooking\Service\BookingStateService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * The "My bookings" plugin: lists the active bookings of the logged in
 * frontend user and lets them cancel. Visitors who are not logged in see
 * a hint; login and registration are up to other extensions.
 */
class MyBookingsController extends BaseController
{
    public function __construct(
        protected readonly BookingRepository $bookingRepository,
        protected readonly BookingSettingsRepository $settingsRepository,
        protected readonly BookingStateService $bookingStateService,
    ) {}

    public function listAction(): ResponseInterface
    {
        $userUid = $this->frontendUserUid();
        $this->view->assign('loggedIn', $userUid > 0);
        $this->view->assign('bookings', $userUid > 0 ? $this->bookingRepository->findActiveByFrontendUser($userUid) : []);

        return $this->htmlResponse();
    }

    /**
     * Cancels one of the user's own bookings (POST only).
     */
    public function cancelAction(Booking $booking): ResponseInterface
    {
        $userUid = $this->frontendUserUid();
        if ($this->request->getMethod() !== 'POST' || $userUid <= 0 || $booking->getFrontendUser() !== $userUid) {
            $this->addFlashMessage($this->translate('myBookings.notYours'), '', ContextualFeedbackSeverity::ERROR);

            return $this->redirect('list');
        }

        try {
            $this->bookingStateService->cancel($booking, $this->settingsRepository->findByEventUid((int)$booking->getEvent()?->getUid()));
            $this->addFlashMessage($this->translate('myBookings.cancelled'), '', ContextualFeedbackSeverity::OK);
        } catch (BookingException $exception) {
            $this->addFlashMessage($this->translate('error.' . $exception->getMessageKey()), '', ContextualFeedbackSeverity::ERROR);
        }

        return $this->redirect('list');
    }

    protected function frontendUserUid(): int
    {
        $frontendUser = $this->request->getAttribute('frontend.user');
        $uid = $frontendUser instanceof FrontendUserAuthentication && is_array($frontendUser->user) ? ($frontendUser->user['uid'] ?? 0) : 0;

        return is_numeric($uid) ? (int)$uid : 0;
    }

    protected function translate(string $key): string
    {
        return LocalizationUtility::translate($key, 'GolbEventsBooking') ?? $key;
    }
}
