<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Unit\Domain\Model\Dto;

use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(BookingSettings::class)]
final class BookingSettingsTest extends UnitTestCase
{
    #[Test]
    public function isBuiltFromAPageRow(): void
    {
        $settings = BookingSettings::fromRow([
            'uid' => '3',
            'tx_golbeventsbooking_enabled' => '1',
            'tx_golbeventsbooking_capacity' => '20',
            'tx_golbeventsbooking_max_seats' => '4',
            'tx_golbeventsbooking_waiting_list' => 1,
            'tx_golbeventsbooking_opens' => (string)(new \DateTimeImmutable('2026-01-01 10:00:00'))->getTimestamp(),
            'tx_golbeventsbooking_closes_hours' => 24,
            'tx_golbeventsbooking_notification_email' => ' office@example.org ',
        ]);

        self::assertSame(3, $settings->eventUid);
        self::assertTrue($settings->enabled);
        self::assertSame(20, $settings->capacity);
        self::assertTrue($settings->hasCapacityLimit());
        self::assertSame(4, $settings->maxSeats);
        self::assertTrue($settings->waitingList);
        self::assertSame('2026-01-01 10:00:00', $settings->opensAt?->format('Y-m-d H:i:s'));
        self::assertSame(24, $settings->closesHoursBefore);
        self::assertSame('office@example.org', $settings->notificationEmail);
    }

    #[Test]
    public function missingAndInvalidValuesFallBackToTheDefaults(): void
    {
        $settings = BookingSettings::fromRow(['uid' => 'x', 'tx_golbeventsbooking_max_seats' => '0', 'tx_golbeventsbooking_capacity' => '-5', 'tx_golbeventsbooking_opens' => null]);

        self::assertSame(0, $settings->eventUid);
        self::assertFalse($settings->enabled);
        self::assertSame(0, $settings->capacity);
        self::assertFalse($settings->hasCapacityLimit());
        self::assertSame(1, $settings->maxSeats);
        self::assertFalse($settings->waitingList);
        self::assertNull($settings->opensAt);
        self::assertSame(0, $settings->closesHoursBefore);
        self::assertSame('', $settings->notificationEmail);
    }

    #[Test]
    public function bookingWindowRespectsEnabledOpeningAndClosing(): void
    {
        $start = new \DateTimeImmutable('2026-05-04 19:30:00');
        $opens = new \DateTimeImmutable('2026-04-01 00:00:00');

        $disabled = new BookingSettings(1, enabled: false);
        self::assertFalse($disabled->isOpenAt(new \DateTimeImmutable('2026-04-15'), $start));

        $settings = new BookingSettings(1, enabled: true, opensAt: $opens, closesHoursBefore: 2);
        self::assertSame('2026-05-04 17:30:00', $settings->closesAt($start)->format('Y-m-d H:i:s'));
        self::assertFalse($settings->isOpenAt(new \DateTimeImmutable('2026-03-31 23:59:59'), $start), 'not opened yet');
        self::assertTrue($settings->isOpenAt($opens, $start), 'opens at the opening time');
        self::assertTrue($settings->isOpenAt(new \DateTimeImmutable('2026-05-04 17:29:59'), $start));
        self::assertFalse($settings->isOpenAt(new \DateTimeImmutable('2026-05-04 17:30:00'), $start), 'closed two hours before');

        $untilStart = new BookingSettings(1, enabled: true);
        self::assertSame($start, $untilStart->closesAt($start));
        self::assertTrue($untilStart->isOpenAt($start->modify('-1 second'), $start));
        self::assertFalse($untilStart->isOpenAt($start, $start), 'closed at the start');
    }
}
