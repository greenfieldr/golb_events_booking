<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Functional\Service;

use Greenfieldr\GolbEventsBooking\Configuration\ExtensionSettings;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\OccurrenceAvailability;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingRepository;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingSettingsRepository;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingCancelledEvent;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingConfirmedEvent;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingCreatedEvent;
use Greenfieldr\GolbEventsBooking\Event\AfterBookingPromotedEvent;
use Greenfieldr\GolbEventsBooking\Exception\BookingClosedException;
use Greenfieldr\GolbEventsBooking\Exception\FullyBookedException;
use Greenfieldr\GolbEventsBooking\Exception\InvalidTransitionException;
use Greenfieldr\GolbEventsBooking\Exception\TooManySeatsException;
use Greenfieldr\GolbEventsBooking\Service\AvailabilityService;
use Greenfieldr\GolbEventsBooking\Service\BookingStateService;
use Greenfieldr\GolbEventsBooking\Service\TokenService;
use Greenfieldr\GolbEventsBooking\Tests\Functional\AbstractBookingTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;

#[CoversClass(BookingStateService::class)]
#[CoversClass(AvailabilityService::class)]
#[CoversClass(TokenService::class)]
#[CoversClass(ExtensionSettings::class)]
#[CoversClass(BookingRepository::class)]
#[CoversClass(BookingSettingsRepository::class)]
#[CoversClass(BookingSettings::class)]
#[CoversClass(OccurrenceAvailability::class)]
#[CoversClass(Booking::class)]
#[CoversClass(AfterBookingCreatedEvent::class)]
#[CoversClass(AfterBookingConfirmedEvent::class)]
#[CoversClass(AfterBookingCancelledEvent::class)]
#[CoversClass(AfterBookingPromotedEvent::class)]
final class BookingStateServiceTest extends AbstractBookingTestCase
{
    private BookingStateService $subject;

    private BookingRepository $repository;

    private AvailabilityService $availability;

    /**
     * @var list<object>
     */
    private array $dispatched = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(BookingStateService::class);
        $this->repository = $this->get(BookingRepository::class);
        $this->availability = $this->get(AvailabilityService::class);

        $container = $this->get('service_container');
        self::assertInstanceOf(Container::class, $container);
        $container->set('golb-events-booking-test-listener', function (object $event): void {
            $this->dispatched[] = $event;
        });
        $listenerProvider = $this->get(ListenerProvider::class);
        foreach ([AfterBookingCreatedEvent::class, AfterBookingConfirmedEvent::class, AfterBookingCancelledEvent::class, AfterBookingPromotedEvent::class] as $eventClass) {
            $listenerProvider->addListener($eventClass, 'golb-events-booking-test-listener');
        }
    }

    /**
     * @return list<class-string>
     */
    private function dispatchedClasses(): array
    {
        return array_map(static fn(object $event): string => $event::class, $this->dispatched);
    }

    #[Test]
    public function settingsAreReadFromTheEventPage(): void
    {
        $settings = $this->settingsOf(3);

        self::assertTrue($settings->enabled);
        self::assertSame(3, $settings->capacity);
        self::assertSame(2, $settings->maxSeats);
        self::assertTrue($settings->waitingList);
        self::assertSame('office@example.org', $settings->notificationEmail);
        self::assertFalse($this->settingsOf(7)->enabled);
        self::assertFalse($this->settingsOf(999)->enabled, 'unknown page');
        self::assertFalse($this->settingsOf(5)->enabled, 'the storage folder is no event');
    }

    #[Test]
    public function frontendUserBookingIsConfirmedAtOnce(): void
    {
        $booking = $this->subject->book($this->newBooking(frontendUser: 7), $this->occurrence(1), $this->settingsOf(3));

        self::assertTrue($booking->isConfirmed());
        self::assertSame('2026-01-01 12:00:00', $booking->getConfirmedAt()?->format('Y-m-d H:i:s'));
        self::assertSame(3, $booking->getEvent()?->getUid());
        self::assertSame(1, $booking->getOccurrence()?->getUid());
        self::assertSame($this->occurrence(1)->getStart()?->getTimestamp(), $booking->getStart()?->getTimestamp());
        self::assertSame(TokenService::LENGTH, strlen($booking->getToken()));
        self::assertSame([AfterBookingCreatedEvent::class], $this->dispatchedClasses());

        $rows = $this->bookingRows();
        self::assertCount(1, $rows);
        self::assertSame(self::STORAGE_PID, self::toInt($rows[0]['pid']));
        self::assertSame('confirmed', $rows[0]['status']);
        self::assertSame(7, self::toInt($rows[0]['frontend_user']));
    }

    #[Test]
    public function guestBookingIsPendingUntilConfirmedWhenDoubleOptInIsEnabled(): void
    {
        $booking = $this->subject->book($this->newBooking(), $this->occurrence(1), $this->settingsOf(3));

        self::assertTrue($booking->isPending());
        self::assertSame(Booking::PENDING_REASON_OPTIN, $booking->getPendingReason());
        self::assertSame('2026-01-03 12:00:00', $booking->getPendingUntil()?->format('Y-m-d H:i:s'));
        self::assertNull($booking->getConfirmedAt());
        self::assertSame(1, $this->availability->bookedSeats($this->occurrence(1)), 'pending bookings block seats');

        $this->freezeTime('2026-01-02 08:00:00');
        $this->subject->confirm($booking, $this->settingsOf(3));

        self::assertTrue($booking->isConfirmed());
        self::assertSame('', $booking->getPendingReason());
        self::assertNull($booking->getPendingUntil());
        self::assertSame('2026-01-02 08:00:00', $booking->getConfirmedAt()?->format('Y-m-d H:i:s'));
        self::assertSame([AfterBookingCreatedEvent::class, AfterBookingConfirmedEvent::class], $this->dispatchedClasses());
        self::assertSame('confirmed', $this->bookingRows()[0]['status']);
    }

    #[Test]
    public function guestBookingIsConfirmedAtOnceWithoutDoubleOptIn(): void
    {
        $this->configureExtension(['doubleOptIn' => '0']);

        $booking = $this->subject->book($this->newBooking(), $this->occurrence(1), $this->settingsOf(3));

        self::assertTrue($booking->isConfirmed());
    }

    #[Test]
    public function pendingHoursSettingSetsTheDeadline(): void
    {
        $this->configureExtension(['doubleOptIn' => '1', 'pendingHours' => '6']);

        $booking = $this->subject->book($this->newBooking(), $this->occurrence(1), $this->settingsOf(3));

        self::assertSame('2026-01-01 18:00:00', $booking->getPendingUntil()?->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function confirmingANonPendingBookingIsRejected(): void
    {
        $booking = $this->subject->book($this->newBooking(frontendUser: 7), $this->occurrence(1), $this->settingsOf(3));

        $this->expectException(InvalidTransitionException::class);
        $this->subject->confirm($booking, $this->settingsOf(3));
    }

    #[Test]
    public function fullDateWithWaitingListPutsTheBookingOnTheWaitingList(): void
    {
        $settings = $this->settingsOf(3);
        $first = $this->subject->book($this->newBooking('a@example.org', 2, 1), $this->occurrence(1), $settings);
        $second = $this->subject->book($this->newBooking('b@example.org', 2, 2), $this->occurrence(1), $settings);
        $third = $this->subject->book($this->newBooking('c@example.org', 1, 3), $this->occurrence(1), $settings);

        self::assertTrue($first->isConfirmed());
        self::assertTrue($second->isWaiting(), 'two seats do not fit into the remaining one');
        self::assertTrue($third->isConfirmed(), 'one seat still fits');

        $availability = $this->availability->availability($settings, $this->occurrence(1), new \DateTimeImmutable('2026-01-01 12:00:00'));
        self::assertSame(3, $availability->bookedSeats);
        self::assertSame(0, $availability->freeSeats);
        self::assertSame(1, $availability->waitingCount);
        self::assertTrue($availability->isFull());
        self::assertTrue($availability->open);
        self::assertTrue($availability->isBookable(), 'the waiting list takes bookings');
        self::assertTrue($availability->isWaitingListOnly());
        self::assertFalse($availability->hasRoomFor(1));
    }

    #[Test]
    public function cancellingFreesSeatsAndPromotesWaitingBookingsInOrder(): void
    {
        $settings = $this->settingsOf(3);
        $first = $this->subject->book($this->newBooking('a@example.org', 2, 1), $this->occurrence(1), $settings);
        $second = $this->subject->book($this->newBooking('b@example.org', 2, 2), $this->occurrence(1), $settings);
        $third = $this->subject->book($this->newBooking('c@example.org', 2, 3), $this->occurrence(1), $settings);
        $fourth = $this->subject->book($this->newBooking('d@example.org', 1, 4), $this->occurrence(1), $settings);
        self::assertTrue($second->isWaiting());
        self::assertTrue($third->isWaiting());
        self::assertTrue($fourth->isConfirmed());
        $this->dispatched = [];

        $this->freezeTime('2026-01-02 12:00:00');
        $this->subject->cancel($first, $settings);

        self::assertTrue($first->isCancelled());
        self::assertSame('2026-01-02 12:00:00', $first->getCancelledAt()?->format('Y-m-d H:i:s'));
        self::assertTrue($second->isConfirmed(), 'the first waiting booking moved up');
        self::assertSame('2026-01-02 12:00:00', $second->getConfirmedAt()?->format('Y-m-d H:i:s'));
        self::assertTrue($third->isWaiting(), 'no room for the second one');
        self::assertSame(3, $this->availability->bookedSeats($this->occurrence(1)));
        self::assertSame([AfterBookingCancelledEvent::class, AfterBookingPromotedEvent::class], $this->dispatchedClasses());

        $this->expectException(InvalidTransitionException::class);
        $this->subject->cancel($first, $settings);
    }

    #[Test]
    public function cancellingAWaitingBookingPromotesNobody(): void
    {
        $settings = $this->settingsOf(3);
        $this->subject->book($this->newBooking('a@example.org', 2, 1), $this->occurrence(1), $settings);
        $waiting = $this->subject->book($this->newBooking('b@example.org', 2, 2), $this->occurrence(1), $settings);
        $other = $this->subject->book($this->newBooking('c@example.org', 2, 3), $this->occurrence(1), $settings);
        $this->dispatched = [];

        $this->subject->cancel($waiting, $settings);

        self::assertTrue($other->isWaiting());
        self::assertSame([AfterBookingCancelledEvent::class], $this->dispatchedClasses());
    }

    #[Test]
    public function fullDateWithoutWaitingListRejectsTheBooking(): void
    {
        $settings = $this->settingsOf(4);
        $this->subject->book($this->newBooking('a@example.org', 1, 1), $this->occurrence(2), $settings);

        $this->expectException(FullyBookedException::class);
        $this->subject->book($this->newBooking('b@example.org', 1, 2), $this->occurrence(2), $settings);
    }

    #[Test]
    public function tooManySeatsAreRejected(): void
    {
        $this->expectException(TooManySeatsException::class);
        $this->subject->book($this->newBooking('a@example.org', 3), $this->occurrence(1), $this->settingsOf(3));
    }

    #[Test]
    public function disabledEventRejectsBookings(): void
    {
        $this->expectException(BookingClosedException::class);
        $this->subject->book($this->newBooking(), $this->occurrence(5), $this->settingsOf(7));
    }

    #[Test]
    public function bookingClosesTheConfiguredHoursBeforeTheStart(): void
    {
        // Lecture on 2099-05-05 10:00 UTC closes 24 hours before
        $this->freezeTime('2099-05-04 09:59:59');
        $booking = $this->subject->book($this->newBooking('a@example.org', 1, 1), $this->occurrence(2), $this->settingsOf(4));
        self::assertTrue($booking->isConfirmed());
        $this->subject->cancel($booking, $this->settingsOf(4));

        $this->freezeTime('2099-05-04 10:00:00');
        $this->expectException(BookingClosedException::class);
        $this->subject->book($this->newBooking('b@example.org', 1, 2), $this->occurrence(2), $this->settingsOf(4));
    }

    #[Test]
    public function bookingOpensAtTheConfiguredTime(): void
    {
        $settings = $this->settingsOf(6);
        self::assertSame('2026-06-01 00:00:00', $settings->opensAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));

        $this->freezeTime('2026-06-01 00:00:00');
        $booking = $this->subject->book($this->newBooking('a@example.org', 5, 1), $this->occurrence(3), $settings);
        self::assertTrue($booking->isConfirmed());
        self::assertNull($this->availability->availability($settings, $this->occurrence(3), new \DateTimeImmutable('2026-06-01'))->freeSeats, 'unlimited');

        $this->freezeTime('2026-05-31 23:59:59');
        $this->expectException(BookingClosedException::class);
        $this->subject->book($this->newBooking('b@example.org', 1, 2), $this->occurrence(4), $settings);
    }

    #[Test]
    public function expiredPendingBookingsAreCancelledAndWaitingOnesMoveUp(): void
    {
        $settings = $this->settingsOf(3);
        $pending = $this->subject->book($this->newBooking('a@example.org', 2), $this->occurrence(1), $settings);
        $waiting = $this->subject->book($this->newBooking('b@example.org', 2, 2), $this->occurrence(1), $settings);
        self::assertTrue($pending->isPending());
        self::assertTrue($waiting->isWaiting());

        $this->freezeTime('2026-01-03 11:59:59');
        self::assertSame([], $this->subject->expirePending($this->settingsOf(...)), 'deadline not reached');

        $this->freezeTime('2026-01-03 12:00:01');
        $expired = $this->subject->expirePending($this->settingsOf(...));

        self::assertSame([$pending->getUid()], array_map(static fn(Booking $booking): ?int => $booking->getUid(), $expired));
        self::assertTrue($pending->isCancelled());
        self::assertTrue($waiting->isConfirmed());
    }

    #[Test]
    public function anonymizingRemovesThePersonalDataButKeepsTheBooking(): void
    {
        $booking = $this->subject->book($this->newBooking(frontendUser: 7), $this->occurrence(1), $this->settingsOf(3));
        $booking->setPhone('+44 1');
        $booking->setCompany('Analytical Engines');
        $booking->setNote('Vegetarian');

        $this->subject->anonymize($booking);

        $row = $this->bookingRows()[0];
        self::assertSame('', $row['first_name']);
        self::assertSame('', $row['last_name']);
        self::assertSame('', $row['email']);
        self::assertSame('', $row['phone']);
        self::assertSame('', $row['company']);
        self::assertSame('', $row['note']);
        self::assertSame('', $row['token']);
        self::assertSame(0, self::toInt($row['frontend_user']));
        self::assertSame(1, self::toInt($row['anonymized']));
        self::assertSame('confirmed', $row['status']);
        self::assertSame(1, self::toInt($row['seats']));
        self::assertNull($this->repository->findOneByToken(''));
    }

    #[Test]
    public function repositoryFindsBookingsByTokenOccurrenceAndFrontendUser(): void
    {
        $settings = $this->settingsOf(3);
        // Two seats, two seats (waiting, one seat left), one seat (fits); the third is cancelled again
        $first = $this->subject->book($this->newBooking('a@example.org', 2, 7), $this->occurrence(1), $settings);
        $second = $this->subject->book($this->newBooking('b@example.org', 2, 7), $this->occurrence(1), $settings);
        $third = $this->subject->book($this->newBooking('c@example.org', 1, 8), $this->occurrence(1), $settings);
        self::assertTrue($second->isWaiting());
        $this->subject->cancel($third, $settings);
        self::assertTrue($second->isWaiting(), 'one free seat is not enough for two');

        self::assertSame($first->getUid(), $this->repository->findOneByToken($first->getToken())?->getUid());
        self::assertNull($this->repository->findOneByToken('unknown'));
        self::assertTrue($this->get(TokenService::class)->matches($first, $first->getToken()));
        self::assertFalse($this->get(TokenService::class)->matches($first, $second->getToken()));

        $active = $this->repository->findByOccurrence($this->occurrence(1));
        self::assertSame([$first->getUid(), $second->getUid()], array_map(static fn(Booking $booking): ?int => $booking->getUid(), $active));
        $waiting = $this->repository->findByOccurrence($this->occurrence(1), [Booking::STATUS_WAITING]);
        self::assertSame([$second->getUid()], array_map(static fn(Booking $booking): ?int => $booking->getUid(), $waiting));
        self::assertSame($second->getUid(), $this->repository->findFirstWaiting($this->occurrence(1))?->getUid());
        self::assertNull($this->repository->findFirstWaiting($this->occurrence(2)));

        $ofUser = $this->repository->findActiveByFrontendUser(7);
        self::assertCount(2, $ofUser);
        self::assertSame([], $this->repository->findActiveByFrontendUser(8), 'cancelled bookings are not active');
        self::assertSame([], $this->repository->findActiveByFrontendUser(0), 'guests have no user');
    }
}
