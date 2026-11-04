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

use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(BookingRequest::class)]
final class BookingRequestTest extends UnitTestCase
{
    #[Test]
    public function trimsTextsAndKeepsKnownSalutationsOnly(): void
    {
        $request = new BookingRequest();
        $request->setFirstName(' Ada ');
        $request->setLastName(" Lovelace\n");
        $request->setEmail(' ada@example.org ');
        $request->setPhone(' +44 ');
        $request->setCompany(' Engines ');
        $request->setNote(" Hi \n");
        $request->setSalutation('ms');

        self::assertSame('Ada', $request->getFirstName());
        self::assertSame('Lovelace', $request->getLastName());
        self::assertSame('ada@example.org', $request->getEmail());
        self::assertSame('+44', $request->getPhone());
        self::assertSame('Engines', $request->getCompany());
        self::assertSame('Hi', $request->getNote());
        self::assertSame(Booking::SALUTATION_MS, $request->getSalutation());

        $request->setSalutation('captain');
        self::assertSame('', $request->getSalutation());
    }

    #[Test]
    public function spamFieldsAndChoiceRoundTrip(): void
    {
        $request = new BookingRequest();
        $request->setOccurrence(7);
        $request->setSeats(3);
        $request->setPrivacy(true);
        $request->setWebsite('x');
        $request->setStartedAt(1000);

        self::assertSame(7, $request->getOccurrence());
        self::assertSame(3, $request->getSeats());
        self::assertTrue($request->isPrivacy());
        self::assertSame('x', $request->getWebsite());
        self::assertSame(1000, $request->getStartedAt());
    }

    #[Test]
    public function appliesTheParticipantDataToABooking(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 12:00:00');
        $request = new BookingRequest();
        $request->setSalutation('mr');
        $request->setFirstName('Charles');
        $request->setLastName('Babbage');
        $request->setEmail('charles@example.org');
        $request->setPhone('1');
        $request->setCompany('Engines');
        $request->setSeats(2);
        $request->setNote('Note');
        $request->setPrivacy(true);

        $booking = new Booking();
        $request->applyTo($booking, $now);

        self::assertSame('mr', $booking->getSalutation());
        self::assertSame('Charles Babbage', $booking->getName());
        self::assertSame('charles@example.org', $booking->getEmail());
        self::assertSame('1', $booking->getPhone());
        self::assertSame('Engines', $booking->getCompany());
        self::assertSame(2, $booking->getSeats());
        self::assertSame('Note', $booking->getNote());
        self::assertSame($now, $booking->getPrivacyAcceptedAt());

        $request->setPrivacy(false);
        $request->applyTo($booking, $now);
        self::assertNull($booking->getPrivacyAcceptedAt());
    }
}
