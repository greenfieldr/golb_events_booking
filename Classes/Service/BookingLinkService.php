<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Service;

use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Absolute links to the pages of a booking (the event page with the
 * Booking form plugin): the success page, the confirmation and the
 * cancellation link. Built with the site router so they work in mails
 * sent from the frontend, the command line and the backend alike, and use
 * the route enhancer configuration if the site imports it.
 */
class BookingLinkService
{
    public const PLUGIN_NAMESPACE = 'tx_golbeventsbooking_form';

    public function __construct(private readonly SiteFinder $siteFinder) {}

    public function successUrl(Booking $booking): string
    {
        return $this->url($booking, 'success');
    }

    public function confirmUrl(Booking $booking): string
    {
        return $this->url($booking, 'confirm');
    }

    public function cancelUrl(Booking $booking): string
    {
        return $this->url($booking, 'cancel');
    }

    /**
     * The absolute URL of the event page, '' if the page has no site.
     */
    public function eventUrl(Booking $booking): string
    {
        return $this->build((int)$booking->getEvent()?->getUid(), []);
    }

    /**
     * @param string $action success, confirm or cancel
     * @return string the absolute URL, '' if the booking has no event page or token
     */
    public function url(Booking $booking, string $action): string
    {
        if ($booking->getToken() === '') {
            return '';
        }

        return $this->build((int)$booking->getEvent()?->getUid(), [
            self::PLUGIN_NAMESPACE => [
                'controller' => 'Booking',
                'action' => $action,
                'token' => $booking->getToken(),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function build(int $pageUid, array $parameters): string
    {
        if ($pageUid <= 0) {
            return '';
        }
        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
        } catch (SiteNotFoundException) {
            return '';
        }

        return (string)$site->getRouter()->generateUri($pageUid, $parameters);
    }
}
