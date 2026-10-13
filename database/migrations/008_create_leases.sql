CREATE TABLE leases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contract_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    activated_by_user_id BIGINT UNSIGNED NULL,
    activated_at DATETIME NOT NULL,
    ended_by_user_id BIGINT UNSIGNED NULL,
    ended_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY leases_contract_unique (contract_id),
    KEY leases_user_index (user_id),
    KEY leases_activated_by_user_index (activated_by_user_id),
    KEY leases_ended_by_user_index (ended_by_user_id),
    CONSTRAINT leases_contract_fk FOREIGN KEY (contract_id) REFERENCES contracts (id) ON DELETE RESTRICT,
    CONSTRAINT leases_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT leases_activated_by_user_fk FOREIGN KEY (activated_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT leases_ended_by_user_fk FOREIGN KEY (ended_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
