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

use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEventsBooking\Constants;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\OccurrenceAvailability;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Counts the seats of a date. Pending and confirmed bookings block seats,
 * waiting and cancelled ones do not. Counts are read from the database
 * directly so they reflect bookings persisted a moment ago.
 */
class AvailabilityService
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    public function availability(BookingSettings $settings, Occurrence $occurrence, \DateTimeImmutable $now): OccurrenceAvailability
    {
        $booked = $this->bookedSeats($occurrence);
        $start = $occurrence->getStart();
        $open = $start instanceof \DateTimeImmutable && $settings->isOpenAt($now, $start);

        return new OccurrenceAvailability(
            $occurrence,
            $booked,
            $settings->hasCapacityLimit() ? max(0, $settings->capacity - $booked) : null,
            $this->waitingCount($occurrence),
            $open,
            $settings->waitingList
        );
    }

    /**
     * Seats of the pending and confirmed bookings of a date.
     */
    public function bookedSeats(Occurrence $occurrence): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Constants::TABLE_BOOKING);
        $sum = $queryBuilder->addSelectLiteral('SUM(' . $queryBuilder->quoteIdentifier('seats') . ')')
            ->from(Constants::TABLE_BOOKING)
            ->where(
                $queryBuilder->expr()->eq('occurrence', $queryBuilder->createNamedParameter((int)$occurrence->getUid(), Connection::PARAM_INT)),
                $queryBuilder->expr()->in('status', $queryBuilder->createNamedParameter(Booking::STATUSES_BLOCKING_SEATS, Connection::PARAM_STR_ARRAY))
            )
            ->executeQuery()
            ->fetchOne();

        return is_numeric($sum) ? (int)$sum : 0;
    }

    /**
     * Number of bookings on the waiting list of a date.
     */
    public function waitingCount(Occurrence $occurrence): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Constants::TABLE_BOOKING);
        $count = $queryBuilder->count('uid')
            ->from(Constants::TABLE_BOOKING)
            ->where(
                $queryBuilder->expr()->eq('occurrence', $queryBuilder->createNamedParameter((int)$occurrence->getUid(), Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter(Booking::STATUS_WAITING))
            )
            ->executeQuery()
            ->fetchOne();

        return is_numeric($count) ? (int)$count : 0;
    }

    /**
     * Whether the date has room for the given seats; always true without a
     * capacity limit.
     */
    public function hasRoomFor(BookingSettings $settings, Occurrence $occurrence, int $seats): bool
    {
        return !$settings->hasCapacityLimit() || $settings->capacity - $this->bookedSeats($occurrence) >= $seats;
    }
}
