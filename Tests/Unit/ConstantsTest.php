<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Unit;

use Greenfieldr\GolbEventsBooking\Constants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(Constants::class)]
final class ConstantsTest extends UnitTestCase
{
    #[Test]
    public function extensionNameIsTheUpperCamelCaseExtensionKey(): void
    {
        self::assertSame(GeneralUtility::underscoredToUpperCamelCase(Constants::EXTENSION_KEY), Constants::EXTENSION_NAME);
    }

    #[Test]
    public function tableNameFollowsTheExtbaseConvention(): void
    {
        self::assertSame('tx_' . str_replace('_', '', Constants::EXTENSION_KEY) . '_domain_model_booking', Constants::TABLE_BOOKING);
    }
}
