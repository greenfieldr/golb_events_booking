<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Unit\Service;

use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Service\TokenService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(TokenService::class)]
final class TokenServiceTest extends UnitTestCase
{
    #[Test]
    public function generatesRandomHexTokensOfFixedLength(): void
    {
        $subject = new TokenService();
        $first = $subject->generate();
        $second = $subject->generate();

        self::assertSame(TokenService::LENGTH, strlen($first));
        self::assertMatchesRegularExpression('/^[0-9a-f]+$/', $first);
        self::assertNotSame($first, $second);
    }

    #[Test]
    public function matchesOnlyTheTokenOfTheBooking(): void
    {
        $subject = new TokenService();
        $booking = new Booking();
        $booking->setToken('abc');

        self::assertTrue($subject->matches($booking, 'abc'));
        self::assertFalse($subject->matches($booking, 'abd'));
        self::assertFalse($subject->matches($booking, ''));

        $booking->setToken('');
        self::assertFalse($subject->matches($booking, ''), 'a booking without token (anonymized) matches nothing');
    }
}
