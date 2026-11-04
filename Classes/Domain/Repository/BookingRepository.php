<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Domain\Repository;

use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * New bookings are stored in the folder of the plugin settings
 * (plugin.tx_golbeventsbooking.persistence.storagePid). The lookups ignore
 * the storage page: they identify bookings by date, token or user and have
 * to work from the command line and the DataHandler, where no plugin
 * configuration exists.
 *
 * @extends Repository<Booking>
 */
class BookingRepository extends Repository
{
    protected $defaultOrderings = [
        'crdate' => QueryInterface::ORDER_ASCENDING,
        'uid' => QueryInterface::ORDER_ASCENDING,
    ];

    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * The bookings of a date in the given statuses, oldest first.
     *
     * @param string[] $statuses
     * @return Booking[]
     */
    public function findByOccurrence(Occurrence $occurrence, array $statuses = Booking::STATUSES_ACTIVE): array
    {
        $query = $this->createQuery();
        $query->matching($query->logicalAnd(
            $query->equals('occurrence', $occurrence),
            $query->in('status', $statuses)
        ));

        return $this->toList($query->execute());
    }

    /**
     * The first booking on the waiting list of a date, or null.
     */
    public function findFirstWaiting(Occurrence $occurrence): ?Booking
    {
        $query = $this->createQuery();
        $query->matching($query->logicalAnd(
            $query->equals('occurrence', $occurrence),
            $query->equals('status', Booking::STATUS_WAITING)
        ));
        $query->setLimit(1);
        $first = $query->execute()->getFirst();

        return $first instanceof Booking ? $first : null;
    }

    /**
     * The booking with the given token, or null. Tokens are unique random
     * secrets, see TokenService.
     */
    public function findOneByToken(string $token): ?Booking
    {
        if ($token === '') {
            return null;
        }
        $query = $this->createQuery();
        $query->matching($query->equals('token', $token));
        $query->setLimit(1);
        $booking = $query->execute()->getFirst();

        return $booking instanceof Booking ? $booking : null;
    }

    /**
     * The active bookings of a frontend user, next date first.
     *
     * @return Booking[]
     */
    public function findActiveByFrontendUser(int $frontendUserUid): array
    {
        if ($frontendUserUid <= 0) {
            return [];
        }
        $query = $this->createQuery();
        $query->matching($query->logicalAnd(
            $query->equals('frontendUser', $frontendUserUid),
            $query->in('status', Booking::STATUSES_ACTIVE)
        ));
        $query->setOrderings(['start' => QueryInterface::ORDER_ASCENDING, 'uid' => QueryInterface::ORDER_ASCENDING]);

        return $this->toList($query->execute());
    }

    /**
     * Pending bookings whose deadline has passed.
     *
     * @return Booking[]
     */
    public function findExpiredPending(\DateTimeImmutable $now): array
    {
        $query = $this->createQuery();
        $query->matching($query->logicalAnd(
            $query->equals('status', Booking::STATUS_PENDING),
            $query->greaterThan('pendingUntil', 0),
            $query->lessThan('pendingUntil', $now->getTimestamp())
        ));

        return $this->toList($query->execute());
    }

    /**
     * @param iterable<mixed> $result
     * @return Booking[]
     */
    private function toList(iterable $result): array
    {
        $bookings = [];
        foreach ($result as $booking) {
            if ($booking instanceof Booking) {
                $bookings[] = $booking;
            }
        }

        return $bookings;
    }
}
