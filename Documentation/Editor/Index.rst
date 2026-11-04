..  include:: /Includes.rst.txt

..  _editor:

=================
Managing bookings
=================

..  _editor-settings:

Booking settings of an event
============================

Every event page (page type "Event" of Golb Events) has a
:guilabel:`Booking` tab in its page properties:

..  list-table::
    :header-rows: 1

    *   -   Field
        -   Meaning
    *   -   Booking enabled
        -   Visitors can book the dates of this event. Without it the
            booking form is not rendered.
    *   -   Capacity per date
        -   Number of seats available for every date of the event; ``0``
            means unlimited seats. Pending (not yet confirmed) and
            confirmed bookings take seats.
    *   -   Seats per booking
        -   Maximum number of seats one booking may take.
    *   -   Waiting list
        -   When a date is fully booked, further bookings go onto a
            waiting list. As soon as seats become free (a cancellation or
            an expired reservation), the first waiting booking that fits
            moves up and its participant is informed by email.
    *   -   Booking opens at
        -   Bookings are accepted from this point in time; leave it empty
            to accept them at once.
    *   -   Booking closes (hours before start)
        -   Bookings for a date close this many hours before its start;
            ``0`` closes them at the start. Past dates are never offered.
    *   -   Notification email
        -   Receives a notification about every booking, cancellation and
            removed date. Leave it empty to notify the email address of
            the organizer record of the event; without both no
            notifications are sent.

The settings apply to every date of a recurring event alike.

..  _editor-bookings:

Bookings
========

Bookings are records in the storage folder chosen at installation
(:ref:`installation-setup`). Open the folder with :guilabel:`Web > List`
to see, filter, edit and export them. A booking carries the booked
event and date, the number of seats, the participant data, the status
and the bookkeeping dates (privacy policy accepted, confirmed, cancelled,
reminded).

Status
    *Pending*: a guest booking that has to be confirmed through the
    link sent by email. The seats are reserved until the deadline shown
    in :guilabel:`Pending until`; afterwards the cleanup command cancels
    the booking. *Confirmed*: the booking counts. *Waiting list*: the
    date was full; the booking moves up automatically when seats become
    free. *Cancelled*: by the participant, by the expired deadline or by
    you.

Editing
    You may change the status, the seats or the participant data in the
    backend, for example to confirm a booking by phone or to cancel a
    no-show. Changes made in the backend do not send mails and do not
    move waiting bookings up.

..  _editor-removed-dates:

Removed dates
=============

When you change the start of an event, shorten its recurrence or delete
it, dates with active bookings may disappear. Those bookings are not
deleted: they are flagged :guilabel:`Booked date was removed`, keep the
booked start, their status and their seats, and the organizer is notified
by email. Participants are **not** informed automatically, so a typo in
a date that you correct a minute later causes no mails.

To handle such a booking, open it and either assign another date of the
event in the :guilabel:`Date` field (the select offers the dates of the
event), uncheck the flag and inform the participant yourself, or cancel
the booking.

..  _editor-plugins:

Plugins
=======

Booking form
    Shows the booking form for the current event page, or for the event
    selected in the :guilabel:`Event` field of the plugin when the form is
    placed on another page. The number of dates offered for a recurring
    event can be limited per plugin. Most sites do not need the content
    element: the form is usually rendered on every event page through
    TypoScript, see :ref:`templating-detail`.

My bookings
    Lists the active bookings of the logged in frontend user with a cancel
    button each. Visitors who are not logged in see a hint.
