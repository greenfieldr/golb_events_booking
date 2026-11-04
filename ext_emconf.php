<?php

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Golb Events Booking',
    'description' => 'Golb Events Booking - Book Events The Page Way! An add-on to Golb Events that lets visitors and frontend users book event dates.',
    'category' => 'fe',
    'author' => 'Marcel Küpper',
    'author_email' => 'typo3dev@kuepper.net',
    'state' => 'beta',
    'version' => '14.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
            'php' => '8.2.0-8.5.99',
            'golb' => '14.0.0-14.99.99',
            'golb_events' => '14.1.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
