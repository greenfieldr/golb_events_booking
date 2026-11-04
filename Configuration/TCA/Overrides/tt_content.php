<?php

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

$boot = function () {
    $ll = 'LLL:EXT:golb_events_booking/Resources/Private/Language/locallang_be.xlf:';

    $formSignature = ExtensionUtility::registerPlugin(
        'golb_events_booking',
        'Form',
        $ll . 'tt_content.plugin.form.title',
        'ext-golbeventsbooking-plugin-form',
        'Golb',
        $ll . 'tt_content.plugin.form.description',
        'FILE:EXT:golb_events_booking/Configuration/FlexForms/Form.xml'
    );
    $myBookingsSignature = ExtensionUtility::registerPlugin(
        'golb_events_booking',
        'MyBookings',
        $ll . 'tt_content.plugin.myBookings.title',
        'ext-golbeventsbooking-plugin-my-bookings',
        'Golb',
        $ll . 'tt_content.plugin.myBookings.description'
    );

    $GLOBALS['TCA']['tt_content']['types'][$formSignature]['showitem'] = '
        --div--;core.form.tabs:general,
            --palette--;;general,
            --palette--;;headers,
        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.plugin,
            pi_flexform,
        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.appearance,
            --palette--;;frames,
            --palette--;;appearanceLinks,
        --div--;core.form.tabs:language,
            --palette--;;language,
        --div--;core.form.tabs:access,
            --palette--;;hidden,
            --palette--;;access,
        --div--;core.form.tabs:notes,
            rowDescription,
        --div--;core.form.tabs:extended,
    ';
    $GLOBALS['TCA']['tt_content']['types'][$myBookingsSignature]['showitem'] = '
        --div--;core.form.tabs:general,
            --palette--;;general,
            --palette--;;headers,
        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.appearance,
            --palette--;;frames,
            --palette--;;appearanceLinks,
        --div--;core.form.tabs:language,
            --palette--;;language,
        --div--;core.form.tabs:access,
            --palette--;;hidden,
            --palette--;;access,
        --div--;core.form.tabs:notes,
            rowDescription,
        --div--;core.form.tabs:extended,
    ';
};
$boot();
unset($boot);
