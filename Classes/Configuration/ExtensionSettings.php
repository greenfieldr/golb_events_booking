<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Configuration;

use Greenfieldr\GolbEventsBooking\Constants;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * The extension configuration (ext_conf_template.txt) with its defaults.
 */
class ExtensionSettings
{
    public const DEFAULT_PENDING_HOURS = 48;

    public function __construct(private readonly ExtensionConfiguration $extensionConfiguration) {}

    /**
     * Whether guests have to confirm their booking through the link sent by
     * email before it counts as confirmed.
     */
    public function isDoubleOptIn(): bool
    {
        $value = $this->get('doubleOptIn');

        return $value === null || (bool)$value;
    }

    /**
     * Hours a pending booking reserves its seats.
     */
    public function getPendingHours(): int
    {
        $value = $this->get('pendingHours');

        return is_numeric($value) && (int)$value > 0 ? (int)$value : self::DEFAULT_PENDING_HOURS;
    }

    /**
     * Days after the booked date the personal data of a booking is
     * anonymized by the cleanup command, 0 to keep it.
     */
    public function getRetentionDays(): int
    {
        $value = $this->get('retentionDays');

        return is_numeric($value) ? max(0, (int)$value) : 0;
    }

    private function get(string $key): mixed
    {
        try {
            return $this->extensionConfiguration->get(Constants::EXTENSION_KEY, $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
