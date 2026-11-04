<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Domain\Model;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * A booking of one date (occurrence) of an event page (table
 * tx_golbeventsbooking_domain_model_booking) by a guest or a frontend user.
 *
 * The event uid and the start of the booked date are stored redundantly so
 * a booking keeps its information when the occurrence is removed (the
 * editor moved or deleted the date); such bookings carry the dateRemoved
 * flag until an editor reassigns them.
 */
class Booking extends AbstractEntity
{
    /**
     * Awaiting something before it counts, see getPendingReason(); the seats
     * are reserved until pendingUntil.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    /**
     * On the waiting list because the date was full
     */
    public const STATUS_WAITING = 'waiting';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_WAITING, self::STATUS_CANCELLED];

    /**
     * Statuses whose seats count against the capacity
     */
    public const STATUSES_BLOCKING_SEATS = [self::STATUS_PENDING, self::STATUS_CONFIRMED];

    /**
     * Statuses of bookings that are not over
     */
    public const STATUSES_ACTIVE = [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_WAITING];

    /**
     * The guest has to confirm the booking through the link sent by email
     */
    public const PENDING_REASON_OPTIN = 'optin';

    public const SALUTATION_MR = 'mr';
    public const SALUTATION_MS = 'ms';
    public const SALUTATION_MX = 'mx';

    protected ?Event $event = null;

    protected ?Occurrence $occurrence = null;

    protected ?\DateTimeImmutable $start = null;

    protected string $status = self::STATUS_CONFIRMED;

    protected string $pendingReason = '';

    protected ?\DateTimeImmutable $pendingUntil = null;

    protected bool $dateRemoved = false;

    protected int $seats = 1;

    /**
     * Uid of the fe_users record, 0 for guests
     */
    protected int $frontendUser = 0;

    protected string $salutation = '';

    protected string $firstName = '';

    protected string $lastName = '';

    protected string $email = '';

    protected string $phone = '';

    protected string $company = '';

    protected string $note = '';

    protected ?\DateTimeImmutable $privacyAcceptedAt = null;

    protected string $token = '';

    protected ?\DateTimeImmutable $confirmedAt = null;

    protected ?\DateTimeImmutable $cancelledAt = null;

    protected ?\DateTimeImmutable $reminderSentAt = null;

    protected bool $anonymized = false;

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): void
    {
        $this->event = $event;
    }

    public function getOccurrence(): ?Occurrence
    {
        return $this->occurrence;
    }

    public function setOccurrence(?Occurrence $occurrence): void
    {
        $this->occurrence = $occurrence;
    }

    public function getStart(): ?\DateTimeImmutable
    {
        return $this->start;
    }

    public function setStart(?\DateTimeImmutable $start): void
    {
        $this->start = $start;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Whether the booking is not over (pending, confirmed or waiting).
     */
    public function isActive(): bool
    {
        return in_array($this->status, self::STATUSES_ACTIVE, true);
    }

    /**
     * Whether the seats of the booking count against the capacity of the
     * date (pending and confirmed bookings).
     */
    public function isBlockingSeats(): bool
    {
        return in_array($this->status, self::STATUSES_BLOCKING_SEATS, true);
    }

    public function getPendingReason(): string
    {
        return $this->pendingReason;
    }

    public function setPendingReason(string $pendingReason): void
    {
        $this->pendingReason = $pendingReason;
    }

    public function getPendingUntil(): ?\DateTimeImmutable
    {
        return $this->pendingUntil;
    }

    public function setPendingUntil(?\DateTimeImmutable $pendingUntil): void
    {
        $this->pendingUntil = $pendingUntil;
    }

    /**
     * Whether a pending booking has passed its deadline at the given time.
     */
    public function isPendingExpired(\DateTimeImmutable $now): bool
    {
        return $this->isPending() && $this->pendingUntil instanceof \DateTimeImmutable && $this->pendingUntil < $now;
    }

    public function isDateRemoved(): bool
    {
        return $this->dateRemoved;
    }

    public function setDateRemoved(bool $dateRemoved): void
    {
        $this->dateRemoved = $dateRemoved;
    }

    public function getSeats(): int
    {
        return max(1, $this->seats);
    }

    public function setSeats(int $seats): void
    {
        $this->seats = $seats;
    }

    public function getFrontendUser(): int
    {
        return $this->frontendUser;
    }

    public function setFrontendUser(int $frontendUser): void
    {
        $this->frontendUser = $frontendUser;
    }

    public function isGuest(): bool
    {
        return $this->frontendUser <= 0;
    }

    public function getSalutation(): string
    {
        return $this->salutation;
    }

    public function setSalutation(string $salutation): void
    {
        $this->salutation = $salutation;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function getCompany(): string
    {
        return $this->company;
    }

    public function setCompany(string $company): void
    {
        $this->company = $company;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): void
    {
        $this->note = $note;
    }

    public function getPrivacyAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->privacyAcceptedAt;
    }

    public function setPrivacyAcceptedAt(?\DateTimeImmutable $privacyAcceptedAt): void
    {
        $this->privacyAcceptedAt = $privacyAcceptedAt;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): void
    {
        $this->confirmedAt = $confirmedAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): void
    {
        $this->cancelledAt = $cancelledAt;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function setReminderSentAt(?\DateTimeImmutable $reminderSentAt): void
    {
        $this->reminderSentAt = $reminderSentAt;
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized;
    }

    public function setAnonymized(bool $anonymized): void
    {
        $this->anonymized = $anonymized;
    }
}
