<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Unit\Exception;

use Greenfieldr\GolbEventsBooking\Exception\BookingClosedException;
use Greenfieldr\GolbEventsBooking\Exception\BookingException;
use Greenfieldr\GolbEventsBooking\Exception\FullyBookedException;
use Greenfieldr\GolbEventsBooking\Exception\InvalidTransitionException;
use Greenfieldr\GolbEventsBooking\Exception\TooManySeatsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(BookingClosedException::class)]
#[CoversClass(FullyBookedException::class)]
#[CoversClass(TooManySeatsException::class)]
#[CoversClass(InvalidTransitionException::class)]
final class BookingExceptionTest extends UnitTestCase
{
    #[Test]
    public function exceptionsCarryDistinctMessageKeysAndCodes(): void
    {
        /** @var list<BookingException> $exceptions */
        $exceptions = [new BookingClosedException(), new FullyBookedException(), new TooManySeatsException(), new InvalidTransitionException()];
        $keys = [];
        $codes = [];
        foreach ($exceptions as $exception) {
            self::assertNotSame('', $exception->getMessage());
            $keys[] = $exception->getMessageKey();
            $codes[] = $exception->getCode();
        }

        self::assertSame(['closed', 'full', 'tooManySeats', 'invalidTransition'], $keys);
        self::assertSame($codes, array_unique($codes));
    }

    #[Test]
    public function messageAndPreviousCanBeOverridden(): void
    {
        $previous = new \RuntimeException('cause');
        $exception = new FullyBookedException('No seats left', 42, $previous);

        self::assertSame('No seats left', $exception->getMessage());
        self::assertSame(42, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }
}
