CREATE TABLE maintenance_visits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    maintenance_request_id BIGINT UNSIGNED NOT NULL,
    proposed_slots_json LONGTEXT NOT NULL,
    selected_start_at DATETIME NULL,
    duration_minutes SMALLINT UNSIGNED NULL,
    technician_name VARCHAR(120) NULL,
    technician_contact VARCHAR(200) NULL,
    status ENUM('proposed', 'scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'proposed',
    result_notes VARCHAR(1000) NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    completed_by_user_id BIGINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    cancelled_by_user_id BIGINT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancel_reason VARCHAR(500) NULL,
    PRIMARY KEY (id),
    KEY maintenance_visits_request_status_index (maintenance_request_id, status, selected_start_at),
    CONSTRAINT maintenance_visits_request_fk FOREIGN KEY (maintenance_request_id)
        REFERENCES maintenance_requests (id) ON DELETE RESTRICT,
    CONSTRAINT maintenance_visits_created_by_user_fk FOREIGN KEY (created_by_user_id)
        REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT maintenance_visits_completed_by_user_fk FOREIGN KEY (completed_by_user_id)
        REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT maintenance_visits_cancelled_by_user_fk FOREIGN KEY (cancelled_by_user_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
