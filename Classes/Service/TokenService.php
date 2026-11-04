<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Service;

use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;

/**
 * The secret of a booking that authorizes its confirmation and cancellation
 * links. A random value stored with the booking; the links carry it and the
 * repository looks the booking up by it.
 */
class TokenService
{
    public const BYTES = 20;

    /**
     * Length of the hex encoded token
     */
    public const LENGTH = 2 * self::BYTES;

    public function generate(): string
    {
        return bin2hex(random_bytes(self::BYTES));
    }

    /**
     * Whether the token of a link belongs to the booking (constant time).
     */
    public function matches(Booking $booking, string $token): bool
    {
        return $booking->getToken() !== '' && $token !== '' && hash_equals($booking->getToken(), $token);
    }
}
