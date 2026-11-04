<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'ext-golbeventsbooking-booking' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:golb_events_booking/Resources/Public/Icons/tx_golbeventsbooking_domain_model_booking.svg',
    ],
];
