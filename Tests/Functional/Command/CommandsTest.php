<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Functional\Command;

use Greenfieldr\GolbEventsBooking\Command\CleanupCommand;
use Greenfieldr\GolbEventsBooking\Command\SendRemindersCommand;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingRepository;
use Greenfieldr\GolbEventsBooking\Service\BookingStateService;
use Greenfieldr\GolbEventsBooking\Tests\Functional\AbstractBookingTestCase;
use Greenfieldr\GolbEventsBooking\Tests\Functional\Fixtures\Mail\CollectingTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

#[CoversClass(SendRemindersCommand::class)]
#[CoversClass(CleanupCommand::class)]
#[CoversClass(BookingRepository::class)]
final class CommandsTest extends AbstractBookingTestCase
{
    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/golb_events_booking/Tests/Functional/Controller/Fixtures/Sites/' => 'typo3conf/sites',
    ];

    protected array $configurationToUseInTestInstance = [
        'MAIL' => [
            'transport' => CollectingTransport::class,
            'defaultMailFromAddress' => 'noreply@example.org',
        ],
    ];

    private BookingStateService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        CollectingTransport::reset();
        $this->bookingService = $this->get(BookingStateService::class);
    }

    #[Test]
    public function remindersGoToConfirmedBookingsOfTheComingDaysOnce(): void
    {
        $settings = $this->settingsOf(3);
        $confirmed = $this->bookingService->book($this->newBooking('a@example.org', 1, 1), $this->occurrence(1), $settings);
        $pending = $this->bookingService->book($this->newBooking('b@example.org', 1), $this->occurrence(1), $settings);
        $later = $this->bookingService->book($this->newBooking('c@example.org', 1, 3), $this->occurrence(2), $this->settingsOf(4));
        CollectingTransport::reset();
        $tester = new CommandTester($this->get(SendRemindersCommand::class));

        // Workshop on 2099-05-04 19:30, Lecture on 2099-05-05 10:00: one day ahead covers the Workshop only
        $this->freezeTime('2099-05-04 08:00:00');
        self::assertSame(Command::SUCCESS, $tester->execute(['--days' => '1']));

        self::assertStringContainsString('Sent 1 reminder(s)', $tester->getDisplay());
        self::assertSame(['a@example.org'], $this->recipients());
        self::assertSame('2099-05-04 08:00:00', $confirmed->getReminderSentAt()?->format('Y-m-d H:i:s'));
        self::assertNull($pending->getReminderSentAt(), 'pending bookings get no reminder');
        self::assertNull($later->getReminderSentAt(), 'the Lecture is more than a day away');

        CollectingTransport::reset();
        self::assertSame(Command::SUCCESS, $tester->execute(['--days' => '3']));
        self::assertStringContainsString('Sent 1 reminder(s)', $tester->getDisplay());
        self::assertSame(['c@example.org'], $this->recipients(), 'only the Lecture is left, the Workshop was reminded already');

        self::assertSame(Command::INVALID, $tester->execute(['--days' => 'soon']));
    }

    #[Test]
    public function cleanupCancelsExpiredPendingBookingsAndAnonymizesOldOnes(): void
    {
        $settings = $this->settingsOf(3);
        $pending = $this->bookingService->book($this->newBooking('a@example.org', 2), $this->occurrence(1), $settings);
        $waiting = $this->bookingService->book($this->newBooking('b@example.org', 2, 2), $this->occurrence(1), $settings);
        $old = $this->bookingService->book($this->newBooking('c@example.org', 1, 3), $this->occurrence(2), $this->settingsOf(4));
        self::assertTrue($pending->isPending());
        self::assertTrue($waiting->isWaiting());
        CollectingTransport::reset();
        $tester = new CommandTester($this->get(CleanupCommand::class));

        $this->freezeTime('2026-01-03 12:00:01');
        self::assertSame(Command::SUCCESS, $tester->execute([]));

        self::assertStringContainsString('Cancelled 1 expired pending booking(s).', $tester->getDisplay());
        self::assertStringContainsString('Retention period not set', $tester->getDisplay());
        self::assertTrue($pending->isCancelled());
        self::assertTrue($waiting->isConfirmed(), 'the waiting booking moved up');
        self::assertSame(['a@example.org', 'office@example.org', 'b@example.org', 'office@example.org'], $this->recipients());
        self::assertSame('c@example.org', $this->bookingRows()[2]['email'], 'no retention period, nothing anonymized');

        // Thirty days after the Lecture (2099-05-05) its booking is anonymized, the Workshop's stay
        $this->configureExtension(['retentionDays' => '30']);
        $this->freezeTime('2099-05-05 12:00:00');
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Anonymized 0 booking(s) of dates before 2099-04-05.', $tester->getDisplay());
        $rows = $this->bookingRows();
        self::assertSame(0, self::toInt($rows[0]['anonymized']) + self::toInt($rows[1]['anonymized']) + self::toInt($rows[2]['anonymized']), 'the Lecture is not 30 days old yet');

        // Thirty days after both dates every booking is anonymized, whatever its status
        $this->freezeTime('2099-06-05 12:00:00');
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Anonymized 3 booking(s) of dates before 2099-05-06.', $tester->getDisplay());
        $rows = $this->bookingRows();
        self::assertSame([1, 1, 1], array_map(static fn(array $row): int => self::toInt($row['anonymized']), $rows));
        self::assertSame(['', '', ''], array_column($rows, 'email'));
        self::assertSame([Booking::STATUS_CANCELLED, Booking::STATUS_CONFIRMED, Booking::STATUS_CONFIRMED], array_column($rows, 'status'), 'statuses and seats are kept for the statistics');
        self::assertSame($old->getUid(), self::toInt($rows[2]['uid']));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Anonymized 0 booking(s)', $tester->getDisplay(), 'anonymized bookings are not touched again');
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        return array_map(static fn(Email $email): string => $email->getTo()[0]->getAddress(), CollectingTransport::sent());
    }
}
