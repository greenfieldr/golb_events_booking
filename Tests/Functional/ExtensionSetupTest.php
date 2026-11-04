<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Verifies that the extension boots in a TYPO3 instance together with its
 * dependencies Golb and Golb Events.
 */
#[CoversNothing]
final class ExtensionSetupTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['greenfieldr/golb', 'greenfieldr/golb_events', 'greenfieldr/golb_events_booking'];

    #[Test]
    public function extensionIsLoadedTogetherWithItsDependencies(): void
    {
        self::assertTrue(ExtensionManagementUtility::isLoaded('golb'));
        self::assertTrue(ExtensionManagementUtility::isLoaded('golb_events'));
        self::assertTrue(ExtensionManagementUtility::isLoaded('golb_events_booking'));
    }
}
