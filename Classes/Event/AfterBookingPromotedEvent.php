<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Event;

use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings;

/**
 * Dispatched after a waiting booking moved up to confirmed because seats became free.
 */
final readonly class AfterBookingPromotedEvent
{
    public function __construct(
        private Booking $booking,
        private BookingSettings $settings,
    ) {}

    public function getBooking(): Booking
    {
        return $this->booking;
    }

    public function getSettings(): BookingSettings
    {
        return $this->settings;
    }
}
