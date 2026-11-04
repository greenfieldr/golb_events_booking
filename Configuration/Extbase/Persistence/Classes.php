<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;

return [
    Booking::class => [
        'tableName' => 'tx_golbeventsbooking_domain_model_booking',
    ],
];
