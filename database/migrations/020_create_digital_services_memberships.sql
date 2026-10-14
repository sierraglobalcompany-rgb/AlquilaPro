CREATE TABLE digital_services (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(1000) NOT NULL,
    current_price DECIMAL(12,2) NOT NULL,
    duration_days INT UNSIGNED NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY digital_services_active_name_index (active, name, id),
    KEY digital_services_created_by_user_index (created_by_user_id),
    CONSTRAINT digital_services_price_check CHECK (current_price >= 0),
    CONSTRAINT digital_services_duration_check CHECK (duration_days > 0),
    CONSTRAINT digital_services_active_check CHECK (active IN (0, 1)),
    CONSTRAINT digital_services_created_by_user_fk
        FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_memberships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_id BIGINT UNSIGNED NOT NULL,
    lease_id BIGINT UNSIGNED NOT NULL,
    accepted_by_user_id BIGINT UNSIGNED NOT NULL,
    price_snapshot DECIMAL(12,2) NOT NULL,
    duration_days_snapshot INT UNSIGNED NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    accepted_at DATETIME NOT NULL,
    renews_membership_id BIGINT UNSIGNED NULL,
    ended_at DATETIME NULL,
    charge_decision VARCHAR(16) NULL,
    charge_decided_by_user_id BIGINT UNSIGNED NULL,
    charge_decided_at DATETIME NULL,
    charge_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY service_memberships_charge_unique (charge_id),
    KEY service_memberships_service_fk_index (service_id),
    KEY service_memberships_lease_service_dates_index (lease_id, service_id, starts_on, ends_on, ended_at),
    KEY service_memberships_accepted_by_user_index (accepted_by_user_id, accepted_at),
    KEY service_memberships_renewal_index (renews_membership_id),
    KEY service_memberships_charge_decided_by_user_index (charge_decided_by_user_id),
    KEY service_memberships_pending_charge_index (charge_decision, accepted_at, id),
    CONSTRAINT service_memberships_price_check CHECK (price_snapshot >= 0),
    CONSTRAINT service_memberships_duration_check CHECK (duration_days_snapshot > 0),
    CONSTRAINT service_memberships_dates_check CHECK (ends_on >= starts_on),
    CONSTRAINT service_memberships_charge_decision_check
        CHECK (charge_decision IS NULL OR charge_decision IN ('create', 'skip')),
    CONSTRAINT service_memberships_charge_fields_check CHECK (
        (charge_decision IS NULL AND charge_decided_at IS NULL AND charge_id IS NULL)
        OR (charge_decision = 'create' AND charge_decided_at IS NOT NULL AND charge_id IS NOT NULL)
        OR (charge_decision = 'skip' AND charge_decided_at IS NOT NULL AND charge_id IS NULL)
    ),
    CONSTRAINT service_memberships_service_fk
        FOREIGN KEY (service_id) REFERENCES digital_services (id) ON DELETE RESTRICT,
    CONSTRAINT service_memberships_lease_fk
        FOREIGN KEY (lease_id) REFERENCES leases (id) ON DELETE RESTRICT,
    CONSTRAINT service_memberships_accepted_by_user_fk
        FOREIGN KEY (accepted_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT service_memberships_renews_membership_fk
        FOREIGN KEY (renews_membership_id) REFERENCES service_memberships (id) ON DELETE RESTRICT,
    CONSTRAINT service_memberships_charge_decided_by_user_fk
        FOREIGN KEY (charge_decided_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT service_memberships_charge_fk
        FOREIGN KEY (charge_id) REFERENCES charges (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
