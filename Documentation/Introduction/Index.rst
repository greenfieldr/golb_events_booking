..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

..  _what-it-does:

What does it do?
================

Golb Events Booking extends `Golb Events
<https://github.com/greenfieldr/golb_events>`__ with bookings of event
dates:

*   **Booking settings on the event page:** capacity per date, seats per
    booking, booking window, waiting list and a notification address.
*   **A booking form** on the event page. Visitors pick a date of a
    recurring event, enter their contact data and book. Logged in frontend
    users book with their account; guests confirm their booking through a
    link sent by email.
*   **Bookings as records** in a storage folder, editable in the backend:
    pending, confirmed, waiting or cancelled.
*   **Mails** to participants and organizers, reminders and a cleanup of
    expired and old bookings through console commands.
*   **"My bookings"** for logged in frontend users.

Registration and login of frontend users are out of scope.

..  _compatibility:

Compatibility
=============

..  list-table::
    :header-rows: 1

    *   -   Golb Events Booking version
        -   Golb Events version
        -   Golb version
        -   TYPO3 version
        -   PHP version
    *   -   14.x
        -   14.1 and later
        -   14.x
        -   14.x
        -   8.2 - 8.5

The major version follows the supported TYPO3 major version.
