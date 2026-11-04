..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _installation-composer:

Composer installation
=====================

Require the extension in your project:

..  code-block:: bash

    composer require greenfieldr/golb_events_booking

The extension key is ``golb_events_booking``. The extension depends on
`Golb Events <https://github.com/greenfieldr/golb_events>`__ (14.1 or
later) and `Golb <https://github.com/greenfieldr/golb>`__, which Composer
installs along with it. TYPO3 picks the packages up automatically.

..  _installation-setup:

Setup
=====

#.  **Database:** Run :guilabel:`Admin Tools > Maintenance > Analyze
    Database Structure` to create the booking table and the additional
    page fields.

#.  **TypoScript:** Include the static TypoScript "Golb Events Booking"
    after "Golb" and "Golb Events" in your site package
    (:guilabel:`Web > Template > Includes`) or import it in your TypoScript
    setup:

    ..  code-block:: typoscript

        @import 'EXT:golb/Configuration/TypoScript/setup.typoscript'
        @import 'EXT:golb_events/Configuration/TypoScript/setup.typoscript'
        @import 'EXT:golb_events_booking/Configuration/TypoScript/setup.typoscript'

#.  **Storage folder:** Create a folder for the bookings (any folder of
    the page tree) and set its uid in the constant
    :typoscript:`plugin.tx_golbeventsbooking.persistence.storagePid`, see
    :ref:`configuration-constants`. Bookings are not stored on the event
    pages, so copying or deleting an event leaves them alone.

#.  **Booking form:** Render the form on the event pages. Either insert
    the TypoScript object :typoscript:`lib.golbEventsBooking.form` in the
    page template of the site for event pages, see
    :ref:`templating-detail`, or add the content element
    :guilabel:`Golb > Booking form` to the event page (or to any other
    page, selecting the event in the plugin).

#.  **Events:** Enable booking on the :guilabel:`Booking` tab of the event
    pages and set the capacity, see :ref:`editor-settings`.

#.  **Mails:** Make sure the mail settings of the installation are
    complete (:guilabel:`Admin Tools > Settings > Configure Installation-Wide
    Options > MAIL`), in particular ``defaultMailFromAddress`` and
    ``defaultMailFromName``: the booking mails are sent from this address.

#.  **Frontend users (optional):** Logged in frontend users book with
    their account; nothing has to be configured for that. Add the content
    element :guilabel:`Golb > My bookings` to a page for them to see and
    cancel their bookings. Login and registration are provided by other
    extensions.

#.  **Routing (optional):** Import the shipped route enhancer
    configuration into the site configuration for speaking URLs of the
    confirmation and cancellation links, see :ref:`configuration-routing`:

    ..  code-block:: yaml
        :caption: config/sites/main/config.yaml

        imports:
          - { resource: 'EXT:golb_events_booking/Configuration/Routing/RouteEnhancers.yaml' }

..  _installation-scheduler:

Scheduler
=========

Two console commands keep the bookings tidy; run them daily, for example
through the :guilabel:`Execute console commands` task of the scheduler or
a cron job:

..  code-block:: bash

    vendor/bin/typo3 golbeventsbooking:cleanup
    vendor/bin/typo3 golbeventsbooking:reminders:send --days=1

``golbeventsbooking:cleanup``
    Cancels pending bookings whose confirmation deadline has passed (the
    seats go to the waiting list) and anonymizes the personal data of
    bookings whose date is older than the retention period, see
    :ref:`configuration-extension` and :ref:`privacy`.

``golbeventsbooking:reminders:send``
    Mails a reminder to the participants of confirmed bookings whose date
    starts within the given number of days (``--days``, default 1). Every
    booking is reminded once.
