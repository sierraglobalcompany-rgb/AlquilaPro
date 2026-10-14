CREATE TABLE documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id BIGINT UNSIGNED NULL,
    lease_id BIGINT UNSIGNED NULL,
    type VARCHAR(40) NOT NULL,
    title VARCHAR(190) NOT NULL,
    content_json LONGTEXT NULL,
    stored_name VARCHAR(80) NULL,
    mime_type VARCHAR(80) NULL,
    sha256 CHAR(64) NULL,
    size_bytes BIGINT UNSIGNED NULL,
    supersedes_document_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY documents_property_current_index (property_id, type, id),
    KEY documents_lease_current_index (lease_id, type, id),
    KEY documents_created_by_user_index (created_by_user_id),
    UNIQUE KEY documents_supersedes_unique (supersedes_document_id),
    CONSTRAINT documents_scope_xor_check
        CHECK ((property_id IS NULL) <> (lease_id IS NULL)),
    CONSTRAINT documents_payload_check
        CHECK ((content_json IS NOT NULL AND stored_name IS NULL)
            OR (content_json IS NULL AND stored_name IS NOT NULL)),
    CONSTRAINT documents_file_metadata_check
        CHECK ((stored_name IS NULL AND mime_type IS NULL AND sha256 IS NULL AND size_bytes IS NULL)
            OR (stored_name IS NOT NULL AND mime_type IS NOT NULL AND sha256 IS NOT NULL AND size_bytes IS NOT NULL)),
    CONSTRAINT documents_property_fk FOREIGN KEY (property_id) REFERENCES properties (id) ON DELETE RESTRICT,
    CONSTRAINT documents_lease_fk FOREIGN KEY (lease_id) REFERENCES leases (id) ON DELETE RESTRICT,
    CONSTRAINT documents_supersedes_fk FOREIGN KEY (supersedes_document_id) REFERENCES documents (id) ON DELETE RESTRICT,
    CONSTRAINT documents_created_by_user_fk FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
