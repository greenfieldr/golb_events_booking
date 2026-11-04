<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events_booking".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEventsBooking\Tests\Functional\Fixtures\Mail;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

/**
 * Mail transport of the functional tests: keeps the sent mails in a static
 * list, so the tests can inspect them even when they were sent from the
 * container of a frontend sub request.
 */
final class CollectingTransport extends AbstractTransport
{
    /**
     * @var list<Email>
     */
    private static array $sent = [];

    /**
     * The TransportFactory passes the mail settings to the constructor
     *
     * @param array<string, mixed> $mailSettings
     */
    public function __construct(private readonly array $mailSettings = [])
    {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    public function getMailSettings(): array
    {
        return $this->mailSettings;
    }

    public static function reset(): void
    {
        self::$sent = [];
    }

    /**
     * @return list<Email>
     */
    public static function sent(): array
    {
        return self::$sent;
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        if ($original instanceof Email) {
            self::$sent[] = $original;
        }
    }

    public function __toString(): string
    {
        return 'collecting://';
    }
}
