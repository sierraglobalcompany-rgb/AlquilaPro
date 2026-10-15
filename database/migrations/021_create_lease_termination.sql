CREATE TABLE good_conduct_bonuses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lease_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    note VARCHAR(1000) NULL,
    decision VARCHAR(16) NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    decided_by_user_id BIGINT UNSIGNED NULL,
    decided_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY good_conduct_bonuses_lease_unique (lease_id),
    KEY good_conduct_bonuses_created_by_user_index (created_by_user_id),
    KEY good_conduct_bonuses_decided_by_user_index (decided_by_user_id),
    CONSTRAINT good_conduct_bonuses_amount_check CHECK (amount > 0),
    CONSTRAINT good_conduct_bonuses_decision_check
        CHECK (decision IS NULL OR decision IN ('approved', 'denied')),
    CONSTRAINT good_conduct_bonuses_lease_fk
        FOREIGN KEY (lease_id) REFERENCES leases (id) ON DELETE RESTRICT,
    CONSTRAINT good_conduct_bonuses_created_by_user_fk
        FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT good_conduct_bonuses_decided_by_user_fk
        FOREIGN KEY (decided_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lease_liquidations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lease_id BIGINT UNSIGNED NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    summary_note VARCHAR(2000) NULL,
    finalized_by_user_id BIGINT UNSIGNED NOT NULL,
    finalized_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY lease_liquidations_lease_unique (lease_id),
    KEY lease_liquidations_finalized_by_user_index (finalized_by_user_id),
    CONSTRAINT lease_liquidations_lease_fk
        FOREIGN KEY (lease_id) REFERENCES leases (id) ON DELETE RESTRICT,
    CONSTRAINT lease_liquidations_finalized_by_user_fk
        FOREIGN KEY (finalized_by_user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
