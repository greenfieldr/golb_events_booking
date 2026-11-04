<?php

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

use Greenfieldr\GolbEvents\Constants as GolbEventsConstants;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

$boot = function () {
    $ll = 'LLL:EXT:golb_events_booking/Resources/Private/Language/locallang_db.xlf:';
    $doktype = (string)GolbEventsConstants::EVENT_DOKTYPE;
    $enabledCondition = 'FIELD:tx_golbeventsbooking_enabled:REQ:true';

    $tempColumns = [
        'tx_golbeventsbooking_enabled' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbeventsbooking_enabled',
            'description' => $ll . 'pages.tx_golbeventsbooking_enabled.description',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'tx_golbeventsbooking_capacity' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbeventsbooking_capacity',
            'description' => $ll . 'pages.tx_golbeventsbooking_capacity.description',
            'l10n_mode' => 'exclude',
            'displayCond' => $enabledCondition,
            'config' => [
                'type' => 'number',
                'default' => 0,
                'range' => [
                    'lower' => 0,
                ],
            ],
        ],
        'tx_golbeventsbooking_max_seats' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbeventsbooking_max_seats',
            'description' => $ll . 'pages.tx_golbeventsbooking_max_seats.description',
            'l10n_mode' => 'exclude',
            'displayCond' => $enabledCondition,
            'config' => [
                'type' => 'number',
                'default' => 1,
                'range' => [
                    'lower' => 1,
                ],
            ],
        ],
        'tx_golbeventsbooking_waiting_list' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbeventsbooking_waiting_list',
            'description' => $ll . 'pages.tx_golbeventsbooking_waiting_list.description',
            'l10n_mode' => 'exclude',
            'displayCond' => $enabledCondition,
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'tx_golbeventsbooking_opens' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbeventsbooking_opens',
            'description' => $ll . 'pages.tx_golbeventsbooking_opens.description',
            'l10n_mode' => 'exclude',
            'displayCond' => $enabledCondition,
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'nullable' => true,
                'default' => null,
            ],
        ],
        'tx_golbeventsbooking_closes_hours' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbeventsbooking_closes_hours',
            'description' => $ll . 'pages.tx_golbeventsbooking_closes_hours.description',
            'l10n_mode' => 'exclude',
            'displayCond' => $enabledCondition,
            'config' => [
                'type' => 'number',
                'default' => 0,
                'range' => [
                    'lower' => 0,
                ],
            ],
        ],
        'tx_golbeventsbooking_notification_email' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbeventsbooking_notification_email',
            'description' => $ll . 'pages.tx_golbeventsbooking_notification_email.description',
            'l10n_mode' => 'exclude',
            'displayCond' => $enabledCondition,
            'config' => [
                'type' => 'email',
                'default' => '',
            ],
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns('pages', $tempColumns);

    $GLOBALS['TCA']['pages']['palettes']['tx_golbeventsbooking_settings'] = [
        'label' => $ll . 'pages.palette.bookingSettings',
        'showitem' => 'tx_golbeventsbooking_enabled, --linebreak--, '
            . 'tx_golbeventsbooking_capacity, tx_golbeventsbooking_max_seats, tx_golbeventsbooking_waiting_list, --linebreak--, '
            . 'tx_golbeventsbooking_opens, tx_golbeventsbooking_closes_hours, --linebreak--, '
            . 'tx_golbeventsbooking_notification_email',
    ];

    ExtensionManagementUtility::addToAllTCAtypes(
        'pages',
        '--div--;' . $ll . 'pages.bookingTab, --palette--;;tx_golbeventsbooking_settings',
        $doktype
    );
};
$boot();
unset($boot);
