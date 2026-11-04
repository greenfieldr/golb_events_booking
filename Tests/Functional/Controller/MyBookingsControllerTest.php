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

use Greenfieldr\GolbEventsBooking\Controller\MyBookingsController;
use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use Greenfieldr\GolbEventsBooking\Service\BookingStateService;
use Greenfieldr\GolbEventsBooking\Tests\Functional\AbstractBookingTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * The "My bookings" plugin on the Events page (page 2) for the frontend
 * user 1 (Grace Hopper).
 */
#[CoversClass(MyBookingsController::class)]
final class MyBookingsControllerTest extends AbstractBookingTestCase
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

    private BookingStateService $bookingService;

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
        $this->get(ConnectionPool::class)->getConnectionForTable('tt_content')->insert('tt_content', [
            'uid' => 200,
            'pid' => 2,
            'colPos' => 0,
            'CType' => 'golbeventsbooking_mybookings',
            'header' => 'My bookings',
        ]);
        $this->bookingService = $this->get(BookingStateService::class);
    }

    private function fetch(int $frontendUserId = 0): ResponseInterface
    {
        return $this->executeFrontendSubRequest((new InternalRequest())->withPageId(2), $this->requestContext($frontendUserId));
    }

    private function cancel(int $bookingUid, int $frontendUserId, string $method = 'POST'): ResponseInterface
    {
        $request = (new InternalRequest())->withPageId(2)
            ->withQueryParameter('tx_golbeventsbooking_mybookings[action]', 'cancel')
            ->withQueryParameter('tx_golbeventsbooking_mybookings[controller]', 'MyBookings')
            ->withQueryParameter('tx_golbeventsbooking_mybookings[booking]', $bookingUid)
            ->withMethod($method);
        if ($method === 'POST') {
            $body = new Stream('php://temp', 'rw');
            $body->write('x=1');
            $body->rewind();
            $request = $request->withBody($body);
        }

        return $this->executeFrontendSubRequest($request, $this->requestContext($frontendUserId));
    }

    private function requestContext(int $frontendUserId): InternalRequestContext
    {
        $context = new InternalRequestContext();

        return $frontendUserId > 0 ? $context->withFrontendUserId($frontendUserId) : $context;
    }

    #[Test]
    public function visitorsAreAskedToLogIn(): void
    {
        $html = (string)$this->fetch()->getBody();

        self::assertStringContainsString('My bookings', $html);
        self::assertStringContainsString('Please log in to see your bookings.', $html);
        self::assertStringNotContainsString('Cancel this booking', $html);
    }

    #[Test]
    public function listsTheActiveBookingsOfTheUserOnly(): void
    {
        // Booking of the Open day opens on 2026-06-01
        $this->freezeTime('2026-06-02 12:00:00');
        $mine = $this->bookingService->book($this->newBooking('grace@example.org', 2, 1), $this->occurrence(1), $this->settingsOf(3));
        $this->bookingService->book($this->newBooking('other@example.org', 1, 2), $this->occurrence(2), $this->settingsOf(4));
        $cancelled = $this->bookingService->book($this->newBooking('grace@example.org', 1, 1), $this->occurrence(3), $this->settingsOf(6));
        $this->bookingService->cancel($cancelled, $this->settingsOf(6));

        $html = (string)$this->fetch(1)->getBody();

        self::assertSame(1, substr_count($html, 'Cancel this booking'));
        self::assertStringContainsString('Workshop', $html);
        self::assertStringNotContainsString('Lecture', $html, 'the booking of another user');
        self::assertStringNotContainsString('Open day', $html, 'cancelled bookings are not listed');
        self::assertStringContainsString('tx_golbeventsbooking_mybookings%5Bbooking%5D=' . $mine->getUid(), $html);
        self::assertStringContainsString('Confirmed', $html);
    }

    #[Test]
    public function emptyListShowsAHint(): void
    {
        $html = (string)$this->fetch(1)->getBody();

        self::assertStringContainsString('You have no upcoming bookings.', $html);
    }

    #[Test]
    public function userCancelsTheirOwnBookingOnPost(): void
    {
        $mine = $this->bookingService->book($this->newBooking('grace@example.org', 2, 1), $this->occurrence(1), $this->settingsOf(3));

        $response = $this->cancel((int)$mine->getUid(), 1);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(Booking::STATUS_CANCELLED, $this->bookingRows()[0]['status']);
    }

    #[Test]
    public function getRequestsAndOtherUsersCannotCancel(): void
    {
        $mine = $this->bookingService->book($this->newBooking('grace@example.org', 2, 1), $this->occurrence(1), $this->settingsOf(3));

        self::assertSame(303, $this->cancel((int)$mine->getUid(), 1, 'GET')->getStatusCode());
        self::assertSame(Booking::STATUS_CONFIRMED, $this->bookingRows()[0]['status'], 'a GET does not cancel');

        self::assertSame(303, $this->cancel((int)$mine->getUid(), 2)->getStatusCode());
        self::assertSame(Booking::STATUS_CONFIRMED, $this->bookingRows()[0]['status'], 'another user cannot cancel');

        self::assertSame(303, $this->cancel((int)$mine->getUid(), 0)->getStatusCode());
        self::assertSame(Booking::STATUS_CONFIRMED, $this->bookingRows()[0]['status'], 'a visitor cannot cancel');
    }
}
