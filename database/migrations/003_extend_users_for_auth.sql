ALTER TABLE users
    ADD COLUMN permissions_json TEXT NULL AFTER is_active,
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER permissions_json,
    ADD COLUMN failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER must_change_password,
    ADD COLUMN locked_until DATETIME NULL AFTER failed_login_attempts,
    ADD COLUMN last_login_at DATETIME NULL AFTER locked_until,
    ADD COLUMN reset_token_hash CHAR(64) NULL AFTER last_login_at,
    ADD COLUMN reset_expires_at DATETIME NULL AFTER reset_token_hash,
    ADD UNIQUE KEY users_reset_token_hash_unique (reset_token_hash);
