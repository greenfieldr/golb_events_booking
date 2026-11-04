<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Exception;

/**
 * A booking request that cannot be fulfilled. The message key names the
 * translatable reason (locallang.xlf "error.<key>").
 */
abstract class BookingException extends \RuntimeException
{
    abstract public function getMessageKey(): string;
}
