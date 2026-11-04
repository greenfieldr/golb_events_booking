<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Domain\Model\Dto;

use Greenfieldr\GolbEvents\Domain\Model\Occurrence;

/**
 * The booking situation of one date: how many seats are taken, how many are
 * free and whether it can be booked right now.
 */
final readonly class OccurrenceAvailability
{
    /**
     * @param int $bookedSeats seats of pending and confirmed bookings
     * @param int|null $freeSeats null when the capacity is unlimited
     * @param int $waitingCount number of bookings on the waiting list
     * @param bool $open whether the booking window is open
     * @param bool $waitingList whether a waiting list is offered when full
     */
    public function __construct(
        public Occurrence $occurrence,
        public int $bookedSeats,
        public ?int $freeSeats,
        public int $waitingCount,
        public bool $open,
        public bool $waitingList,
    ) {}

    public function isUnlimited(): bool
    {
        return $this->freeSeats === null;
    }

    public function isFull(): bool
    {
        return $this->freeSeats !== null && $this->freeSeats <= 0;
    }

    /**
     * Whether the given number of seats fits into the free seats.
     */
    public function hasRoomFor(int $seats): bool
    {
        return $this->freeSeats === null || $this->freeSeats >= $seats;
    }

    /**
     * Whether a booking is possible right now: the window is open and either
     * seats are free or the waiting list takes it.
     */
    public function isBookable(): bool
    {
        return $this->open && (!$this->isFull() || $this->waitingList);
    }

    /**
     * Whether a new booking would go onto the waiting list.
     */
    public function isWaitingListOnly(): bool
    {
        return $this->open && $this->isFull() && $this->waitingList;
    }
}
