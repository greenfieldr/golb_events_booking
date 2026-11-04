<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Service;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEventsBooking\Configuration\ExtensionSettings;
use Greenfieldr\GolbEventsBooking\Constants;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingRepository;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingCancelledEvent;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingConfirmedEvent;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingCreatedEvent;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingPromotedEvent;
use Greenfieldr\GolbEventsBooking\Exception\BookingClosedException;
use Greenfieldr\GolbEventsBooking\Exception\FullyBookedException;
use Greenfieldr\GolbEventsBooking\Exception\InvalidTransitionException;
use Greenfieldr\GolbEventsBooking\Exception\TooManySeatsException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Every change of a booking's status goes through this service: creating a
 * booking (pending, confirmed or waiting), confirming, cancelling, moving
 * waiting bookings up and expiring pending ones. It persists the changes
 * and dispatches the PSR-14 events the mails and other extensions listen to.
 */
class BookingStateService
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly AvailabilityService $availabilityService,
        private readonly TokenService $tokenService,
        private readonly ExtensionSettings $extensionSettings,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Context $context,
    ) {}

    /**
     * Stores a new booking of the date. The participant data (names, email,
     * seats, note, frontend user, privacy acceptance) has to be set on the
     * booking; event, occurrence, start, status, token and timestamps are
     * set here. Guests get a pending booking when double opt-in is enabled,
     * a full date puts the booking on the waiting list.
     *
     * @throws BookingClosedException when the booking window is closed
     * @throws TooManySeatsException when more seats than allowed are requested
     * @throws FullyBookedException when the date is full and has no waiting list
     */
    public function book(Booking $booking, Occurrence $occurrence, BookingSettings $settings): Booking
    {
        $now = $this->now();
        $start = $occurrence->getStart();
        if (!$start instanceof \DateTimeImmutable || !$settings->isOpenAt($now, $start)) {
            throw new BookingClosedException();
        }
        if ($booking->getSeats() > $settings->maxSeats) {
            throw new TooManySeatsException();
        }
        $waiting = !$this->availabilityService->hasRoomFor($settings, $occurrence, $booking->getSeats());
        if ($waiting && !$settings->waitingList) {
            throw new FullyBookedException();
        }

        $event = $occurrence->getEvent();
        $booking->setEvent($event instanceof Event ? $event : null);
        $booking->setOccurrence($occurrence);
        $booking->setStart($start);
        $booking->setToken($this->tokenService->generate());
        $booking->setConfirmedAt(null);
        $booking->setCancelledAt(null);
        $booking->setPendingReason('');
        $booking->setPendingUntil(null);

        if ($waiting) {
            $booking->setStatus(Booking::STATUS_WAITING);
        } elseif ($booking->isGuest() && $this->extensionSettings->isDoubleOptIn()) {
            $booking->setStatus(Booking::STATUS_PENDING);
            $booking->setPendingReason(Booking::PENDING_REASON_OPTIN);
            $booking->setPendingUntil($now->modify('+' . $this->extensionSettings->getPendingHours() . ' hours'));
        } else {
            $booking->setStatus(Booking::STATUS_CONFIRMED);
            $booking->setConfirmedAt($now);
        }

        // Guard against two requests taking the last seats at the same time:
        // store, count again and step back to the waiting list (or give up)
        // if the capacity is exceeded now.
        $connection = $this->connectionPool->getConnectionForTable(Constants::TABLE_BOOKING);
        $connection->transactional(function () use ($booking, $occurrence, $settings): void {
            $this->bookingRepository->add($booking);
            $this->persistenceManager->persistAll();

            if ($booking->isBlockingSeats() && $settings->hasCapacityLimit()
                && $this->availabilityService->bookedSeats($occurrence) > $settings->capacity
            ) {
                if (!$settings->waitingList) {
                    $this->bookingRepository->remove($booking);
                    $this->persistenceManager->persistAll();
                    throw new FullyBookedException();
                }
                $booking->setStatus(Booking::STATUS_WAITING);
                $booking->setPendingReason('');
                $booking->setPendingUntil(null);
                $booking->setConfirmedAt(null);
                $this->bookingRepository->update($booking);
                $this->persistenceManager->persistAll();
            }
        });

        $this->eventDispatcher->dispatch(new AfterBookingCreatedEvent($booking, $settings));

        return $booking;
    }

    /**
     * Confirms a pending booking. Pending bookings block their seats, so no
     * capacity check is needed.
     *
     * @throws InvalidTransitionException when the booking is not pending
     */
    public function confirm(Booking $booking, BookingSettings $settings): void
    {
        if (!$booking->isPending()) {
            throw new InvalidTransitionException();
        }
        $booking->setStatus(Booking::STATUS_CONFIRMED);
        $booking->setPendingReason('');
        $booking->setPendingUntil(null);
        $booking->setConfirmedAt($this->now());
        $this->bookingRepository->update($booking);
        $this->persistenceManager->persistAll();

        $this->eventDispatcher->dispatch(new AfterBookingConfirmedEvent($booking, $settings));
    }

    /**
     * Cancels an active booking and moves waiting bookings up if seats
     * became free.
     *
     * @throws InvalidTransitionException when the booking is already cancelled
     */
    public function cancel(Booking $booking, BookingSettings $settings): void
    {
        if (!$booking->isActive()) {
            throw new InvalidTransitionException();
        }
        $freesSeats = $booking->isBlockingSeats();
        $booking->setStatus(Booking::STATUS_CANCELLED);
        $booking->setPendingReason('');
        $booking->setPendingUntil(null);
        $booking->setCancelledAt($this->now());
        $this->bookingRepository->update($booking);
        $this->persistenceManager->persistAll();

        $this->eventDispatcher->dispatch(new AfterBookingCancelledEvent($booking, $settings));

        $occurrence = $booking->getOccurrence();
        if ($freesSeats && $occurrence instanceof Occurrence) {
            $this->promoteWaiting($occurrence, $settings);
        }
    }

    /**
     * Confirms waiting bookings of the date in their order as long as their
     * seats fit into the free seats. Returns the promoted bookings.
     *
     * @return Booking[]
     */
    public function promoteWaiting(Occurrence $occurrence, BookingSettings $settings): array
    {
        if (!$settings->waitingList) {
            return [];
        }
        $promoted = [];
        while (($next = $this->bookingRepository->findFirstWaiting($occurrence)) instanceof Booking) {
            if (!$this->availabilityService->hasRoomFor($settings, $occurrence, $next->getSeats())) {
                break;
            }
            $next->setStatus(Booking::STATUS_CONFIRMED);
            $next->setConfirmedAt($this->now());
            $this->bookingRepository->update($next);
            $this->persistenceManager->persistAll();
            $this->eventDispatcher->dispatch(new AfterBookingPromotedEvent($next, $settings));
            $promoted[] = $next;
        }

        return $promoted;
    }

    /**
     * Cancels pending bookings whose deadline has passed. The settings of
     * the events are resolved by the given callable (event uid to settings).
     * Returns the cancelled bookings.
     *
     * @param callable(int): BookingSettings $settingsResolver
     * @return Booking[]
     */
    public function expirePending(callable $settingsResolver): array
    {
        $expired = [];
        foreach ($this->bookingRepository->findExpiredPending($this->now()) as $booking) {
            $this->cancel($booking, $settingsResolver((int)$booking->getEvent()?->getUid()));
            $expired[] = $booking;
        }

        return $expired;
    }

    /**
     * Removes the personal data of a booking but keeps the record for the
     * statistics (date, seats, status).
     */
    public function anonymize(Booking $booking): void
    {
        $booking->setSalutation('');
        $booking->setFirstName('');
        $booking->setLastName('');
        $booking->setEmail('');
        $booking->setPhone('');
        $booking->setCompany('');
        $booking->setNote('');
        $booking->setFrontendUser(0);
        $booking->setToken('');
        $booking->setAnonymized(true);
        $this->bookingRepository->update($booking);
        $this->persistenceManager->persistAll();
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->context->getPropertyFromAspect('date', 'full');

        return $now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable();
    }
}
