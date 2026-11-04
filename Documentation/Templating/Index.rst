..  include:: /Includes.rst.txt

..  _templating:

==========
Templating
==========

..  _templating-templates:

Templates
=========

The default templates render the complete booking form with the dates
of the event, the result pages of a booking (success, confirmation,
cancellation) and the list of a user's bookings. The markup uses BEM
style classes (``golbbooking-form``, ``golbbooking-dates__item``,
``golbbooking-notice--pending``, ...) on top of Golb's and is styled by a
plain stylesheet without framework or colours, see :ref:`templating-css`.
The layout comes from Golb, the date partial from Golb Events.

..  code-block:: text

    EXT:golb/Resources/Private/Layouts/Default.html     Adds the stylesheets and wraps the output in <div class="golb">
    EXT:golb_events/Resources/Private/Partials/Event/Date.html   Date and time of an occurrence

    EXT:golb_events_booking/Resources/Private/
    ├── Partials/Booking/
    │   ├── Dates.html               The dates of the event with free seats (radio list or single date)
    │   ├── Field.html               A text input with label and errors
    │   ├── Errors.html              Validation errors of one field
    │   └── Summary.html             The facts of a booking (event, date, seats, participant, status)
    └── Templates/
        ├── Booking/
        │   ├── Form.html            The booking form
        │   ├── Success.html         After a booking: confirmed, pending or waiting
        │   ├── Confirm.html         Target of the confirmation link
        │   ├── Cancel.html          Target of the cancellation link (asks, then cancels)
        │   ├── Unavailable.html     Rendered when the page is no event or booking is disabled (empty)
        │   └── NotFound.html        Unknown token
        └── MyBookings/List.html     The "My bookings" plugin

    EXT:golb_events_booking/Resources/Public/Css/golb_events_booking.css   Default stylesheet

To change the markup copy the files you need to your site package and
register the path as described in :ref:`configuration-typoscript`; a file
found in your path replaces the default file of the same name. The mail
templates are described in :ref:`configuration-mails`.

..  _templating-form:

The booking form
================

Variables of :file:`Booking/Form.html`:

..  list-table::
    :header-rows: 1

    *   -   Variable
        -   Content
    *   -   ``event``
        -   The :php:`Event` of Golb Events
    *   -   ``availabilities``
        -   :php:`OccurrenceAvailability[]`: the upcoming dates with
            ``occurrence``, ``bookedSeats``, ``freeSeats`` (null when
            unlimited), ``waitingCount``, ``open``, ``waitingList`` and
            the predicates ``full``, ``bookable``, ``waitingListOnly``,
            ``unlimited``
    *   -   ``bookable``
        -   Whether any date can be booked right now
    *   -   ``bookingRequest``
        -   The form object (:php:`BookingRequest`), prefilled for logged
            in users and with the entered data after a failed validation
    *   -   ``salutationOptions``
        -   Options of the salutation select
    *   -   ``frontendUser``
        -   The fe_users row of the logged in user, empty for guests
    *   -   ``settings``
        -   The plugin settings; ``settings.booking`` holds the
            :php:`BookingSettings` of the event (``capacity``,
            ``maxSeats``, ``waitingList``, ...)

The form posts to the ``create`` action with the object name
``bookingRequest``. Keep the hidden fields ``startedAt`` (minimum fill
time) and ``website`` (honeypot) when you write your own template. The
result templates receive ``booking``, ``event``, ``occurrence`` and
``settings``; :file:`Confirm.html` and :file:`Cancel.html` also receive
``outcome``.

..  _templating-detail:

The form on event pages
=======================

The TypoScript object :typoscript:`lib.golbEventsBooking.form` renders
the booking form of the current page. Insert it into the page template
of your site for event pages, for example next to the detail rendering
of Golb Events:

..  code-block:: typoscript

    [page["doktype"] == 42]
    page.10.variables.booking < lib.golbEventsBooking.form
    [END]

..  code-block:: html

    <f:cObject typoscriptObjectPath="lib.golbEvents.event" data="{data}"/>
    <f:cObject typoscriptObjectPath="lib.golbEventsBooking.form"/>

The object renders as USER_INT (the free seats change with every
booking) and outputs nothing for pages that are no events or whose
booking is disabled. Golb Events' detail template shows the event's
:guilabel:`Link` field as registration link; leave the field empty for
events booked through this extension or point it to the form.

..  _templating-css:

Stylesheet
==========

:file:`Resources/Public/Css/golb_events_booking.css` builds on Golb's
custom properties (``--golb-space``, ``--golb-border``, ``--golb-surface``,
``--golb-radius``, ...) declared on ``.golb``, so adjusting them in your
stylesheet changes all three extensions at once. Status colours are
plain ``rgb`` values on the ``golbbooking-notice--*`` and
``golbbooking-status--*`` classes. Disable the file with the constant
:typoscript:`plugin.tx_golbeventsbooking.settings.includeCss = 0` or
remove it from :typoscript:`plugin.tx_golbeventsbooking.settings.css`.
