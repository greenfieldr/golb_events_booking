<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Domain\Model\Dto;

use Greenfieldr\GolbEventsBooking\Domain\Model\Booking;
use TYPO3\CMS\Extbase\Attribute\Validate;

/**
 * The data of the booking form. Validated by the Extbase property
 * validators below and by the BookingRequestValidator (seats, privacy,
 * spam checks, custom rules), then copied to a Booking.
 */
class BookingRequest
{
    /**
     * Uid of the booked occurrence
     */
    #[Validate(validator: 'NumberRange', options: ['minimum' => 1])]
    protected int $occurrence = 0;

    protected string $salutation = '';

    #[Validate(validator: 'NotEmpty')]
    #[Validate(validator: 'StringLength', options: ['maximum' => 255])]
    protected string $firstName = '';

    #[Validate(validator: 'NotEmpty')]
    #[Validate(validator: 'StringLength', options: ['maximum' => 255])]
    protected string $lastName = '';

    #[Validate(validator: 'NotEmpty')]
    #[Validate(validator: 'EmailAddress')]
    protected string $email = '';

    #[Validate(validator: 'StringLength', options: ['maximum' => 255])]
    protected string $phone = '';

    #[Validate(validator: 'StringLength', options: ['maximum' => 255])]
    protected string $company = '';

    #[Validate(validator: 'NumberRange', options: ['minimum' => 1])]
    protected int $seats = 1;

    #[Validate(validator: 'StringLength', options: ['maximum' => 5000])]
    protected string $note = '';

    protected bool $privacy = false;

    /**
     * Honeypot: a field hidden from humans, must stay empty
     */
    protected string $website = '';

    /**
     * Timestamp the form was rendered, for the minimum fill time
     */
    protected int $startedAt = 0;

    public function getOccurrence(): int
    {
        return $this->occurrence;
    }

    public function setOccurrence(int $occurrence): void
    {
        $this->occurrence = $occurrence;
    }

    public function getSalutation(): string
    {
        return $this->salutation;
    }

    public function setSalutation(string $salutation): void
    {
        $this->salutation = in_array($salutation, [Booking::SALUTATION_MR, Booking::SALUTATION_MS, Booking::SALUTATION_MX], true) ? $salutation : '';
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = trim($firstName);
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = trim($lastName);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = trim($email);
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = trim($phone);
    }

    public function getCompany(): string
    {
        return $this->company;
    }

    public function setCompany(string $company): void
    {
        $this->company = trim($company);
    }

    public function getSeats(): int
    {
        return $this->seats;
    }

    public function setSeats(int $seats): void
    {
        $this->seats = $seats;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): void
    {
        $this->note = trim($note);
    }

    public function isPrivacy(): bool
    {
        return $this->privacy;
    }

    public function setPrivacy(bool $privacy): void
    {
        $this->privacy = $privacy;
    }

    public function getWebsite(): string
    {
        return $this->website;
    }

    public function setWebsite(string $website): void
    {
        $this->website = $website;
    }

    public function getStartedAt(): int
    {
        return $this->startedAt;
    }

    public function setStartedAt(int $startedAt): void
    {
        $this->startedAt = $startedAt;
    }

    /**
     * Copies the participant data to a booking.
     */
    public function applyTo(Booking $booking, \DateTimeImmutable $now): void
    {
        $booking->setSalutation($this->salutation);
        $booking->setFirstName($this->firstName);
        $booking->setLastName($this->lastName);
        $booking->setEmail($this->email);
        $booking->setPhone($this->phone);
        $booking->setCompany($this->company);
        $booking->setSeats($this->seats);
        $booking->setNote($this->note);
        $booking->setPrivacyAcceptedAt($this->privacy ? $now : null);
    }
}
