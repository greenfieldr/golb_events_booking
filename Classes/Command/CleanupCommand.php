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

use Greenfieldr\GolbEventsBooking\Configuration\ExtensionSettings;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingRepository;
use Greenfieldr\GolbEventsBooking\Domain\Repository\BookingSettingsRepository;
use Greenfieldr\GolbEventsBooking\Service\BookingStateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Context\Context;

/**
 * Cancels pending bookings whose deadline has passed (their seats go to the
 * waiting list) and anonymizes the personal data of bookings whose date is
 * older than the retention period. Run it daily through the scheduler.
 */
#[AsCommand(
    name: 'golbeventsbooking:cleanup',
    description: 'Cancels expired pending bookings and anonymizes bookings older than the retention period.'
)]
final class CleanupCommand extends Command
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly BookingSettingsRepository $settingsRepository,
        private readonly BookingStateService $bookingStateService,
        private readonly ExtensionSettings $extensionSettings,
        private readonly Context $context,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $expired = $this->bookingStateService->expirePending($this->settingsRepository->findByEventUid(...));
        $io->text(sprintf('Cancelled %d expired pending booking(s).', count($expired)));

        $retentionDays = $this->extensionSettings->getRetentionDays();
        if ($retentionDays > 0) {
            $before = $this->now()->modify('-' . $retentionDays . ' days');
            $anonymized = 0;
            foreach ($this->bookingRepository->findForAnonymization($before) as $booking) {
                $this->bookingStateService->anonymize($booking);
                $anonymized++;
            }
            $io->text(sprintf('Anonymized %d booking(s) of dates before %s.', $anonymized, $before->format('Y-m-d')));
        } else {
            $io->text('Retention period not set (extension setting "retentionDays"), nothing anonymized.');
        }
        $io->success('Cleanup done.');

        return Command::SUCCESS;
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->context->getPropertyFromAspect('date', 'full');

        return $now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable();
    }
}
