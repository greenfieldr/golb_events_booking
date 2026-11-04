#
# The columns of the booking table are derived from the TCA. The token is a
# passthrough field (never shown in the backend), so it is declared here,
# together with the indexes of the frequent lookups.
#
CREATE TABLE tx_golbeventsbooking_domain_model_booking (
    token varchar(40) DEFAULT '' NOT NULL,

    KEY token (token),
    KEY occurrence_status (occurrence, status),
    KEY frontend_user (frontend_user)
);
