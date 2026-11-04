<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Functional\Controller;

use Greenfieldr\GolbEventsBooking\Constants;
use Greenfieldr\GolbEventsBooking\Controller\BookingController;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingRequest;
use Greenfieldr\GolbEventsBooking\Event\ValidateBookingEvent;
use Greenfieldr\GolbEventsBooking\Service\BookingStateService;
use Greenfieldr\GolbEventsBooking\Tests\Functional\AbstractBookingTestCase;
use Greenfieldr\GolbEventsBooking\Validation\Validator\BookingRequestValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Renders the booking form through the frontend and books through it. The
 * TypoScript fixture renders lib.golbEventsBooking.form on every event page;
 * the fixture holds the Workshop (page 3, 3 seats, 2 per booking, waiting
 * list), the Lecture (page 4, 1 seat, closes 24 hours before), the Open day
 * (page 6, unlimited, two dates), a disabled event (page 7) and an event
 * that opens in 2099 (page 8). All dates lie in 2099.
 */
#[CoversClass(BookingController::class)]
#[CoversClass(BookingRequest::class)]
#[CoversClass(BookingRequestValidator::class)]
#[CoversClass(ValidateBookingEvent::class)]
final class BookingControllerTest extends AbstractBookingTestCase
{
    protected array $coreExtensionsToLoad = ['typo3/cms-fluid-styled-content'];

    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/golb_events_booking/Tests/Functional/Controller/Fixtures/Sites/' => 'typo3conf/sites',
    ];

    protected array $configurationToUseInTestInstance = [
        'FE' => [
            'cacheHash' => [
                'enforceValidation' => false,
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/fe_users.csv');
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:golb/Configuration/TypoScript/constants.typoscript',
                'EXT:golb_events/Configuration/TypoScript/constants.typoscript',
                'EXT:golb_events_booking/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:golb/Configuration/TypoScript/setup.typoscript',
                'EXT:golb_events/Configuration/TypoScript/setup.typoscript',
                'EXT:golb_events_booking/Configuration/TypoScript/setup.typoscript',
                'EXT:golb_events_booking/Tests/Functional/Controller/Fixtures/TypoScript/Rendering.typoscript',
            ],
        ]);
    }

    /**
     * @param array<string, int|string> $queryParameters
     */
    private function fetch(int $pageId, array $queryParameters = [], int $frontendUserId = 0): ResponseInterface
    {
        $request = (new InternalRequest())->withPageId($pageId);
        foreach ($queryParameters as $name => $value) {
            $request = $request->withQueryParameter($name, $value);
        }

        return $this->executeFrontendSubRequest($request, $this->requestContext($frontendUserId));
    }

    /**
     * @param array<string, mixed> $bookingRequest the fields of the booking form
     * @param array<string, int|string> $queryParameters
     */
    private function post(int $pageId, array $bookingRequest, array $queryParameters = [], int $frontendUserId = 0, string $action = 'create'): ResponseInterface
    {
        $request = (new InternalRequest())->withPageId($pageId)
            ->withQueryParameter('tx_golbeventsbooking_form[action]', $action)
            ->withQueryParameter('tx_golbeventsbooking_form[controller]', 'Booking');
        foreach ($queryParameters as $name => $value) {
            $request = $request->withQueryParameter($name, $value);
        }
        return $this->executeFrontendSubRequest($this->withPostBody($request, ['tx_golbeventsbooking_form' => ['bookingRequest' => $bookingRequest]]), $this->requestContext($frontendUserId));
    }

    /**
     * A POST request with the values as form encoded body; the testing
     * framework parses the body into the parsed body of the request.
     *
     * @param array<string, mixed> $values
     */
    private function withPostBody(InternalRequest $request, array $values): InternalRequest
    {
        $body = new Stream('php://temp', 'rw');
        $body->write(http_build_query($values));
        $body->rewind();

        return $request->withMethod('POST')->withBody($body);
    }

    private function requestContext(int $frontendUserId): InternalRequestContext
    {
        $context = new InternalRequestContext();

        return $frontendUserId > 0 ? $context->withFrontendUserId($frontendUserId) : $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function validForm(int $occurrence = 1, int $seats = 1): array
    {
        return [
            'occurrence' => $occurrence,
            'salutation' => 'ms',
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => 'ada@example.org',
            'phone' => '',
            'company' => 'Analytical Engines',
            'seats' => $seats,
            'note' => 'Vegetarian',
            'privacy' => 1,
            'website' => '',
            'startedAt' => 0,
        ];
    }

    /**
     * The token of the only booking in the database.
     */
    private function tokenOfTheBooking(): string
    {
        $rows = $this->bookingRows();
        self::assertCount(1, $rows);
        self::assertIsString($rows[0]['token']);

        return $rows[0]['token'];
    }

    #[Test]
    public function formOnAnEventPageShowsTheDateWithFreeSeats(): void
    {
        $html = (string)$this->fetch(3)->getBody();

        self::assertStringContainsString('class="golbbooking"', $html);
        self::assertStringContainsString('Book this event', $html);
        self::assertStringContainsString('04.05.2099', $html);
        self::assertStringContainsString('3 seats free', $html);
        self::assertStringContainsString('name="tx_golbeventsbooking_form[bookingRequest][occurrence]" value="1"', $html);
        self::assertStringContainsString('name="tx_golbeventsbooking_form[bookingRequest][website]"', $html, 'honeypot');
        self::assertStringContainsString('Up to 2 seats per booking', $html);
        self::assertStringContainsString('golb_events_booking.css', $html);
        self::assertStringNotContainsString('type="radio"', $html, 'a single date needs no choice');
    }

    #[Test]
    public function formOfARecurringEventOffersTheDatesAsRadioList(): void
    {
        $html = (string)$this->fetch(6)->getBody();

        self::assertSame(2, substr_count($html, 'type="radio"'));
        self::assertStringContainsString('01.07.2099', $html);
        self::assertStringContainsString('08.07.2099', $html);
        self::assertStringContainsString('Seats available', $html);
        self::assertStringContainsString('checked="checked"', $html, 'the first bookable date is preselected');
    }

    #[Test]
    public function formOfAnEventThatHasNotOpenedShowsTheDatesAsClosed(): void
    {
        $html = (string)$this->fetch(8)->getBody();

        self::assertStringContainsString('No date can be booked at the moment.', $html);
        self::assertSame(2, substr_count($html, 'Booking closed'));
        self::assertStringNotContainsString('<form', $html);
    }

    #[Test]
    public function formIsNotRenderedForDisabledEventsAndOrdinaryPages(): void
    {
        self::assertStringNotContainsString('golbbooking', (string)$this->fetch(7)->getBody());
        self::assertStringNotContainsString('golbbooking', (string)$this->fetch(2)->getBody());
    }

    #[Test]
    public function formIsPrefilledForLoggedInUsers(): void
    {
        $html = (string)$this->fetch(3, [], 1)->getBody();

        self::assertStringContainsString('value="Grace"', $html);
        self::assertStringContainsString('value="Hopper"', $html);
        self::assertStringContainsString('value="grace@example.org"', $html);
        self::assertStringContainsString('value="Navy"', $html);
    }

    #[Test]
    public function guestBookingIsStoredPendingAndTheSuccessPageAsksForConfirmation(): void
    {
        $response = $this->post(3, $this->validForm());

        self::assertSame(303, $response->getStatusCode());
        $token = $this->tokenOfTheBooking();
        self::assertStringContainsString('tx_golbeventsbooking_form%5Btoken%5D=' . $token, $response->getHeaderLine('Location'));
        $row = $this->bookingRows()[0];
        self::assertSame(Booking::STATUS_PENDING, $row['status']);
        self::assertSame(self::STORAGE_PID, self::toInt($row['pid']));
        self::assertSame(3, self::toInt($row['event']));
        self::assertSame(1, self::toInt($row['occurrence']));
        self::assertSame('Ada', $row['first_name']);
        self::assertSame('ms', $row['salutation']);
        self::assertSame('Vegetarian', $row['note']);
        self::assertSame(0, self::toInt($row['frontend_user']));
        self::assertGreaterThan(0, self::toInt($row['privacy_accepted_at']));

        $html = (string)$this->fetch(3, ['tx_golbeventsbooking_form[action]' => 'success', 'tx_golbeventsbooking_form[controller]' => 'Booking', 'tx_golbeventsbooking_form[token]' => $token])->getBody();
        self::assertStringContainsString('Please confirm your booking', $html);
        self::assertStringContainsString('ada@example.org', $html);
        self::assertStringContainsString('Awaiting your confirmation', $html);
        self::assertStringContainsString('Cancel this booking', $html);
    }

    #[Test]
    public function loggedInUserBookingIsConfirmedAtOnce(): void
    {
        $response = $this->post(3, $this->validForm(1, 2), [], 1);

        self::assertSame(303, $response->getStatusCode());
        $row = $this->bookingRows()[0];
        self::assertSame(Booking::STATUS_CONFIRMED, $row['status']);
        self::assertSame(1, self::toInt($row['frontend_user']));
        self::assertSame(2, self::toInt($row['seats']));

        $html = (string)$this->fetch(3, ['tx_golbeventsbooking_form[action]' => 'success', 'tx_golbeventsbooking_form[controller]' => 'Booking', 'tx_golbeventsbooking_form[token]' => $this->tokenOfTheBooking()], 1)->getBody();
        self::assertStringContainsString('Your booking is confirmed', $html);
    }

    #[Test]
    public function missingFieldsAndPrivacyRedisplayTheFormWithErrors(): void
    {
        $form = $this->validForm();
        $form['lastName'] = '';
        $form['email'] = 'not-an-address';
        $form['privacy'] = 0;

        $response = $this->post(3, $form);
        $html = (string)$response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $this->bookingRows());
        self::assertStringContainsString('<form', $html);
        self::assertStringContainsString('Please agree to the processing of your data.', $html);
        self::assertStringContainsString('golbbooking-form__errors', $html);
        self::assertStringContainsString('value="Ada"', $html, 'entered data is kept');
    }

    #[Test]
    public function filledHoneypotAndTooFastSubmissionsAreRejected(): void
    {
        $form = $this->validForm();
        $form['website'] = 'https://spam.example';
        $html = (string)$this->post(3, $form)->getBody();
        self::assertStringContainsString('submitted too quickly', $html);
        self::assertSame([], $this->bookingRows());

        // The frontend request runs on real time, not on the frozen time of the test
        $form = $this->validForm();
        $form['startedAt'] = time() - 10;
        $html = (string)$this->post(3, $form, ['golbBookingSettings' => 'minimumFillTime=60'])->getBody();
        self::assertStringContainsString('submitted too quickly', $html);
        self::assertSame([], $this->bookingRows());

        $form['startedAt'] = time() - 120;
        self::assertSame(303, $this->post(3, $form, ['golbBookingSettings' => 'minimumFillTime=60'])->getStatusCode());
        self::assertCount(1, $this->bookingRows());
    }

    #[Test]
    public function tooManySeatsAreRejectedByTheValidator(): void
    {
        $html = (string)$this->post(3, $this->validForm(1, 3))->getBody();

        self::assertStringContainsString('Up to 2 seats can be booked at once.', $html);
        self::assertSame([], $this->bookingRows());
    }

    #[Test]
    public function fullDateWithoutWaitingListIsRejected(): void
    {
        $this->get(BookingStateService::class)->book($this->newBooking('first@example.org', 1, 1), $this->occurrence(2), $this->settingsOf(4));

        $html = (string)$this->post(4, $this->validForm(2))->getBody();

        self::assertStringContainsString('The date is fully booked.', $html);
        self::assertCount(1, $this->bookingRows());
    }

    #[Test]
    public function fullDateWithWaitingListPutsTheBookingOnTheWaitingList(): void
    {
        $this->get(BookingStateService::class)->book($this->newBooking('first@example.org', 2, 1), $this->occurrence(1), $this->settingsOf(3));

        $response = $this->post(3, $this->validForm(1, 2));

        self::assertSame(303, $response->getStatusCode());
        $rows = $this->bookingRows();
        self::assertCount(2, $rows);
        self::assertSame(Booking::STATUS_WAITING, $rows[1]['status']);
        self::assertIsString($rows[1]['token']);
        $html = (string)$this->fetch(3, ['tx_golbeventsbooking_form[action]' => 'success', 'tx_golbeventsbooking_form[controller]' => 'Booking', 'tx_golbeventsbooking_form[token]' => $rows[1]['token']])->getBody();
        self::assertStringContainsString('You are on the waiting list', $html);
    }

    #[Test]
    public function confirmationLinkConfirmsAPendingBookingOnce(): void
    {
        $this->post(3, $this->validForm());
        $token = $this->tokenOfTheBooking();
        $parameters = ['tx_golbeventsbooking_form[action]' => 'confirm', 'tx_golbeventsbooking_form[controller]' => 'Booking', 'tx_golbeventsbooking_form[token]' => $token];

        $html = (string)$this->fetch(3, $parameters)->getBody();
        self::assertStringContainsString('Thank you for confirming', $html);
        self::assertSame(Booking::STATUS_CONFIRMED, $this->bookingRows()[0]['status']);

        $html = (string)$this->fetch(3, $parameters)->getBody();
        self::assertStringContainsString('Booking already confirmed', $html);
    }

    #[Test]
    public function cancellationLinkAsksAndCancelsOnPost(): void
    {
        $this->post(3, $this->validForm(1, 2), [], 1);
        $token = $this->tokenOfTheBooking();
        $parameters = ['tx_golbeventsbooking_form[action]' => 'cancel', 'tx_golbeventsbooking_form[controller]' => 'Booking', 'tx_golbeventsbooking_form[token]' => $token];

        $html = (string)$this->fetch(3, $parameters)->getBody();
        self::assertStringContainsString('Cancel booking?', $html);
        self::assertStringContainsString('Yes, cancel my booking', $html);
        self::assertSame(Booking::STATUS_CONFIRMED, $this->bookingRows()[0]['status'], 'a GET does not cancel');

        $request = $this->withPostBody((new InternalRequest())->withPageId(3)->withQueryParameters($parameters), ['tx_golbeventsbooking_form' => ['confirmed' => '1']]);
        $html = (string)$this->executeFrontendSubRequest($request)->getBody();
        self::assertStringContainsString('Your booking has been cancelled', $html);
        self::assertSame(Booking::STATUS_CANCELLED, $this->bookingRows()[0]['status']);

        $html = (string)$this->fetch(3, $parameters)->getBody();
        self::assertStringContainsString('Booking already cancelled', $html);
    }

    #[Test]
    public function unknownTokensShowTheNotFoundMessage(): void
    {
        foreach (['success', 'confirm', 'cancel'] as $action) {
            $html = (string)$this->fetch(3, ['tx_golbeventsbooking_form[action]' => $action, 'tx_golbeventsbooking_form[controller]' => 'Booking', 'tx_golbeventsbooking_form[token]' => str_repeat('0', 40)])->getBody();
            self::assertStringContainsString('Booking not found', $html, $action);
        }
    }

    #[Test]
    public function contentElementBooksTheSelectedEventOnAnotherPage(): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('tt_content')->insert('tt_content', [
            'uid' => 100,
            'pid' => 2,
            'colPos' => 0,
            'CType' => 'golbeventsbooking_form',
            'header' => 'Book the workshop',
            'pi_flexform' => '<?xml version="1.0" encoding="utf-8" standalone="yes" ?><T3FlexForms><data><sheet index="sDEF"><language index="lDEF">'
                . '<field index="settings.eventUid"><value index="vDEF">3</value></field>'
                . '</language></sheet></data></T3FlexForms>',
        ]);

        $html = (string)$this->fetch(2)->getBody();
        self::assertStringContainsString('Book this event', $html);
        self::assertStringContainsString('3 seats free', $html);

        self::assertSame(303, $this->post(2, $this->validForm())->getStatusCode());
        self::assertSame(3, self::toInt($this->bookingRows()[0]['event']));
    }

    #[Test]
    public function privacyLinkPointsToTheConfiguredPage(): void
    {
        $html = (string)$this->fetch(3, ['golbBookingSettings' => 'privacyPageUid=1'])->getBody();

        self::assertStringContainsString('privacy policy</a>', $html);
        self::assertStringContainsString(Constants::EXTENSION_KEY, 'golb_events_booking');
    }
}
