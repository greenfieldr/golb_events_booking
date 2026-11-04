<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Functional;

use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEvents\Domain\Repository\OccurrenceRepository;
use Greenfieldr\GolbEventsBooking\Constants;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingSettingsRepository;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Shared setup for tests working with the booking fixture: the event pages
 * 3 (Workshop, 3 seats, 2 per booking, waiting list), 4 (Lecture, 1 seat,
 * closes 24 hours before), 6 (Open day, unlimited, opens 2026-06-01, two
 * dates), 7 (booking disabled) and 8 (opens 2099, two dates), the storage
 * folder 5 and the occurrences 1 to 7. All dates lie in 2099.
 */
abstract class AbstractBookingTestCase extends FunctionalTestCase
{
    public const STORAGE_PID = 5;

    protected array $testExtensionsToLoad = ['greenfieldr/golb', 'greenfieldr/golb_events', 'greenfieldr/golb_events_booking'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BookingSite.csv');
        $this->freezeTime();
        $this->configureExtension(['doubleOptIn' => '1', 'pendingHours' => '48', 'retentionDays' => '0']);
        // The fixture dates lie in 2099 so frontend sub requests (real time) see them as upcoming
        $this->get(ExtensionConfiguration::class)->set('golb_events', ['horizonYears' => '80']);
    }

    protected function freezeTime(string $now = '2026-01-01 12:00:00'): void
    {
        $this->get(Context::class)->setAspect('date', new DateTimeAspect(new \DateTimeImmutable($now, new \DateTimeZone('UTC'))));
    }

    /**
     * @param array<string, string> $settings
     */
    protected function configureExtension(array $settings): void
    {
        $this->get(ExtensionConfiguration::class)->set(Constants::EXTENSION_KEY, $settings);
    }

    protected function occurrence(int $uid): Occurrence
    {
        $occurrence = $this->get(OccurrenceRepository::class)->findByUid($uid);
        self::assertInstanceOf(Occurrence::class, $occurrence);

        return $occurrence;
    }

    protected function settingsOf(int $eventUid): BookingSettings
    {
        return $this->get(BookingSettingsRepository::class)->findByEventUid($eventUid);
    }

    /**
     * A booking with participant data, not yet stored.
     */
    protected function newBooking(string $email = 'ada@example.org', int $seats = 1, int $frontendUser = 0): Booking
    {
        $booking = new Booking();
        $booking->setPid(self::STORAGE_PID);
        $booking->setFirstName('Ada');
        $booking->setLastName('Lovelace');
        $booking->setEmail($email);
        $booking->setSeats($seats);
        $booking->setFrontendUser($frontendUser);
        $booking->setPrivacyAcceptedAt(new \DateTimeImmutable('2026-01-01 12:00:00', new \DateTimeZone('UTC')));

        return $booking;
    }

    protected static function toInt(mixed $value): int
    {
        self::assertIsNumeric($value);

        return (int)$value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function bookingRows(): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(Constants::TABLE_BOOKING);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder->select('*')->from(Constants::TABLE_BOOKING)->orderBy('uid')->executeQuery()->fetchAllAssociative();
    }
}
