CREATE TABLE community_incidents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lease_id BIGINT UNSIGNED NOT NULL,
    category VARCHAR(80) NOT NULL,
    occurred_on DATE NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'open',
    resolution_notes TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    closed_by_user_id BIGINT UNSIGNED NULL,
    closed_at DATETIME NULL,
    voided_by_user_id BIGINT UNSIGNED NULL,
    voided_at DATETIME NULL,
    void_reason VARCHAR(1000) NULL,
    PRIMARY KEY (id),
    KEY community_incidents_lease_occurred_index (lease_id, occurred_on, id),
    KEY community_incidents_status_created_index (status, created_at, id),
    KEY community_incidents_user_created_index (created_by_user_id, created_at, id),
    KEY community_incidents_closed_by_user_index (closed_by_user_id),
    KEY community_incidents_voided_by_user_index (voided_by_user_id),
    CONSTRAINT community_incidents_status_check
        CHECK (status IN ('open', 'reviewed', 'closed', 'voided')),
    CONSTRAINT community_incidents_closed_fields_check
        CHECK (status <> 'closed' OR (resolution_notes IS NOT NULL AND closed_by_user_id IS NOT NULL AND closed_at IS NOT NULL)),
    CONSTRAINT community_incidents_voided_fields_check
        CHECK (status <> 'voided' OR (void_reason IS NOT NULL AND voided_by_user_id IS NOT NULL AND voided_at IS NOT NULL)),
    CONSTRAINT community_incidents_lease_fk FOREIGN KEY (lease_id) REFERENCES leases (id) ON DELETE RESTRICT,
    CONSTRAINT community_incidents_created_by_user_fk FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT community_incidents_closed_by_user_fk FOREIGN KEY (closed_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT community_incidents_voided_by_user_fk FOREIGN KEY (voided_by_user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
