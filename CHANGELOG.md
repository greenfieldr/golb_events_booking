# Changelog

All notable changes to the TYPO3 extension "golb_events_booking" are
documented in this file. The format is based on conventional commits; the
major version follows the supported TYPO3 major version (14.x.x for
TYPO3 14). The file is regenerated with git-cliff on every release.

## [Unreleased]

### Features

- Booking settings on event pages: enabled, capacity per date, seats
  per booking, waiting list, opening time, closing hours before the
  start and a notification address
- Booking records in a storage folder with the states pending,
  confirmed, waiting and cancelled; pending bookings carry a reason and
  a deadline and block their seats
- `BookingStateService` with the complete lifecycle (book, confirm,
  cancel, waiting list promotion, expiry, anonymization), guarded
  against overbooking, and PSR-14 events for every change
- Bookings keep their date: removed dates (Golb Events 14.1) flag the
  bookings and notify the organizer
- "Booking form" plugin (content element or `lib.golbEventsBooking.form`
  on event pages) with a date picker, prefilled data for frontend users,
  double opt-in for guests, honeypot and minimum fill time, the
  `ValidateBookingEvent` for custom rules, success, confirmation and
  cancellation pages and a route enhancer configuration
- Mails to participants and organizers as FluidEmail templates, the event
  attached as calendar file
- Commands `golbeventsbooking:reminders:send` and
  `golbeventsbooking:cleanup`
- "My bookings" plugin for frontend users
- Documentation for editors, integrators and developers

### Build and CI

- Extension skeleton with the Golb tooling: coding standards, PHPStan,
  Rector, Fractor, unit and functional tests, CI, release and
  documentation workflows
