..  include:: /Includes.rst.txt

..  _developer:

================
Developer corner
================

..  _developer-model:

Model
=====

:php:`\Greenfieldr\GolbEventsBooking\Domain\Model\Booking`
    A booking of one occurrence (table
    ``tx_golbeventsbooking_domain_model_booking``): ``event`` and
    ``occurrence`` of Golb Events, the redundant ``start`` of the booked
    date, ``status`` (``pending``, ``confirmed``, ``waiting``,
    ``cancelled``), ``pendingReason`` and ``pendingUntil``, the
    ``dateRemoved`` flag, ``seats``, ``frontendUser`` (uid, 0 for guests),
    the participant data, ``privacyAcceptedAt``, ``token`` and the
    bookkeeping dates. Predicates: ``isActive()`` (not cancelled),
    ``isBlockingSeats()`` (pending or confirmed), ``isGuest()``,
    ``isPendingExpired()``.

:php:`\Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings`
    The booking settings of an event page, read from the page row by
    :php:`BookingSettingsRepository::findByEventUid()`:
    ``enabled``, ``capacity``, ``maxSeats``, ``waitingList``, ``opensAt``,
    ``closesHoursBefore``, ``notificationEmail``;
    ``isOpenAt($now, $start)`` and ``closesAt($start)``.

:php:`\Greenfieldr\GolbEventsBooking\Domain\Model\Dto\OccurrenceAvailability`
    The booking situation of one date, built by
    :php:`AvailabilityService::availability()`.

..  _developer-services:

Services
========

:php:`\Greenfieldr\GolbEventsBooking\Service\BookingStateService`
    Every status change goes through this service:
    ``book(Booking, Occurrence, BookingSettings)`` stores a new booking
    (pending, confirmed or waiting; throws ``BookingClosedException``,
    ``TooManySeatsException`` or ``FullyBookedException``),
    ``confirm()``, ``cancel()`` (moves waiting bookings up),
    ``promoteWaiting()``, ``expirePending()`` and ``anonymize()``. The
    participant data of a new booking is set by the caller, the service
    sets event, occurrence, start, status, token and timestamps. The
    last seats are guarded by a transaction with a recount.

:php:`\Greenfieldr\GolbEventsBooking\Service\AvailabilityService`
    Counts seats from the database: ``bookedSeats()``,
    ``waitingCount()``, ``hasRoomFor()``, ``availability()``.

:php:`\Greenfieldr\GolbEventsBooking\Service\BookingLinkService`
    Absolute URLs of the success, confirmation and cancellation pages and
    the event page, built with the site router.

:php:`\Greenfieldr\GolbEventsBooking\Mail\BookingMailer`
    Sends the mails, see :ref:`configuration-mails`.

:php:`\Greenfieldr\GolbEventsBooking\Domain\Repository\BookingRepository`
    ``findByOccurrence()``, ``findFirstWaiting()``, ``findOneByToken()``,
    ``findActiveByFrontendUser()``, ``findExpiredPending()``,
    ``findDueForReminder()``, ``findForAnonymization()``. The lookups
    ignore the storage page; new bookings are stored in the folder of the
    plugin settings.

..  _developer-events:

PSR-14 events
=============

:php:`\Greenfieldr\GolbEventsBooking\Event\ValidateBookingEvent`
    Dispatched while the form is validated, after the built-in checks.
    Add errors with ``addError($message, $property)`` for a captcha, a
    block list or any other rule; every error is shown with the form.

:php:`AfterBookingCreatedEvent`, :php:`AfterBookingConfirmedEvent`,
:php:`AfterBookingCancelledEvent`, :php:`AfterBookingPromotedEvent`
    Dispatched after the respective status change with the booking and
    the settings of its event. The mails are sent by a listener on these
    events (:php:`EventListener\BookingMailListener`); deregister it in
    your :file:`Services.yaml` to send your own.

:php:`AfterBookedDatesRemovedEvent`
    Dispatched when dates with active bookings were removed from an
    event, with the affected bookings (flagged ``dateRemoved``, detached
    from their occurrence).

..  code-block:: php
    :caption: EXT:my_ext/Classes/EventListener/CaptchaCheck.php

    use Greenfieldr\GolbEventsBooking\Event\ValidateBookingEvent;
    use TYPO3\CMS\Core\Attribute\AsEventListener;

    #[AsEventListener]
    final class CaptchaCheck
    {
        public function __invoke(ValidateBookingEvent $event): void
        {
            $answer = $event->getRequest()->getParsedBody()['captcha'] ?? '';
            if ($answer !== 'expected') {
                $event->addError('Please solve the captcha.');
            }
        }
    }

..  _developer-how-it-works:

How bookings stay attached to their dates
=========================================

Golb Events keeps its occurrence records stable since version 14.1: a
date keeps its uid across saves of the event page, only dates that
vanished are deleted and new dates are inserted. Bookings reference the
occurrence and store the event uid and the start redundantly.
:php:`EventListener\OccurrencesSynchronizedListener` listens to Golb
Events' :php:`AfterOccurrencesSynchronizedEvent`: active bookings of
removed occurrences are detached (``occurrence`` = 0), flagged
``dateRemoved`` and reported through :php:`AfterBookedDatesRemovedEvent`.
Editors reassign them in the backend, see :ref:`editor-removed-dates`.

..  _developer-commands:

Commands
========

``golbeventsbooking:reminders:send [--days=1]``
    Reminder mails for confirmed bookings of dates starting within the
    given days, once per booking (``reminder_sent_at``).

``golbeventsbooking:cleanup``
    Cancels expired pending bookings and anonymizes bookings older than
    the retention period.

..  _developer-qa:

Development
===========

Clone the repository and install the development dependencies:

..  code-block:: bash

    composer install
    composer ci            # coding standards, PHPStan, Rector, Fractor, tests
    composer fix           # apply Rector, Fractor and coding standard fixes
    composer ci:coverage   # test suites with code coverage (PCOV or Xdebug)

The functional tests run against SQLite by default
(``typo3DatabaseDriver=pdo_sqlite``) and render the plugins through
frontend sub requests; mails are caught by a collecting transport. The
documentation can be rendered locally with the official Docker image:

..  code-block:: bash

    docker run --rm -it -v "$(pwd)":/project \
        ghcr.io/typo3-documentation/render-guides:latest --config=Documentation
