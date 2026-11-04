# Golb Events Booking - Book Events The Page Way

[![CI](https://github.com/greenfieldr/golb_events_booking/actions/workflows/ci.yml/badge.svg)](https://github.com/greenfieldr/golb_events_booking/actions/workflows/ci.yml)
[![Coverage Status](https://coveralls.io/repos/github/greenfieldr/golb_events_booking/badge.svg?branch=main)](https://coveralls.io/github/greenfieldr/golb_events_booking?branch=main)
[![Latest release](https://img.shields.io/github/v/release/greenfieldr/golb_events_booking)](https://github.com/greenfieldr/golb_events_booking/releases)
[![License: GPL-3.0-or-later](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)

Golb Events Booking is an add-on to
[Golb Events](https://github.com/greenfieldr/golb_events) that lets
visitors book the dates of event pages. Booking works **with or without
frontend users**: logged in users book with their account, everybody else
books as guest with an email address and confirms the booking through a
link (double opt-in). Registration and login of frontend users are out of
scope; use any extension of your choice for that.

- Booking settings per event page: capacity per date, seats per booking,
  booking window, waiting list, notification address
- Booking form on the event page with a date picker for recurring events,
  honeypot and privacy checkbox; confirmation, waiting list and
  cancellation by email with signed links
- Bookings as records in a storage folder, editable in the backend, with
  states pending, confirmed, waiting and cancelled
- Bookings stay attached to their date: Golb Events 14.1 keeps occurrence
  records stable and reports removed dates, which are flagged on the
  bookings and reported to the organizer
- Commands for reminders and for cleaning up expired and old bookings
- Frontend plugin "My bookings" for logged in users
- PSR-14 events for validation and every state change

## Compatibility

| Golb Events Booking | Golb Events | Golb  | TYPO3 | PHP       |
|---------------------|-------------|-------|-------|-----------|
| 14.x                | 14.1+       | 14.x  | 14.x  | 8.2 - 8.5 |

The major version follows the supported TYPO3 major version.

## Installation

```bash
composer require greenfieldr/golb_events_booking
```

Then run "Analyze Database Structure", include the static TypoScript
"Golb Events Booking" after "Golb" and "Golb Events", set the storage
folder of the bookings and enable booking on the event pages. The full
documentation is in the [Documentation](Documentation/) folder.

## Development

```bash
composer install
composer ci             # coding standards, PHPStan, Rector, Fractor, unit and functional tests
composer fix            # apply automated fixes
composer ci:coverage    # test suites with code coverage (needs PCOV or Xdebug)
```

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/).
Releases are tagged `14.x.x` (TYPO3 14); the changelog is generated from the
commit history with git-cliff.

## License

GPL-3.0-or-later, see [LICENSE](LICENSE).
