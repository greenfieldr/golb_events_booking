<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Mail;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Export\IcalBuilder;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings;
use Greenfieldr\GolbEventsBooking\Service\BookingLinkService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Sends the mails of the booking workflow as FluidEmail: to the
 * participant (opt-in request, confirmation, waiting list, promotion,
 * cancellation, reminder) and to the organizer (notification about new and
 * cancelled bookings, removed dates). The templates live in
 * Resources/Private/Templates/Email/Booking and can be overridden through
 * $GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'].
 *
 * Sender and reply-to come from the TYPO3 mail defaults
 * (defaultMailFromAddress, defaultMailReplyToAddress).
 */
class BookingMailer
{
    public const TEMPLATE_OPT_IN = 'Booking/OptIn';
    public const TEMPLATE_CONFIRMED = 'Booking/Confirmed';
    public const TEMPLATE_WAITING = 'Booking/Waiting';
    public const TEMPLATE_PROMOTED = 'Booking/Promoted';
    public const TEMPLATE_CANCELLED = 'Booking/Cancelled';
    public const TEMPLATE_REMINDER = 'Booking/Reminder';
    public const TEMPLATE_ORGANIZER_BOOKED = 'Booking/OrganizerBooked';
    public const TEMPLATE_ORGANIZER_CANCELLED = 'Booking/OrganizerCancelled';
    public const TEMPLATE_ORGANIZER_DATES_REMOVED = 'Booking/OrganizerDatesRemoved';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly BookingLinkService $linkService,
        private readonly IcalBuilder $icalBuilder,
        private readonly Context $context,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Mail to the participant after the booking: the opt-in request for a
     * pending booking, the confirmation or the waiting list notice.
     */
    public function sendBookingResult(Booking $booking, BookingSettings $settings): bool
    {
        $template = match (true) {
            $booking->isPending() => self::TEMPLATE_OPT_IN,
            $booking->isWaiting() => self::TEMPLATE_WAITING,
            $booking->isConfirmed() => self::TEMPLATE_CONFIRMED,
            default => null,
        };
        if ($template === null) {
            return false;
        }

        return $this->sendToParticipant($booking, $settings, $template, $booking->isConfirmed());
    }

    /**
     * Mail to the participant after the opt-in confirmation.
     */
    public function sendConfirmed(Booking $booking, BookingSettings $settings): bool
    {
        return $this->sendToParticipant($booking, $settings, self::TEMPLATE_CONFIRMED, true);
    }

    /**
     * Mail to the participant who moved up from the waiting list.
     */
    public function sendPromoted(Booking $booking, BookingSettings $settings): bool
    {
        return $this->sendToParticipant($booking, $settings, self::TEMPLATE_PROMOTED, true);
    }

    public function sendCancelled(Booking $booking, BookingSettings $settings): bool
    {
        return $this->sendToParticipant($booking, $settings, self::TEMPLATE_CANCELLED, false);
    }

    public function sendReminder(Booking $booking, BookingSettings $settings): bool
    {
        return $this->sendToParticipant($booking, $settings, self::TEMPLATE_REMINDER, true);
    }

    /**
     * Notification about a new booking to the organizer, if an address is
     * configured. Pending bookings are reported when they are confirmed.
     */
    public function sendOrganizerBooked(Booking $booking, BookingSettings $settings): bool
    {
        return $this->sendToOrganizer($booking, $settings, self::TEMPLATE_ORGANIZER_BOOKED);
    }

    public function sendOrganizerCancelled(Booking $booking, BookingSettings $settings): bool
    {
        return $this->sendToOrganizer($booking, $settings, self::TEMPLATE_ORGANIZER_CANCELLED);
    }

    /**
     * Notification to the organizer that dates with bookings were removed.
     *
     * @param list<Booking> $bookings
     */
    public function sendOrganizerDatesRemoved(array $bookings, BookingSettings $settings): bool
    {
        $first = $bookings[0] ?? null;
        if (!$first instanceof Booking) {
            return false;
        }
        $recipient = $this->organizerAddress($first, $settings);
        if (!$recipient instanceof Address) {
            return false;
        }
        $email = $this->email(self::TEMPLATE_ORGANIZER_DATES_REMOVED, $first, $settings)
            ->assign('bookings', $bookings)
            ->to($recipient);

        return $this->send($email);
    }

    /**
     * The address the organizer notifications go to: the notification
     * address of the event or the address of the organizer record.
     */
    public function organizerAddress(Booking $booking, BookingSettings $settings): ?Address
    {
        $address = $settings->notificationEmail;
        if ($address === '') {
            $address = (string)$booking->getEvent()?->getOrganizer()?->getEmail();
        }
        if (!GeneralUtility::validEmail($address)) {
            return null;
        }
        $name = (string)$booking->getEvent()?->getOrganizerName();

        return new Address($address, $settings->notificationEmail === '' ? $name : '');
    }

    private function sendToParticipant(Booking $booking, BookingSettings $settings, string $template, bool $attachIcal): bool
    {
        if (!GeneralUtility::validEmail($booking->getEmail())) {
            return false;
        }
        $email = $this->email($template, $booking, $settings)
            ->to(new Address($booking->getEmail(), $booking->getName()));
        if ($attachIcal) {
            $this->attachIcal($email, $booking);
        }

        return $this->send($email);
    }

    private function sendToOrganizer(Booking $booking, BookingSettings $settings, string $template): bool
    {
        $recipient = $this->organizerAddress($booking, $settings);
        if (!$recipient instanceof Address) {
            return false;
        }
        $email = $this->email($template, $booking, $settings)
            ->to($recipient)
            ->replyTo(new Address($booking->getEmail(), $booking->getName()));

        return $this->send($email);
    }

    /**
     * A FluidEmail of the template with the booking variables and the
     * translated subject (key "mail.<template>.subject" with the event title).
     */
    private function email(string $template, Booking $booking, BookingSettings $settings): FluidEmail
    {
        $event = $booking->getEvent();
        $subjectKey = 'mail.' . lcfirst(substr($template, strlen('Booking/'))) . '.subject';

        return GeneralUtility::makeInstance(FluidEmail::class)
            ->setTemplate($template)
            ->format(FluidEmail::FORMAT_BOTH)
            ->subject(LocalizationUtility::translate($subjectKey, 'GolbEventsBooking', [(string)$event?->getTitle()]) ?? $subjectKey)
            ->assignMultiple([
                'booking' => $booking,
                'event' => $event,
                'occurrence' => $booking->getOccurrence(),
                'settings' => $settings,
                'eventUrl' => $this->linkService->eventUrl($booking),
                'confirmUrl' => $this->linkService->confirmUrl($booking),
                'cancelUrl' => $this->linkService->cancelUrl($booking),
                'successUrl' => $this->linkService->successUrl($booking),
                'dateFormat' => $this->systemSetting('ddmmyy', 'd-m-y'),
                'timeFormat' => $this->systemSetting('hhmm', 'H:i'),
            ]);
    }

    private function attachIcal(FluidEmail $email, Booking $booking): void
    {
        $event = $booking->getEvent();
        if (!$event instanceof Event) {
            return;
        }
        $url = $this->linkService->eventUrl($booking);
        $host = parse_url($url, PHP_URL_HOST);
        $ical = $this->icalBuilder->build($event, $url, is_string($host) ? $host : 'golb-events', $this->now());
        if ($ical !== null) {
            $email->attach($ical, 'event.ics', 'text/calendar');
        }
    }

    /**
     * A string setting of $GLOBALS['TYPO3_CONF_VARS']['SYS'], e.g. the date
     * and time formats of the backend, which the mails use as well.
     */
    private function systemSetting(string $key, string $default): string
    {
        $configuration = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        $system = is_array($configuration) && is_array($configuration['SYS'] ?? null) ? $configuration['SYS'] : [];
        $value = $system[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function send(FluidEmail $email): bool
    {
        try {
            $this->mailer->send($email);

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Sending a booking mail failed: {message}', ['message' => $exception->getMessage(), 'exception' => $exception]);

            return false;
        }
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->context->getPropertyFromAspect('date', 'full');

        return $now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable();
    }
}
