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
 * Dispatched after dates with active bookings were removed from an event
 * (the editor changed or deleted the event). The bookings carry the
 * dateRemoved flag and no occurrence any more; they keep their start.
 * Listeners inform the organizer or the participants.
 */
final readonly class AfterBookedDatesRemovedEvent
{
    /**
     * @param list<Booking> $bookings the affected active bookings
     */
    public function __construct(
        private int $eventUid,
        private array $bookings,
        private BookingSettings $settings,
    ) {}

    public function getEventUid(): int
    {
        return $this->eventUid;
    }

    /**
     * @return list<Booking>
     */
    public function getBookings(): array
    {
        return $this->bookings;
    }

    public function getSettings(): BookingSettings
    {
        return $this->settings;
    }
}
