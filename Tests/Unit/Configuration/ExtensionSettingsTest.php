<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Unit\Configuration;

use Greenfieldr\GolbEventsBooking\Configuration\ExtensionSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Exception;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ExtensionSettings::class)]
final class ExtensionSettingsTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $values
     */
    private function subject(array $values): ExtensionSettings
    {
        $configuration = self::createStub(ExtensionConfiguration::class);
        $configuration->method('get')->willReturnCallback(static function (string $extension, string $path) use ($values): mixed {
            if (!array_key_exists($path, $values)) {
                throw new Exception('not configured', 1762300100);
            }

            return $values[$path];
        });

        return new ExtensionSettings($configuration);
    }

    #[Test]
    public function defaultsApplyWhenNothingIsConfigured(): void
    {
        $subject = $this->subject([]);

        self::assertTrue($subject->isDoubleOptIn());
        self::assertSame(ExtensionSettings::DEFAULT_PENDING_HOURS, $subject->getPendingHours());
        self::assertSame(0, $subject->getRetentionDays());
    }

    #[Test]
    public function configuredValuesAreRead(): void
    {
        $subject = $this->subject(['doubleOptIn' => '0', 'pendingHours' => '6', 'retentionDays' => '30']);

        self::assertFalse($subject->isDoubleOptIn());
        self::assertSame(6, $subject->getPendingHours());
        self::assertSame(30, $subject->getRetentionDays());
    }

    #[Test]
    public function invalidValuesFallBackToTheDefaults(): void
    {
        $subject = $this->subject(['pendingHours' => 'soon', 'retentionDays' => '-3']);

        self::assertSame(ExtensionSettings::DEFAULT_PENDING_HOURS, $subject->getPendingHours());
        self::assertSame(0, $subject->getRetentionDays());
        self::assertSame(ExtensionSettings::DEFAULT_PENDING_HOURS, $this->subject(['pendingHours' => '0'])->getPendingHours());
    }
}
