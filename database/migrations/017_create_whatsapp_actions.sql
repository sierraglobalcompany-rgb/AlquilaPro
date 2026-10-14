CREATE TABLE whatsapp_actions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    context_type VARCHAR(32) NOT NULL,
    context_id BIGINT UNSIGNED NOT NULL,
    recipient_phone_snapshot VARCHAR(15) NOT NULL,
    template_key VARCHAR(80) NOT NULL,
    message_snapshot TEXT NOT NULL,
    url_snapshot TEXT NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    opened_by_user_id BIGINT UNSIGNED NULL,
    opened_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY whatsapp_actions_recent_index (created_at, id),
    KEY whatsapp_actions_context_index (context_type, context_id, created_at),
    CONSTRAINT whatsapp_actions_created_by_user_fk FOREIGN KEY (created_by_user_id)
        REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT whatsapp_actions_opened_by_user_fk FOREIGN KEY (opened_by_user_id)
        REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
