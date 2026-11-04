..  include:: /Includes.rst.txt

..  _privacy:

=======
Privacy
=======

..  _privacy-data:

Stored data
===========

A booking stores the data the participant enters: salutation, first and
last name, email address, phone, company, the number of seats and a note,
plus the time the privacy checkbox was ticked (``privacy_accepted_at``),
the booked event and date, the status with its timestamps and, for logged
in users, the uid of the frontend user. No IP addresses or user agents
are stored.

The booking form requires the privacy checkbox. Link your privacy policy
through the constant ``privacyPageUid`` (:ref:`configuration-constants`)
and describe the processing there: the data is used to manage the
booking, to inform the participant by email and to inform the organizer.

..  _privacy-optin:

Double opt-in
=============

Guests confirm their email address through the link of the opt-in mail
(extension setting ``doubleOptIn``). Until then the booking is pending;
after the deadline (``pendingHours``) the cleanup command cancels it.
The link carries a random token stored with the booking, not the email
address or the uid.

..  _privacy-retention:

Retention and anonymization
===========================

Set the extension setting ``retentionDays`` and run the cleanup command
regularly (:ref:`installation-scheduler`). The command removes the
personal data of bookings whose date is older than the retention period:
names, email, phone, company, note, the frontend user and the token are
cleared and the booking is marked anonymized. Date, seats and status are
kept for statistics. Delete the records in the backend if you want to
remove them entirely.

Mails are sent through the mail configuration of the installation; the
extension does not store sent mails.
