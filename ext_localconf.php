<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

use Greenfieldr\GolbEventsBooking\Controller\BookingController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

// Templates and partials of the booking mails (FluidEmail); override them
// with a path at a higher index in your site package
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][750] = 'EXT:golb_events_booking/Resources/Private/Templates/Email/';
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['partialRootPaths'][750] = 'EXT:golb_events_booking/Resources/Private/Partials/Email/';

// The free seats change with every booking, so every action is uncached
ExtensionUtility::configurePlugin(
    'GolbEventsBooking',
    'Form',
    [BookingController::class => 'form,create,success,confirm,cancel'],
    [BookingController::class => 'form,create,success,confirm,cancel']
);
