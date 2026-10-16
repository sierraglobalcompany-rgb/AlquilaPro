ALTER TABLE users
    ADD COLUMN username VARCHAR(80) NULL AFTER email,
    ADD COLUMN document_number VARCHAR(80) NULL AFTER username,
    ADD UNIQUE KEY users_username_unique (username),
    ADD UNIQUE KEY users_document_number_unique (document_number);
