<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

use Greenfieldr\GolbEvents\Constants as GolbEventsConstants;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;

defined('TYPO3') || die();

$ll = 'LLL:EXT:golb_events_booking/Resources/Private/Language/locallang_db.xlf:';
$table = 'tx_golbeventsbooking_domain_model_booking';

/*
 * A booking of one date (occurrence) of an event page by a visitor or a
 * frontend user. Bookings are created in the frontend and live in a storage
 * folder; they are not translated and not versioned.
 */
return [
    'ctrl' => [
        'title' => $ll . $table,
        'label' => 'last_name',
        'label_alt' => 'first_name, email',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY crdate DESC',
        'delete' => 'deleted',
        'typeicon_classes' => [
            'default' => 'ext-golbeventsbooking-booking',
        ],
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'columns' => [
        'pid' => [
            'label' => 'pid',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'crdate' => [
            'label' => 'crdate',
            'config' => [
                'type' => 'datetime', 'searchable' => false,
            ],
        ],
        'tstamp' => [
            'label' => 'tstamp',
            'config' => [
                'type' => 'datetime', 'searchable' => false,
            ],
        ],
        'event' => [
            'label' => $ll . $table . '.event',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
                'default' => 0,
                'suggestOptions' => [
                    'default' => [
                        'addWhere' => ' AND pages.doktype = ' . GolbEventsConstants::EVENT_DOKTYPE,
                    ],
                ],
            ],
        ],
        'occurrence' => [
            'label' => $ll . $table . '.occurrence',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $ll . $table . '.occurrence.none', 'value' => 0],
                ],
                'foreign_table' => 'tx_golbevents_domain_model_occurrence',
                'foreign_table_where' => ' AND {#tx_golbevents_domain_model_occurrence}.{#event} = ###REC_FIELD_event### ORDER BY tx_golbevents_domain_model_occurrence.start',
                'default' => 0,
            ],
        ],
        'start' => [
            'label' => $ll . $table . '.start',
            'description' => $ll . $table . '.start.description',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'readOnly' => true,
                'default' => 0,
                'searchable' => false,
            ],
        ],
        'status' => [
            'label' => $ll . $table . '.status',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $ll . 'status.' . Booking::STATUS_PENDING, 'value' => Booking::STATUS_PENDING],
                    ['label' => $ll . 'status.' . Booking::STATUS_CONFIRMED, 'value' => Booking::STATUS_CONFIRMED],
                    ['label' => $ll . 'status.' . Booking::STATUS_WAITING, 'value' => Booking::STATUS_WAITING],
                    ['label' => $ll . 'status.' . Booking::STATUS_CANCELLED, 'value' => Booking::STATUS_CANCELLED],
                ],
                'default' => Booking::STATUS_CONFIRMED,
            ],
        ],
        'pending_reason' => [
            'label' => $ll . $table . '.pending_reason',
            'displayCond' => 'FIELD:status:=:' . Booking::STATUS_PENDING,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $ll . 'pendingReason.none', 'value' => ''],
                    ['label' => $ll . 'pendingReason.' . Booking::PENDING_REASON_OPTIN, 'value' => Booking::PENDING_REASON_OPTIN],
                ],
                'default' => '',
            ],
        ],
        'pending_until' => [
            'label' => $ll . $table . '.pending_until',
            'description' => $ll . $table . '.pending_until.description',
            'displayCond' => 'FIELD:status:=:' . Booking::STATUS_PENDING,
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'nullable' => true,
                'default' => null,
                'searchable' => false,
            ],
        ],
        'date_removed' => [
            'label' => $ll . $table . '.date_removed',
            'description' => $ll . $table . '.date_removed.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'seats' => [
            'label' => $ll . $table . '.seats',
            'config' => [
                'type' => 'number',
                'default' => 1,
                'range' => [
                    'lower' => 1,
                ],
            ],
        ],
        'frontend_user' => [
            'label' => $ll . $table . '.frontend_user',
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_users',
                'size' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'salutation' => [
            'label' => $ll . $table . '.salutation',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => ''],
                    ['label' => $ll . 'salutation.' . Booking::SALUTATION_MR, 'value' => Booking::SALUTATION_MR],
                    ['label' => $ll . 'salutation.' . Booking::SALUTATION_MS, 'value' => Booking::SALUTATION_MS],
                    ['label' => $ll . 'salutation.' . Booking::SALUTATION_MX, 'value' => Booking::SALUTATION_MX],
                ],
                'default' => '',
            ],
        ],
        'first_name' => [
            'label' => $ll . $table . '.first_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'required' => true,
                'default' => '',
            ],
        ],
        'last_name' => [
            'label' => $ll . $table . '.last_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'required' => true,
                'default' => '',
            ],
        ],
        'email' => [
            'label' => $ll . $table . '.email',
            'config' => [
                'type' => 'email',
                'required' => true,
                'default' => '',
            ],
        ],
        'phone' => [
            'label' => $ll . $table . '.phone',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'default' => '',
                'searchable' => false,
            ],
        ],
        'company' => [
            'label' => $ll . $table . '.company',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'default' => '',
            ],
        ],
        'note' => [
            'label' => $ll . $table . '.note',
            'config' => [
                'type' => 'text',
                'rows' => 4,
                'default' => '',
                'searchable' => false,
            ],
        ],
        'privacy_accepted_at' => [
            'label' => $ll . $table . '.privacy_accepted_at',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'readOnly' => true,
                'default' => 0,
                'searchable' => false,
            ],
        ],
        'confirmed_at' => [
            'label' => $ll . $table . '.confirmed_at',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'readOnly' => true,
                'default' => 0,
                'searchable' => false,
            ],
        ],
        'cancelled_at' => [
            'label' => $ll . $table . '.cancelled_at',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'readOnly' => true,
                'default' => 0,
                'searchable' => false,
            ],
        ],
        'reminder_sent_at' => [
            'label' => $ll . $table . '.reminder_sent_at',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'readOnly' => true,
                'default' => 0,
                'searchable' => false,
            ],
        ],
        'anonymized' => [
            'label' => $ll . $table . '.anonymized',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'readOnly' => true,
                'default' => 0,
            ],
        ],
        // Secret of the confirmation and cancellation links, never shown
        'token' => [
            'label' => 'token',
            'config' => [
                'type' => 'passthrough',
                'default' => '',
            ],
        ],
    ],
    'palettes' => [
        'date' => [
            'showitem' => 'event, occurrence, --linebreak--, start, seats',
        ],
        'status' => [
            'showitem' => 'status, pending_reason, pending_until, --linebreak--, date_removed',
        ],
        'name' => [
            'showitem' => 'salutation, first_name, last_name',
        ],
        'contact' => [
            'showitem' => 'email, phone, --linebreak--, company',
        ],
        'bookkeeping' => [
            'showitem' => 'privacy_accepted_at, confirmed_at, --linebreak--, cancelled_at, reminder_sent_at, --linebreak--, anonymized',
        ],
    ],
    'types' => [
        0 => [
            'showitem' => '
                --div--;core.form.tabs:general,
                    --palette--;;date,
                    --palette--;' . $ll . $table . '.palette.status;;status,
                --div--;' . $ll . $table . '.tab.participant,
                    frontend_user,
                    --palette--;;name,
                    --palette--;;contact,
                    note,
                --div--;' . $ll . $table . '.tab.bookkeeping,
                    --palette--;;bookkeeping,
            ',
        ],
    ],
];
