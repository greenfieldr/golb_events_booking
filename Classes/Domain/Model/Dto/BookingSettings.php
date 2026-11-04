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

/**
 * The booking settings of an event page (the tx_golbeventsbooking_* page
 * fields). Built from the page row, so no mapping of Golb Events' Event
 * model has to be changed.
 */
final readonly class BookingSettings
{
    /**
     * @param int $capacity seats per date, 0 for unlimited
     * @param int $maxSeats seats one booking may take
     * @param \DateTimeImmutable|null $opensAt bookings are accepted from this time on, null for always
     * @param int $closesHoursBefore bookings close this many hours before the start of the date
     */
    public function __construct(
        public int $eventUid,
        public bool $enabled = false,
        public int $capacity = 0,
        public int $maxSeats = 1,
        public bool $waitingList = false,
        public ?\DateTimeImmutable $opensAt = null,
        public int $closesHoursBefore = 0,
        public string $notificationEmail = '',
    ) {}

    /**
     * @param array<string, mixed> $row a pages row
     */
    public static function fromRow(array $row): self
    {
        $int = static fn(mixed $value): int => is_numeric($value) ? (int)$value : 0;
        $opens = $row['tx_golbeventsbooking_opens'] ?? null;

        return new self(
            $int($row['uid'] ?? 0),
            $int($row['tx_golbeventsbooking_enabled'] ?? 0) !== 0,
            max(0, $int($row['tx_golbeventsbooking_capacity'] ?? 0)),
            max(1, $int($row['tx_golbeventsbooking_max_seats'] ?? 1)),
            $int($row['tx_golbeventsbooking_waiting_list'] ?? 0) !== 0,
            is_numeric($opens) && (int)$opens > 0 ? (new \DateTimeImmutable('@' . (int)$opens))->setTimezone(new \DateTimeZone(date_default_timezone_get())) : null,
            max(0, $int($row['tx_golbeventsbooking_closes_hours'] ?? 0)),
            is_string($row['tx_golbeventsbooking_notification_email'] ?? null) ? trim($row['tx_golbeventsbooking_notification_email']) : ''
        );
    }

    public function hasCapacityLimit(): bool
    {
        return $this->capacity > 0;
    }

    /**
     * Whether bookings for a date starting at $start are accepted at $now:
     * booking is enabled, has opened and the date has not closed yet.
     */
    public function isOpenAt(\DateTimeImmutable $now, \DateTimeImmutable $start): bool
    {
        if (!$this->enabled) {
            return false;
        }
        if ($this->opensAt instanceof \DateTimeImmutable && $now < $this->opensAt) {
            return false;
        }

        return $now < $this->closesAt($start);
    }

    /**
     * The point in time bookings for a date starting at $start close.
     */
    public function closesAt(\DateTimeImmutable $start): \DateTimeImmutable
    {
        return $this->closesHoursBefore > 0 ? $start->modify('-' . $this->closesHoursBefore . ' hours') : $start;
    }
}
