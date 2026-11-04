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

use Greenfieldr\GolbEvents\Event\AfterOccurrencesSynchronizedEvent;
use Greenfieldr\GolbEvents\Recurrence\OccurrenceRecord;
use Greenfieldr\GolbEventsBooking\Constants;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingRepository;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingSettingsRepository;
use Greenfieldr\GolbEventsBooking\Event\AfterBookedDatesRemovedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Keeps bookings attached to their dates. Golb Events keeps occurrence
 * records stable, so bookings only lose their date when the editor really
 * removes it (changed start, shortened recurrence, deleted page). Such
 * bookings are flagged "date removed" and detached from the occurrence,
 * but keep their start, status and seats; an editor can assign another
 * date in the backend. The AfterBookedDatesRemovedEvent lets the mails
 * inform the organizer.
 *
 * Runs through the database directly: the DataHandler hook of Golb Events
 * dispatches the event in backend and CLI context where no Extbase
 * persistence session exists.
 */
#[AsEventListener]
final readonly class OccurrencesSynchronizedListener
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private BookingRepository $bookingRepository,
        private BookingSettingsRepository $settingsRepository,
        private EventDispatcherInterface $eventDispatcher,
        private Context $context,
    ) {}

    public function __invoke(AfterOccurrencesSynchronizedEvent $event): void
    {
        $removedUids = array_map(static fn(OccurrenceRecord $record): int => $record->uid, $event->getRemoved());
        if ($removedUids === []) {
            return;
        }

        $affectedUids = $this->findActiveBookingUids($removedUids);
        if ($affectedUids === []) {
            return;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Constants::TABLE_BOOKING);
        $queryBuilder->update(Constants::TABLE_BOOKING)
            ->set('occurrence', 0)
            ->set('date_removed', 1)
            ->set('tstamp', $this->context->getPropertyFromAspect('date', 'timestamp'))
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($affectedUids, Connection::PARAM_INT_ARRAY)))
            ->executeStatement();

        $bookings = [];
        foreach ($affectedUids as $uid) {
            $booking = $this->bookingRepository->findByUid($uid);
            if ($booking instanceof Booking) {
                // The object may have been loaded before the update
                $booking->setOccurrence(null);
                $booking->setDateRemoved(true);
                $bookings[] = $booking;
            }
        }

        $this->eventDispatcher->dispatch(new AfterBookedDatesRemovedEvent(
            $event->getEventUid(),
            $bookings,
            $this->settingsRepository->findByEventUid($event->getEventUid())
        ));
    }

    /**
     * @param int[] $occurrenceUids
     * @return int[]
     */
    private function findActiveBookingUids(array $occurrenceUids): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Constants::TABLE_BOOKING);
        $uids = $queryBuilder->select('uid')
            ->from(Constants::TABLE_BOOKING)
            ->where(
                $queryBuilder->expr()->in('occurrence', $queryBuilder->createNamedParameter($occurrenceUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->in('status', $queryBuilder->createNamedParameter(Booking::STATUSES_ACTIVE, Connection::PARAM_STR_ARRAY))
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map(static fn(mixed $uid): int => is_numeric($uid) ? (int)$uid : 0, $uids);
    }
}
