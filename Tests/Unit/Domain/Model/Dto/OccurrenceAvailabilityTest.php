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

use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\OccurrenceAvailability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(OccurrenceAvailability::class)]
final class OccurrenceAvailabilityTest extends UnitTestCase
{
    #[Test]
    public function unlimitedDateIsNeverFull(): void
    {
        $availability = new OccurrenceAvailability(new Occurrence(), 120, null, 0, true, false);

        self::assertTrue($availability->isUnlimited());
        self::assertFalse($availability->isFull());
        self::assertTrue($availability->hasRoomFor(1000));
        self::assertTrue($availability->isBookable());
        self::assertFalse($availability->isWaitingListOnly());
    }

    #[Test]
    public function limitedDateWithFreeSeats(): void
    {
        $availability = new OccurrenceAvailability(new Occurrence(), 8, 2, 0, true, false);

        self::assertFalse($availability->isFull());
        self::assertTrue($availability->hasRoomFor(2));
        self::assertFalse($availability->hasRoomFor(3));
        self::assertTrue($availability->isBookable());
        self::assertFalse($availability->isWaitingListOnly());
    }

    #[Test]
    public function fullDateIsBookableOnlyWithWaitingList(): void
    {
        $withoutList = new OccurrenceAvailability(new Occurrence(), 10, 0, 0, true, false);
        self::assertTrue($withoutList->isFull());
        self::assertFalse($withoutList->isBookable());
        self::assertFalse($withoutList->isWaitingListOnly());

        $withList = new OccurrenceAvailability(new Occurrence(), 10, 0, 2, true, true);
        self::assertTrue($withList->isBookable());
        self::assertTrue($withList->isWaitingListOnly());
    }

    #[Test]
    public function closedWindowIsNeverBookable(): void
    {
        $availability = new OccurrenceAvailability(new Occurrence(), 0, 10, 0, false, true);

        self::assertFalse($availability->isBookable());
        self::assertFalse($availability->isWaitingListOnly());
    }
}
