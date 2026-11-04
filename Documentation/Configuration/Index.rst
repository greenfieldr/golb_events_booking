..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

..  _configuration-typoscript:

TypoScript
==========

The static TypoScript "Golb Events Booking" defines the view paths and
settings of the plugins under :typoscript:`plugin.tx_golbeventsbooking`.
The plugin specific objects :typoscript:`plugin.tx_golbeventsbooking_form`
and :typoscript:`plugin.tx_golbeventsbooking_mybookings` are copied from
it. It also defines the TypoScript object
:typoscript:`lib.golbEventsBooking.form` that renders the booking form of
the current event page, see :ref:`templating-detail`.

..  confval:: plugin.tx_golbeventsbooking.view.templateRootPaths
    :name: typoscript-templateRootPaths
    :type: array

    Template paths. Index ``0`` holds Golb's templates (the layout is
    reused), index ``5`` the templates of this extension. Add your own
    path with a higher index to override them, see :ref:`templating`.

..  confval:: plugin.tx_golbeventsbooking.view.partialRootPaths
    :name: typoscript-partialRootPaths
    :type: array

    Partial paths: Golb (``0``), Golb Events (``3``, the date partial is
    reused), this extension (``5``).

..  confval:: plugin.tx_golbeventsbooking.view.layoutRootPaths
    :name: typoscript-layoutRootPaths
    :type: array

    Layout paths, same mechanism as the template paths.

..  confval:: plugin.tx_golbeventsbooking.persistence.storagePid
    :name: typoscript-storagePid
    :type: integer (constant)

    Uid of the folder new bookings are stored in. Required.

..  confval:: plugin.tx_golbeventsbooking.settings.css
    :name: typoscript-css
    :type: array

    The stylesheets Golb's layout adds to the page when ``includeCss`` is
    enabled: Golb's, Golb Events' and this extension's
    (``golbEventsBooking``). Remove an entry
    (:typoscript:`settings.css.golbEventsBooking >`) or add your own file.

..  _configuration-constants:

Constants
=========

The constants can be set in the constant editor (category "Golb Events
Booking") or in the TypoScript constants of the site.

..  confval:: plugin.tx_golbeventsbooking.persistence.storagePid
    :name: constant-storagePid
    :type: integer

    The storage folder of the bookings, see above.

..  confval:: plugin.tx_golbeventsbooking.settings.includeCss
    :name: constant-includeCss
    :type: boolean
    :default: ``1``

    Adds the default stylesheets to the page. Disable it to style the
    markup yourself.

..  confval:: plugin.tx_golbeventsbooking.settings.dateFormat
    :name: constant-dateFormat
    :type: string
    :default: ``%d.%m.%Y``

    Format of the dates in the plugin templates (:php:`f:format.date`).
    The mails use the date format of the TYPO3 installation
    (``SYS/ddmmyy``).

..  confval:: plugin.tx_golbeventsbooking.settings.timeFormat
    :name: constant-timeFormat
    :type: string
    :default: ``%H:%M``

    Format of the times in the plugin templates. The mails use
    ``SYS/hhmm``.

..  confval:: plugin.tx_golbeventsbooking.settings.privacyPageUid
    :name: constant-privacyPageUid
    :type: integer

    Uid of the privacy policy page linked from the privacy checkbox of
    the form. Without it the checkbox has no link.

..  confval:: plugin.tx_golbeventsbooking.settings.minimumFillTime
    :name: constant-minimumFillTime
    :type: integer
    :default: ``3``

    Seconds a visitor has to spend on the form at least; faster
    submissions are rejected as spam. ``0`` disables the check. A hidden
    honeypot field is checked as well.

..  confval:: plugin.tx_golbeventsbooking.settings.occurrenceLimit
    :name: constant-occurrenceLimit
    :type: integer
    :default: ``12``

    Number of upcoming dates of a recurring event offered in the form.
    The Booking form plugin can override it per content element.

..  _configuration-flexform:

Plugin settings
===============

Booking form
    :guilabel:`Event`: the event page whose dates are offered. Leave it
    empty on event pages to use the current page.
    :guilabel:`Number of dates offered`: overrides the constant
    ``occurrenceLimit``.

My bookings
    No settings.

..  _configuration-extension:

Extension configuration
=======================

Set in :guilabel:`Admin Tools > Settings > Extension Configuration >
golb_events_booking`.

..  confval:: doubleOptIn
    :name: extconf-doubleOptIn
    :type: boolean
    :default: ``1``

    Bookings of visitors who are not logged in have to be confirmed
    through a link sent by email before they count as confirmed. Logged
    in frontend users are confirmed at once. Pending bookings reserve
    their seats.

..  confval:: pendingHours
    :name: extconf-pendingHours
    :type: integer
    :default: ``48``

    Hours a pending booking reserves its seats. The cleanup command
    cancels pending bookings afterwards.

..  confval:: retentionDays
    :name: extconf-retentionDays
    :type: integer
    :default: ``0``

    The cleanup command anonymizes the personal data of bookings this
    many days after the booked date. ``0`` keeps the data, see
    :ref:`privacy`.

..  _configuration-routing:

Routing
=======

:file:`Configuration/Routing/RouteEnhancers.yaml` contains a route
enhancer for the links of the booking mails. Import it into the site
configuration and, if the form is not rendered on every event page,
restrict it to the pages with the plugin:

..  code-block:: yaml
    :caption: config/sites/main/config.yaml

    imports:
      - { resource: 'EXT:golb_events_booking/Configuration/Routing/RouteEnhancers.yaml' }

    routeEnhancers:
      GolbEventsBookingForm:
        limitToPages: [12]

Resulting URLs for the form on ``/events/conference``:

..  code-block:: text

    /events/conference/booking/<token>            success page of a booking
    /events/conference/booking/<token>/confirm    confirmation link
    /events/conference/booking/<token>/cancel     cancellation link

The form itself and its submission use plugin arguments without routes.

..  _configuration-mails:

Mails
=====

The mails are FluidEmail templates in
:file:`EXT:golb_events_booking/Resources/Private/Templates/Email/Booking/`
(HTML and text version each) with a shared partial
:file:`Partials/Email/Booking/Facts`. The paths are registered at index
``750`` of ``$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths']``
and ``partialRootPaths``; register your own paths with a higher index to
override single templates:

..  code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][800] = 'EXT:my_site/Resources/Private/Templates/Email/';

..  list-table::
    :header-rows: 1

    *   -   Template
        -   Recipient, occasion
    *   -   ``Booking/OptIn``
        -   Guest, after booking: confirmation link and deadline
    *   -   ``Booking/Confirmed``
        -   Participant, when the booking is confirmed (with the event as
            calendar file)
    *   -   ``Booking/Waiting``
        -   Participant, when the booking went onto the waiting list
    *   -   ``Booking/Promoted``
        -   Participant, when the booking moved up from the waiting list
    *   -   ``Booking/Cancelled``
        -   Participant, when the booking was cancelled or expired
    *   -   ``Booking/Reminder``
        -   Participant, by the reminder command
    *   -   ``Booking/OrganizerBooked``
        -   Organizer, for every confirmed or waiting booking
    *   -   ``Booking/OrganizerCancelled``
        -   Organizer, for every cancellation
    *   -   ``Booking/OrganizerDatesRemoved``
        -   Organizer, when dates with bookings were removed

Subjects and texts are translated through
:file:`Resources/Private/Language/locallang.xlf` (keys ``mail.*``), the
sender is the TYPO3 default mail address. Variables of the templates:
``booking``, ``event``, ``occurrence``, ``settings`` (the booking
settings of the event), ``eventUrl``, ``confirmUrl``, ``cancelUrl``,
``successUrl``, ``dateFormat`` and ``timeFormat``.

..  _configuration-tsconfig:

TSconfig
========

The extension ships :file:`Configuration/page.tsconfig` with the content
element wizard entries of the plugins; it is loaded automatically.
