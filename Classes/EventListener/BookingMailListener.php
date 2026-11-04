<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\EventListener;

use Greenfieldr\GolbEventsBooking\Event\AfterBookedDatesRemovedEvent;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingCancelledEvent;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingConfirmedEvent;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingCreatedEvent;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingPromotedEvent;
use Greenfieldr\GolbEventsBooking\Mail\BookingMailer;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Sends the mails of the booking workflow in reaction to the state changes.
 * Sites that want other mails deregister these listeners or replace the
 * templates.
 */
final readonly class BookingMailListener
{
    public function __construct(private BookingMailer $mailer) {}

    /**
     * A new booking: the participant gets the opt-in request, the
     * confirmation or the waiting list notice; the organizer is told about
     * confirmed and waiting bookings (pending ones when they are confirmed).
     */
    #[AsEventListener(identifier: 'golb-events-booking/mail/created')]
    public function onCreated(AfterBookingCreatedEvent $event): void
    {
        $this->mailer->sendBookingResult($event->getBooking(), $event->getSettings());
        if (!$event->getBooking()->isPending()) {
            $this->mailer->sendOrganizerBooked($event->getBooking(), $event->getSettings());
        }
    }

    #[AsEventListener(identifier: 'golb-events-booking/mail/confirmed')]
    public function onConfirmed(AfterBookingConfirmedEvent $event): void
    {
        $this->mailer->sendConfirmed($event->getBooking(), $event->getSettings());
        $this->mailer->sendOrganizerBooked($event->getBooking(), $event->getSettings());
    }

    #[AsEventListener(identifier: 'golb-events-booking/mail/cancelled')]
    public function onCancelled(AfterBookingCancelledEvent $event): void
    {
        $this->mailer->sendCancelled($event->getBooking(), $event->getSettings());
        $this->mailer->sendOrganizerCancelled($event->getBooking(), $event->getSettings());
    }

    #[AsEventListener(identifier: 'golb-events-booking/mail/promoted')]
    public function onPromoted(AfterBookingPromotedEvent $event): void
    {
        $this->mailer->sendPromoted($event->getBooking(), $event->getSettings());
        $this->mailer->sendOrganizerBooked($event->getBooking(), $event->getSettings());
    }

    #[AsEventListener(identifier: 'golb-events-booking/mail/dates-removed')]
    public function onDatesRemoved(AfterBookedDatesRemovedEvent $event): void
    {
        $this->mailer->sendOrganizerDatesRemoved($event->getBookings(), $event->getSettings());
    }
}
