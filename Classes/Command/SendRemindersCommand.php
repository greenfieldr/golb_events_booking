<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Command;

use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingRepository;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingSettingsRepository;
use Greenfieldr\GolbEventsBooking\Mail\BookingMailer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Sends the reminder mail to the participants of confirmed bookings whose
 * date starts within the given number of days. Every booking gets one
 * reminder; run the command daily through the scheduler.
 */
#[AsCommand(
    name: 'golbeventsbooking:reminders:send',
    description: 'Sends reminder mails for confirmed bookings of dates that start within the given number of days.'
)]
final class SendRemindersCommand extends Command
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly BookingSettingsRepository $settingsRepository,
        private readonly BookingMailer $mailer,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly Context $context,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Remind participants of dates starting within this many days', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = $input->getOption('days');
        if (!is_string($days) || !ctype_digit($days) || (int)$days <= 0) {
            $io->error('The days option must be a positive number.');

            return Command::INVALID;
        }

        $now = $this->now();
        $until = $now->modify('+' . (int)$days . ' days');
        $sent = 0;
        foreach ($this->bookingRepository->findDueForReminder($now, $until) as $booking) {
            $settings = $this->settingsRepository->findByEventUid((int)$booking->getEvent()?->getUid());
            if ($this->mailer->sendReminder($booking, $settings)) {
                $booking->setReminderSentAt($now);
                $this->bookingRepository->update($booking);
                $sent++;
            }
        }
        $this->persistenceManager->persistAll();

        $io->success(sprintf('Sent %d reminder(s) for dates until %s.', $sent, $until->format('Y-m-d H:i')));

        return Command::SUCCESS;
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->context->getPropertyFromAspect('date', 'full');

        return $now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable();
    }
}
