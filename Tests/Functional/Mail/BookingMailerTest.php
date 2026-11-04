<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Functional\Mail;

use Greenfieldr\GolbEvents\Recurrence\OccurrenceSynchronizer;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\EventListener\BookingMailListener;
use Greenfieldr\GolbEventsBooking\Mail\BookingMailer;
use Greenfieldr\GolbEventsBooking\Service\BookingLinkService;
use Greenfieldr\GolbEventsBooking\Service\BookingStateService;
use Greenfieldr\GolbEventsBooking\Tests\Functional\AbstractBookingTestCase;
use Greenfieldr\GolbEventsBooking\Tests\Functional\Fixtures\Mail\CollectingTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Email;
use TYPO3\CMS\Core\Database\Connection;

/**
 * The mails sent by the booking workflow. The site fixture gives the event
 * pages the base http://localhost/ for the links; the Workshop (event 3)
 * has the notification address office@example.org.
 */
#[CoversClass(BookingMailer::class)]
#[CoversClass(BookingMailListener::class)]
#[CoversClass(BookingLinkService::class)]
final class BookingMailerTest extends AbstractBookingTestCase
{
    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/golb_events_booking/Tests/Functional/Controller/Fixtures/Sites/' => 'typo3conf/sites',
    ];

    protected array $configurationToUseInTestInstance = [
        'MAIL' => [
            'transport' => CollectingTransport::class,
            'defaultMailFromAddress' => 'noreply@example.org',
            'defaultMailFromName' => 'Example site',
        ],
    ];

    private BookingStateService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        CollectingTransport::reset();
        $this->bookingService = $this->get(BookingStateService::class);
    }

    /**
     * @return list<string> "recipient: subject" of every sent mail
     */
    private function sentSummary(): array
    {
        return array_map(
            static fn(Email $email): string => implode(',', array_map(static fn($address) => $address->getAddress(), $email->getTo())) . ': ' . $email->getSubject(),
            CollectingTransport::sent()
        );
    }

    private function sentMail(int $index): Email
    {
        $email = CollectingTransport::sent()[$index] ?? null;
        self::assertInstanceOf(Email::class, $email);

        return $email;
    }

    private function textBody(Email $email): string
    {
        $body = $email->getTextBody();
        self::assertIsString($body);

        return $body;
    }

    private function htmlBody(Email $email): string
    {
        $body = $email->getHtmlBody();
        self::assertIsString($body);

        return $body;
    }

    #[Test]
    public function guestBookingSendsTheOptInRequestOnly(): void
    {
        $booking = $this->bookingService->book($this->newBooking(), $this->occurrence(1), $this->settingsOf(3));

        self::assertSame(['ada@example.org: Please confirm your booking: Workshop'], $this->sentSummary());
        $mail = $this->sentMail(0);
        self::assertSame('noreply@example.org', $mail->getFrom()[0]->getAddress());
        $text = $this->textBody($mail);
        self::assertStringContainsString('tx_golbeventsbooking_form%5Baction%5D=confirm', $text);
        self::assertStringContainsString($booking->getToken(), $text);
        self::assertStringContainsString('http://localhost/events/workshop', $text);
        self::assertStringContainsString('Event: Workshop', $text);
        self::assertStringContainsString('Seats: 1', $text);
        self::assertStringContainsString('Participant: Ada Lovelace', $text);
        self::assertStringContainsString('Status: Awaiting your confirmation', $text);
        self::assertStringContainsString('The reservation expires on', $text);
        self::assertStringContainsString('<a href="http://localhost/events/workshop?', $this->htmlBody($mail));
        self::assertSame([], $mail->getAttachments(), 'no calendar file before the confirmation');
    }

    #[Test]
    public function confirmingSendsTheConfirmationWithCalendarFileAndNotifiesTheOrganizer(): void
    {
        $booking = $this->bookingService->book($this->newBooking(), $this->occurrence(1), $this->settingsOf(3));
        CollectingTransport::reset();

        $this->bookingService->confirm($booking, $this->settingsOf(3));

        self::assertSame([
            'ada@example.org: Your booking is confirmed: Workshop',
            'office@example.org: New booking: Workshop',
        ], $this->sentSummary());
        $participant = $this->sentMail(0);
        self::assertCount(1, $participant->getAttachments());
        self::assertStringContainsString('BEGIN:VCALENDAR', $participant->getAttachments()[0]->getBody());
        self::assertStringContainsString('Cancel booking', $this->htmlBody($participant));
        self::assertStringContainsString('tx_golbeventsbooking_form%5Baction%5D=cancel', $this->textBody($participant));

        $organizer = $this->sentMail(1);
        self::assertSame('ada@example.org', $organizer->getReplyTo()[0]->getAddress());
        self::assertStringContainsString('Email: ada@example.org', $this->textBody($organizer));
        self::assertStringContainsString('Status: Confirmed', $this->textBody($organizer));
    }

    #[Test]
    public function frontendUserBookingSendsConfirmationAndOrganizerNotificationAtOnce(): void
    {
        $this->bookingService->book($this->newBooking('grace@example.org', 2, 7), $this->occurrence(1), $this->settingsOf(3));

        self::assertSame([
            'grace@example.org: Your booking is confirmed: Workshop',
            'office@example.org: New booking: Workshop',
        ], $this->sentSummary());
        self::assertStringContainsString('Seats: 2', $this->textBody($this->sentMail(1)));
    }

    #[Test]
    public function organizerRecordAddressIsUsedWithoutNotificationAddress(): void
    {
        // The Lecture has no notification address; give it an organizer record
        $this->getConnectionPool()->getConnectionForTable('tx_golbevents_domain_model_organizer')->insert(
            'tx_golbevents_domain_model_organizer',
            ['uid' => 1, 'pid' => 2, 'name' => 'Golb Society', 'email' => 'society@example.org']
        );
        $this->getConnectionPool()->getConnectionForTable('pages')->update('pages', ['tx_golbevents_organizer' => 1], ['uid' => 4], ['uid' => Connection::PARAM_INT]);

        $this->bookingService->book($this->newBooking('grace@example.org', 1, 7), $this->occurrence(2), $this->settingsOf(4));

        self::assertSame([
            'grace@example.org: Your booking is confirmed: Lecture',
            'society@example.org: New booking: Lecture',
        ], $this->sentSummary());
        self::assertSame('Golb Society', $this->sentMail(1)->getTo()[0]->getName());
    }

    #[Test]
    public function eventWithoutAnyOrganizerAddressSendsNoNotification(): void
    {
        $this->bookingService->book($this->newBooking('grace@example.org', 1, 7), $this->occurrence(2), $this->settingsOf(4));

        self::assertSame(['grace@example.org: Your booking is confirmed: Lecture'], $this->sentSummary());
    }

    #[Test]
    public function waitingListCancellationAndPromotionAreMailed(): void
    {
        $settings = $this->settingsOf(3);
        $first = $this->bookingService->book($this->newBooking('a@example.org', 2, 1), $this->occurrence(1), $settings);
        $this->bookingService->book($this->newBooking('b@example.org', 2, 2), $this->occurrence(1), $settings);
        self::assertSame([
            'a@example.org: Your booking is confirmed: Workshop',
            'office@example.org: New booking: Workshop',
            'b@example.org: You are on the waiting list: Workshop',
            'office@example.org: New booking: Workshop',
        ], $this->sentSummary());
        self::assertStringContainsString('Status: On the waiting list', $this->textBody($this->sentMail(2)));
        CollectingTransport::reset();

        $this->bookingService->cancel($first, $settings);

        self::assertSame([
            'a@example.org: Your booking was cancelled: Workshop',
            'office@example.org: Booking cancelled: Workshop',
            'b@example.org: A seat became free: Workshop',
            'office@example.org: New booking: Workshop',
        ], $this->sentSummary());
        self::assertCount(1, $this->sentMail(2)->getAttachments(), 'the promoted participant gets the calendar file');
        self::assertStringNotContainsString('Cancel booking', $this->htmlBody($this->sentMail(0)), 'a cancelled booking offers no cancellation');
    }

    #[Test]
    public function removedDatesAreReportedToTheOrganizer(): void
    {
        $this->bookingService->book($this->newBooking('a@example.org', 1, 1), $this->occurrence(1), $this->settingsOf(3));
        CollectingTransport::reset();

        $this->getConnectionPool()->getConnectionForTable('pages')->update(
            'pages',
            ['tx_golbevents_start' => '2099-05-11 19:30:00', 'tx_golbevents_end' => '2099-05-11 21:00:00'],
            ['uid' => 3],
            ['uid' => Connection::PARAM_INT]
        );
        $this->get(OccurrenceSynchronizer::class)->synchronizeEvent(3);

        self::assertSame(['office@example.org: Booked dates were removed: Workshop'], $this->sentSummary());
        $text = $this->textBody($this->sentMail(0));
        self::assertStringContainsString('Ada Lovelace <a@example.org>, 1 x Seats', $text);
        $configuration = $GLOBALS['TYPO3_CONF_VARS'];
        self::assertIsArray($configuration);
        self::assertIsArray($configuration['SYS']);
        $format = $configuration['SYS']['ddmmyy'] ?? 'd-m-y';
        self::assertIsString($format);
        self::assertStringContainsString(date($format, $this->occurrence(1)->getStart()?->getTimestamp() ?? 0), $text, 'the booked start is kept');
    }

    #[Test]
    public function reminderIsSentWithCalendarFile(): void
    {
        $booking = $this->bookingService->book($this->newBooking('grace@example.org', 1, 7), $this->occurrence(1), $this->settingsOf(3));
        CollectingTransport::reset();

        self::assertTrue($this->get(BookingMailer::class)->sendReminder($booking, $this->settingsOf(3)));

        self::assertSame(['grace@example.org: Reminder: Workshop'], $this->sentSummary());
        self::assertCount(1, $this->sentMail(0)->getAttachments());
    }

    #[Test]
    public function linksPointToTheEventPage(): void
    {
        $booking = $this->bookingService->book($this->newBooking('grace@example.org', 1, 7), $this->occurrence(1), $this->settingsOf(3));
        $links = $this->get(BookingLinkService::class);

        self::assertSame('http://localhost/events/workshop', $links->eventUrl($booking));
        self::assertStringStartsWith('http://localhost/events/workshop?', $links->confirmUrl($booking));
        self::assertStringContainsString('tx_golbeventsbooking_form%5Btoken%5D=' . $booking->getToken(), $links->cancelUrl($booking));
        self::assertStringContainsString('cHash=', $links->successUrl($booking));

        $anonymous = new Booking();
        self::assertSame('', $links->confirmUrl($anonymous), 'no token, no link');
    }
}
