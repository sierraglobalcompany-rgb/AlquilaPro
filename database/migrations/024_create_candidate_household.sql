ALTER TABLE candidates
    ADD COLUMN pets_answered_at DATETIME NULL AFTER pets_declared;

CREATE TABLE candidate_household_occupants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidate_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(190) NOT NULL,
    relationship VARCHAR(100) NOT NULL,
    age_years TINYINT UNSIGNED NULL,
    occupation_or_activity VARCHAR(190) NULL,
    notes TEXT NULL,
    photo_stored_name VARCHAR(190) NULL,
    photo_mime_type VARCHAR(80) NULL,
    photo_size_bytes BIGINT UNSIGNED NULL,
    photo_sha256 CHAR(64) NULL,
    photo_consent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY candidate_household_occupants_candidate_id_index (candidate_id),
    CONSTRAINT candidate_household_occupants_candidate_id_fk
        FOREIGN KEY (candidate_id) REFERENCES candidates (id) ON DELETE CASCADE,
    CONSTRAINT candidate_household_occupants_age_check
        CHECK (age_years IS NULL OR age_years <= 120),
    CONSTRAINT candidate_household_occupants_photo_check
        CHECK (
            (photo_stored_name IS NULL AND photo_mime_type IS NULL AND photo_size_bytes IS NULL AND photo_sha256 IS NULL AND photo_consent_at IS NULL)
            OR (photo_stored_name IS NOT NULL AND photo_mime_type IS NOT NULL AND photo_size_bytes IS NOT NULL AND photo_sha256 IS NOT NULL AND photo_consent_at IS NOT NULL)
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE candidate_household_pets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidate_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    species VARCHAR(80) NOT NULL,
    breed VARCHAR(120) NULL,
    size_category VARCHAR(32) NULL,
    age_years TINYINT UNSIGNED NULL,
    vaccination_declaration VARCHAR(32) NULL,
    notes TEXT NULL,
    photo_stored_name VARCHAR(190) NULL,
    photo_mime_type VARCHAR(80) NULL,
    photo_size_bytes BIGINT UNSIGNED NULL,
    photo_sha256 CHAR(64) NULL,
    photo_consent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY candidate_household_pets_candidate_id_index (candidate_id),
    CONSTRAINT candidate_household_pets_candidate_id_fk
        FOREIGN KEY (candidate_id) REFERENCES candidates (id) ON DELETE CASCADE,
    CONSTRAINT candidate_household_pets_age_check
        CHECK (age_years IS NULL OR age_years <= 100),
    CONSTRAINT candidate_household_pets_vaccination_check
        CHECK (vaccination_declaration IS NULL OR vaccination_declaration IN ('declared_vaccinated', 'declared_not_vaccinated')),
    CONSTRAINT candidate_household_pets_photo_check
        CHECK (
            (photo_stored_name IS NULL AND photo_mime_type IS NULL AND photo_size_bytes IS NULL AND photo_sha256 IS NULL AND photo_consent_at IS NULL)
            OR (photo_stored_name IS NOT NULL AND photo_mime_type IS NOT NULL AND photo_size_bytes IS NOT NULL AND photo_sha256 IS NOT NULL AND photo_consent_at IS NOT NULL)
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
