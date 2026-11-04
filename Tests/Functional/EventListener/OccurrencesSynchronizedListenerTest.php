<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Functional\EventListener;

use Greenfieldr\GolbEvents\Recurrence\OccurrenceSynchronizer;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Event\AfterBookedDatesRemovedEvent;
use Greenfieldr\GolbEventsBooking\EventListener\OccurrencesSynchronizedListener;
use Greenfieldr\GolbEventsBooking\Service\BookingStateService;
use Greenfieldr\GolbEventsBooking\Tests\Functional\AbstractBookingTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;

#[CoversClass(OccurrencesSynchronizedListener::class)]
#[CoversClass(AfterBookedDatesRemovedEvent::class)]
final class OccurrencesSynchronizedListenerTest extends AbstractBookingTestCase
{
    private BookingStateService $bookingService;

    private OccurrenceSynchronizer $synchronizer;

    /**
     * @var list<AfterBookedDatesRemovedEvent>
     */
    private array $dispatched = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookingService = $this->get(BookingStateService::class);
        $this->synchronizer = $this->get(OccurrenceSynchronizer::class);

        $container = $this->get('service_container');
        self::assertInstanceOf(Container::class, $container);
        $container->set('golb-events-booking-test-listener', function (AfterBookedDatesRemovedEvent $event): void {
            $this->dispatched[] = $event;
        });
        $this->get(ListenerProvider::class)->addListener(AfterBookedDatesRemovedEvent::class, 'golb-events-booking-test-listener');
    }

    #[Test]
    public function savingTheEventWithoutDateChangesLeavesTheBookingsAlone(): void
    {
        $booking = $this->bookingService->book($this->newBooking(frontendUser: 1), $this->occurrence(1), $this->settingsOf(3));

        $this->synchronizer->synchronizeEvent(3);

        $row = $this->bookingRows()[0];
        self::assertSame(1, self::toInt($row['occurrence']));
        self::assertSame(0, self::toInt($row['date_removed']));
        self::assertSame($booking->getUid(), self::toInt($row['uid']));
        self::assertSame([], $this->dispatched);
    }

    #[Test]
    public function movedDateDetachesAndFlagsTheActiveBookings(): void
    {
        $settings = $this->settingsOf(3);
        $confirmed = $this->bookingService->book($this->newBooking('a@example.org', 1, 1), $this->occurrence(1), $settings);
        $cancelled = $this->bookingService->book($this->newBooking('b@example.org', 1, 2), $this->occurrence(1), $settings);
        $this->bookingService->cancel($cancelled, $settings);
        $pending = $this->bookingService->book($this->newBooking('c@example.org', 1), $this->occurrence(1), $settings);
        $originalStart = $confirmed->getStart()?->getTimestamp();

        $this->getConnectionPool()->getConnectionForTable('pages')->update(
            'pages',
            ['tx_golbevents_start' => '2026-05-11 19:30:00', 'tx_golbevents_end' => '2026-05-11 21:00:00'],
            ['uid' => 3],
            ['uid' => Connection::PARAM_INT]
        );
        $this->synchronizer->synchronizeEvent(3);

        $rows = $this->bookingRows();
        self::assertSame([0, 1, 0], array_map(static fn(array $row): int => self::toInt($row['occurrence']), $rows), 'active bookings lost the occurrence, the cancelled one keeps it');
        self::assertSame([1, 0, 1], array_map(static fn(array $row): int => self::toInt($row['date_removed']), $rows));
        self::assertSame($originalStart, self::toInt($rows[0]['start']), 'the booked start is kept');
        self::assertSame(Booking::STATUS_CONFIRMED, $rows[0]['status']);
        self::assertSame(Booking::STATUS_PENDING, $rows[2]['status']);

        self::assertCount(1, $this->dispatched);
        $event = $this->dispatched[0];
        self::assertSame(3, $event->getEventUid());
        self::assertSame(3, $event->getSettings()->eventUid);
        self::assertSame('office@example.org', $event->getSettings()->notificationEmail);
        self::assertSame(
            [$confirmed->getUid(), $pending->getUid()],
            array_map(static fn(Booking $booking): ?int => $booking->getUid(), $event->getBookings())
        );
        foreach ($event->getBookings() as $booking) {
            self::assertTrue($booking->isDateRemoved());
            self::assertNull($booking->getOccurrence());
        }
    }

    #[Test]
    public function deletingTheEventPageDetachesTheBookings(): void
    {
        $this->bookingService->book($this->newBooking(frontendUser: 1), $this->occurrence(1), $this->settingsOf(3));

        $this->synchronizer->deleteOccurrencesOfEvent(3);

        $row = $this->bookingRows()[0];
        self::assertSame(0, self::toInt($row['occurrence']));
        self::assertSame(1, self::toInt($row['date_removed']));
        self::assertCount(1, $this->dispatched);
    }

    #[Test]
    public function removedDatesWithoutBookingsDispatchNothing(): void
    {
        $this->freezeTime('2026-06-02 12:00:00');
        $this->bookingService->book($this->newBooking(frontendUser: 1), $this->occurrence(3), $this->settingsOf(6));

        // Open day loses its second date (occurrence 4), the booked first date stays
        $this->getConnectionPool()->getConnectionForTable('pages')->update(
            'pages',
            ['tx_golbevents_recurrence_frequency' => 'none'],
            ['uid' => 6],
            ['uid' => Connection::PARAM_INT]
        );
        $this->synchronizer->synchronizeEvent(6);

        self::assertSame(3, self::toInt($this->bookingRows()[0]['occurrence']));
        self::assertSame([], $this->dispatched);
    }
}
