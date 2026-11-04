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

use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingRequest;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings;
use Greenfieldr\GolbEventsBooking\Event\ValidateBookingEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ValidateBookingEvent::class)]
final class ValidateBookingEventTest extends UnitTestCase
{
    #[Test]
    public function collectsErrorsForPropertiesAndTheWholeForm(): void
    {
        $bookingRequest = new BookingRequest();
        $settings = new BookingSettings(3, enabled: true);
        $request = new ServerRequest('https://example.org/');

        $event = new ValidateBookingEvent($bookingRequest, $settings, $request);

        self::assertSame($bookingRequest, $event->getBookingRequest());
        self::assertSame($settings, $event->getSettings());
        self::assertSame($request, $event->getRequest());
        self::assertFalse($event->hasErrors());

        $event->addError('Captcha failed');
        $event->addError('Blocked domain', 'email');

        self::assertTrue($event->hasErrors());
        self::assertSame([
            ['property' => '', 'message' => 'Captcha failed'],
            ['property' => 'email', 'message' => 'Blocked domain'],
        ], $event->getErrors());
    }
}
