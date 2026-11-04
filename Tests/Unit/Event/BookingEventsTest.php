<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Unit\Event;

use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingCancelledEvent;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingConfirmedEvent;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingCreatedEvent;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingPromotedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(AfterBookingCreatedEvent::class)]
#[CoversClass(AfterBookingConfirmedEvent::class)]
#[CoversClass(AfterBookingCancelledEvent::class)]
#[CoversClass(AfterBookingPromotedEvent::class)]
final class BookingEventsTest extends UnitTestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function eventClassDataProvider(): iterable
    {
        foreach ([AfterBookingCreatedEvent::class, AfterBookingConfirmedEvent::class, AfterBookingCancelledEvent::class, AfterBookingPromotedEvent::class] as $class) {
            yield $class => [$class];
        }
    }

    /**
     * @param class-string $class
     */
    #[Test]
    #[DataProvider('eventClassDataProvider')]
    public function eventExposesBookingAndSettings(string $class): void
    {
        $booking = new Booking();
        $settings = new BookingSettings(3, enabled: true);

        $event = new $class($booking, $settings);

        self::assertTrue(method_exists($event, 'getBooking'));
        self::assertTrue(method_exists($event, 'getSettings'));
        self::assertSame($booking, $event->getBooking());
        self::assertSame($settings, $event->getSettings());
    }
}
