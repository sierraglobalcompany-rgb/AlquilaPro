CREATE TABLE audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id BIGINT UNSIGNED NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    summary VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL,
    metadata_json TEXT NULL,
    PRIMARY KEY (id),
    KEY audit_log_occurred_index (occurred_at, id),
    KEY audit_log_actor_index (actor_user_id),
    KEY audit_log_entity_index (entity_type, entity_id, id),
    CONSTRAINT audit_log_actor_fk
        FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE notification_jobs
    MODIFY recipient_email VARCHAR(254) NULL,
    MODIFY recipient_name VARCHAR(120) NULL;
