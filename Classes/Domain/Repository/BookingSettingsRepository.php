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

use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

/**
 * Reads the booking settings of event pages. Works on the live default
 * language page record, as Golb Events does for the occurrences; the
 * settings are not translated.
 */
class BookingSettingsRepository
{
    /**
     * @var array<int, BookingSettings>
     */
    private array $cache = [];

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    public function findByEventUid(int $eventUid): BookingSettings
    {
        if (isset($this->cache[$eventUid])) {
            return $this->cache[$eventUid];
        }

        $row = null;
        if ($eventUid > 0) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
            $queryBuilder->getRestrictions()->removeAll();
            $row = $queryBuilder->select('*')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($eventUid, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                    $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                    $queryBuilder->expr()->neq('doktype', $queryBuilder->createNamedParameter(PageRepository::DOKTYPE_SYSFOLDER, Connection::PARAM_INT))
                )
                ->executeQuery()
                ->fetchAssociative();
        }

        return $this->cache[$eventUid] = is_array($row) ? BookingSettings::fromRow($row) : new BookingSettings($eventUid);
    }
}
