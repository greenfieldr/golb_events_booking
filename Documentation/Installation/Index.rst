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
installs along with it.

..  _installation-setup:

Setup
=====

#.  **Database:** Run :guilabel:`Admin Tools > Maintenance > Analyze
    Database Structure` to create the booking table and the additional
    page fields.

#.  **TypoScript:** Include the static TypoScript "Golb Events Booking"
    after "Golb" and "Golb Events".

#.  **Storage folder:** Create a folder for the bookings and set its uid
    in the constant :typoscript:`plugin.tx_golbeventsbooking.persistence.storagePid`.

#.  **Events:** Enable booking on the :guilabel:`Booking` tab of the event
    pages.
