<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Event;

use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingRequest;
use Greenfieldr\GolbEventsBooking\Domain\Model\Dto\BookingSettings;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched while the booking form is validated, after the built-in
 * checks. Listeners add their own rules, e.g. a captcha or a block list,
 * by adding errors: every error is shown with the form and prevents the
 * booking.
 */
final class ValidateBookingEvent
{
    /**
     * @var list<array{property: string, message: string}>
     */
    private array $errors = [];

    public function __construct(
        private readonly BookingRequest $bookingRequest,
        private readonly BookingSettings $settings,
        private readonly ServerRequestInterface $request,
    ) {}

    public function getBookingRequest(): BookingRequest
    {
        return $this->bookingRequest;
    }

    public function getSettings(): BookingSettings
    {
        return $this->settings;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * @param string $property the form field the error belongs to, '' for the whole form
     * @param string $message the translated message to show
     */
    public function addError(string $message, string $property = ''): void
    {
        $this->errors[] = ['property' => $property, 'message' => $message];
    }

    /**
     * @return list<array{property: string, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
