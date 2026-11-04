<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Unit\Domain\Model;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(Booking::class)]
final class BookingTest extends UnitTestCase
{
    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function accessorDataProvider(): iterable
    {
        $date = new \DateTimeImmutable('2026-05-04 19:30:00');
        $values = [
            'event' => new Event(), 'occurrence' => new Occurrence(), 'start' => $date,
            'status' => Booking::STATUS_WAITING, 'pendingReason' => Booking::PENDING_REASON_OPTIN, 'pendingUntil' => $date,
            'dateRemoved' => true, 'seats' => 3, 'frontendUser' => 7, 'salutation' => Booking::SALUTATION_MX,
            'firstName' => 'Ada', 'lastName' => 'Lovelace', 'email' => 'ada@example.org', 'phone' => '+44 1', 'company' => 'Analytical Engines',
            'note' => 'Vegetarian', 'privacyAcceptedAt' => $date, 'token' => 'secret', 'confirmedAt' => $date, 'cancelledAt' => $date,
            'reminderSentAt' => $date, 'anonymized' => true,
        ];
        foreach ($values as $property => $value) {
            yield $property => [$property, $value];
        }
    }

    #[Test]
    #[DataProvider('accessorDataProvider')]
    public function settersAndGettersRoundTrip(string $property, mixed $value): void
    {
        $booking = new Booking();
        $setter = 'set' . ucfirst($property);
        $getter = (is_bool($value) ? 'is' : 'get') . ucfirst($property);
        self::assertTrue(method_exists($booking, $setter), 'Missing setter ' . $setter);
        self::assertTrue(method_exists($booking, $getter), 'Missing getter ' . $getter);

        (new \ReflectionMethod($booking, $setter))->invoke($booking, $value);

        self::assertSame($value, (new \ReflectionMethod($booking, $getter))->invoke($booking));
    }

    #[Test]
    public function defaultsAreAConfirmedGuestBookingOfOneSeat(): void
    {
        $booking = new Booking();

        self::assertTrue($booking->isConfirmed());
        self::assertTrue($booking->isGuest());
        self::assertSame(1, $booking->getSeats());
        self::assertSame('', $booking->getPendingReason());
        self::assertNull($booking->getPendingUntil());
        self::assertFalse($booking->isDateRemoved());
    }

    /**
     * @return iterable<string, array{string, bool, bool, bool, bool, bool, bool}>
     */
    public static function statusDataProvider(): iterable
    {
        // status, pending, confirmed, waiting, cancelled, active, blocking seats
        yield 'pending' => [Booking::STATUS_PENDING, true, false, false, false, true, true];
        yield 'confirmed' => [Booking::STATUS_CONFIRMED, false, true, false, false, true, true];
        yield 'waiting' => [Booking::STATUS_WAITING, false, false, true, false, true, false];
        yield 'cancelled' => [Booking::STATUS_CANCELLED, false, false, false, true, false, false];
    }

    #[Test]
    #[DataProvider('statusDataProvider')]
    public function statusPredicates(string $status, bool $pending, bool $confirmed, bool $waiting, bool $cancelled, bool $active, bool $blocking): void
    {
        $booking = new Booking();
        $booking->setStatus($status);

        self::assertSame($pending, $booking->isPending());
        self::assertSame($confirmed, $booking->isConfirmed());
        self::assertSame($waiting, $booking->isWaiting());
        self::assertSame($cancelled, $booking->isCancelled());
        self::assertSame($active, $booking->isActive());
        self::assertSame($blocking, $booking->isBlockingSeats());
    }

    #[Test]
    public function pendingBookingExpiresAfterItsDeadline(): void
    {
        $booking = new Booking();
        $booking->setStatus(Booking::STATUS_PENDING);
        $booking->setPendingUntil(new \DateTimeImmutable('2026-05-04 12:00:00'));

        self::assertFalse($booking->isPendingExpired(new \DateTimeImmutable('2026-05-04 11:59:59')));
        self::assertTrue($booking->isPendingExpired(new \DateTimeImmutable('2026-05-04 12:00:01')));

        $booking->setStatus(Booking::STATUS_CONFIRMED);
        self::assertFalse($booking->isPendingExpired(new \DateTimeImmutable('2026-05-04 12:00:01')), 'only pending bookings expire');

        $booking->setStatus(Booking::STATUS_PENDING);
        $booking->setPendingUntil(null);
        self::assertFalse($booking->isPendingExpired(new \DateTimeImmutable('2099-01-01')), 'no deadline, no expiry');
    }

    #[Test]
    public function nameJoinsFirstAndLastName(): void
    {
        $booking = new Booking();
        $booking->setFirstName('Ada');
        self::assertSame('Ada', $booking->getName());
        $booking->setLastName('Lovelace');
        self::assertSame('Ada Lovelace', $booking->getName());
    }

    #[Test]
    public function seatsAreAtLeastOne(): void
    {
        $booking = new Booking();
        $booking->setSeats(0);
        self::assertSame(1, $booking->getSeats());
    }

    #[Test]
    public function statusListsAreConsistent(): void
    {
        self::assertSame([], array_diff(Booking::STATUSES_BLOCKING_SEATS, Booking::STATUSES_ACTIVE), 'blocking statuses are active');
        self::assertSame([], array_diff(Booking::STATUSES_ACTIVE, Booking::STATUSES));
        self::assertNotContains(Booking::STATUS_CANCELLED, Booking::STATUSES_ACTIVE);
        self::assertNotContains(Booking::STATUS_WAITING, Booking::STATUSES_BLOCKING_SEATS);
    }
}
