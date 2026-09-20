-- Afrik Appart clean MySQL install script.
-- Run this once against an empty MySQL database, then configure the application .env.
-- This script creates schema and safe reference/draft data only; it does not import local reservations or credentials.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'customer',
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    locale VARCHAR(5) NOT NULL DEFAULT 'fr',
    email_booking_updates TINYINT(1) NOT NULL DEFAULT 1,
    email_marketing TINYINT(1) NOT NULL DEFAULT 0,
    email_newsletter TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (email VARCHAR(255) NOT NULL, token VARCHAR(255) NOT NULL, created_at TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sessions (id VARCHAR(255) NOT NULL, user_id BIGINT UNSIGNED NULL, ip_address VARCHAR(45) NULL, user_agent TEXT NULL, payload LONGTEXT NOT NULL, last_activity INT NOT NULL, PRIMARY KEY (id), KEY idx_sessions_user_id (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cache (`key` VARCHAR(255) NOT NULL, value MEDIUMTEXT NOT NULL, expiration INT NOT NULL, PRIMARY KEY (`key`), KEY idx_cache_expiration (expiration)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cache_locks (`key` VARCHAR(255) NOT NULL, owner VARCHAR(255) NOT NULL, expiration INT NOT NULL, PRIMARY KEY (`key`), KEY idx_cache_locks_expiration (expiration)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS jobs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, queue VARCHAR(255) NOT NULL, payload LONGTEXT NOT NULL, attempts TINYINT UNSIGNED NOT NULL, reserved_at INT UNSIGNED NULL, available_at INT UNSIGNED NOT NULL, created_at INT UNSIGNED NOT NULL, PRIMARY KEY (id), KEY idx_jobs_queue (queue)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS job_batches (id VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, total_jobs INT NOT NULL, pending_jobs INT NOT NULL, failed_jobs INT NOT NULL, failed_job_ids LONGTEXT NOT NULL, options MEDIUMTEXT NULL, cancelled_at INT NULL, created_at INT NOT NULL, finished_at INT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS failed_jobs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, uuid VARCHAR(255) NOT NULL, connection TEXT NOT NULL, queue TEXT NOT NULL, payload LONGTEXT NOT NULL, exception LONGTEXT NOT NULL, failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uk_failed_jobs_uuid (uuid)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS establishments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    country_code VARCHAR(2) NOT NULL DEFAULT 'BJ',
    city VARCHAR(255) NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'XOF',
    description TEXT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(255) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_establishments_slug (slug),
    KEY idx_establishments_slug_city (slug, city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS properties (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    establishment_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    currency VARCHAR(3) NOT NULL DEFAULT 'XOF',
    nightly_rate_xof DECIMAL(12,2) NOT NULL DEFAULT 0,
    nightly_rate_eur DECIMAL(12,2) NOT NULL DEFAULT 0,
    max_guests INT NOT NULL DEFAULT 2,
    bedrooms INT NOT NULL DEFAULT 1,
    bathrooms INT NOT NULL DEFAULT 1,
    beds INT NOT NULL DEFAULT 1,
    address VARCHAR(255) NULL,
    city VARCHAR(255) NULL,
    country VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    summary TEXT NULL,
    description LONGTEXT NULL,
    cover_image VARCHAR(255) NULL,
    minimum_stay INT NOT NULL DEFAULT 1,
    metadata JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_properties_slug (slug),
    KEY idx_properties_establishment_status (establishment_id, status),
    KEY idx_properties_published_city (is_published, city),
    KEY idx_properties_slug (slug),
    CONSTRAINT fk_properties_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS amenities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    category VARCHAR(255) NOT NULL DEFAULT 'general',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_amenities_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_amenities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id BIGINT UNSIGNED NOT NULL,
    amenity_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_property_amenities (property_id, amenity_id),
    KEY idx_property_amenities_property (property_id),
    KEY idx_property_amenities_amenity (amenity_id),
    CONSTRAINT fk_property_amenities_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    CONSTRAINT fk_property_amenities_amenity FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(255) NULL,
    width INT NULL,
    height INT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_cover TINYINT(1) NOT NULL DEFAULT 0,
    metadata JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_property_images_property_order (property_id, sort_order),
    KEY idx_property_images_cover (property_id, is_cover),
    CONSTRAINT fk_property_images_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id BIGINT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    nightly_rate_xof DECIMAL(12,2) NOT NULL DEFAULT 0,
    nightly_rate_eur DECIMAL(12,2) NOT NULL DEFAULT 0,
    minimum_stay INT NOT NULL DEFAULT 1,
    rule_type VARCHAR(50) NOT NULL DEFAULT 'standard',
    metadata JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rate_rules_property_date (property_id, effective_from),
    CONSTRAINT fk_rate_rules_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_guests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(255) NULL,
    country VARCHAR(255) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id BIGINT UNSIGNED NOT NULL,
    guest_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    reservation_ref VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    adults INT NOT NULL DEFAULT 1,
    children INT NOT NULL DEFAULT 0,
    infants INT NOT NULL DEFAULT 0,
    currency VARCHAR(3) NOT NULL DEFAULT 'XOF',
    email VARCHAR(255) NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    fees DECIMAL(12,2) NOT NULL DEFAULT 0,
    taxes DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    source VARCHAR(50) NOT NULL DEFAULT 'website',
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_reservation_ref (reservation_ref),
    KEY idx_reservation_status_date (status, check_in),
    KEY idx_reservation_property_range (property_id, check_in, check_out),
    KEY idx_reservation_ref (reservation_ref),
    KEY idx_reservation_email (email),
    CONSTRAINT fk_reservations_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    CONSTRAINT fk_reservations_guest FOREIGN KEY (guest_id) REFERENCES reservation_guests(id) ON DELETE SET NULL,
    CONSTRAINT fk_reservations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(255) NOT NULL,
    provider_reference VARCHAR(255) NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'XOF',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    idempotency_key VARCHAR(255) NULL,
    payload JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payment_attempts_provider_status (provider, status),
    KEY idx_payment_attempts_idempotency (idempotency_key),
    CONSTRAINT fk_payment_attempts_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_price_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency VARCHAR(3) NOT NULL DEFAULT 'XOF',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_reservation_price_lines_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS currency_configs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    currency_code VARCHAR(3) NOT NULL,
    name VARCHAR(255) NOT NULL,
    xof_per_eur DECIMAL(12,6) NOT NULL DEFAULT 655.957000,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    effective_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_currency_configs_code (currency_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_availability_blocks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_availability_blocks_property_dates (property_id, start_date, end_date),
    CONSTRAINT fk_admin_blocks_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS external_calendar_feeds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    provider VARCHAR(30) NOT NULL DEFAULT 'other',
    url TEXT NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_sync_at TIMESTAMP NULL,
    last_successful_sync_at TIMESTAMP NULL,
    sync_interval_minutes INT NOT NULL DEFAULT 15,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_external_calendar_feeds_property_enabled (property_id, is_enabled),
    CONSTRAINT fk_external_calendar_feeds_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS external_calendar_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    feed_id BIGINT UNSIGNED NOT NULL,
    uid VARCHAR(255) NOT NULL,
    summary VARCHAR(255) NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_external_calendar_events_uid (uid),
    KEY idx_external_calendar_events_feed_date (feed_id, start_date),
    CONSTRAINT fk_external_calendar_events_feed FOREIGN KEY (feed_id) REFERENCES external_calendar_feeds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cleaning_visits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    property_id BIGINT UNSIGNED NOT NULL,
    reservation_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    assignee_name VARCHAR(255) NOT NULL,
    scheduled_at DATETIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 120,
    status VARCHAR(50) NOT NULL DEFAULT 'scheduled',
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    instructions TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cleaning_visits_property_schedule (property_id, scheduled_at),
    CONSTRAINT fk_cleaning_visits_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    CONSTRAINT fk_cleaning_visits_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL,
    CONSTRAINT fk_cleaning_visits_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cleaning_schedule_shares (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    token VARCHAR(64) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    view_mode VARCHAR(10) NOT NULL,
    property_ids JSON NOT NULL,
    assignee_name VARCHAR(255) NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cleaning_schedule_shares_token (token),
    KEY idx_cleaning_schedule_shares_tenant (tenant_id),
    CONSTRAINT fk_cleaning_schedule_shares_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(255) NOT NULL,
    value TEXT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'string',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_settings_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template VARCHAR(255) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'queued',
    payload JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_outbox_status_recipient (status, recipient_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    model_type VARCHAR(255) NULL,
    model_id BIGINT UNSIGNED NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_model (model_type, model_id),
    KEY idx_audit_logs_action (action),
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE establishments
    ADD COLUMN cover_image VARCHAR(255) NULL,
    ADD COLUMN address VARCHAR(255) NULL,
    ADD COLUMN latitude DECIMAL(10,7) NULL,
    ADD COLUMN longitude DECIMAL(10,7) NULL,
    ADD COLUMN google_maps_url VARCHAR(2048) NULL,
    ADD COLUMN review_channel VARCHAR(20) NOT NULL DEFAULT 'internal',
    ADD COLUMN google_review_url VARCHAR(2048) NULL,
    ADD COLUMN google_reviews_import_url VARCHAR(2048) NULL,
    ADD COLUMN google_reviews_last_sync_at DATETIME NULL,
    ADD COLUMN google_reviews_last_sync_error TEXT NULL,
    ADD COLUMN features JSON NULL,
    ADD COLUMN payment_methods JSON NULL;

ALTER TABLE properties
    ADD COLUMN property_type VARCHAR(50) NOT NULL DEFAULT 'apartment',
    ADD COLUMN calendar_export_token VARCHAR(64) NULL,
    ADD UNIQUE KEY uk_properties_calendar_export_token (calendar_export_token);

ALTER TABLE property_images ADD COLUMN room_tag VARCHAR(100) NULL;
ALTER TABLE external_calendar_feeds ADD COLUMN last_sync_error VARCHAR(2048) NULL, ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'stale';

CREATE TABLE IF NOT EXISTS property_features (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, property_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, description TEXT NULL,
    cost_xof DECIMAL(12,2) NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_property_features_property (property_id),
    CONSTRAINT fk_property_features_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, source VARCHAR(50) NOT NULL DEFAULT 'google', reviewer_name VARCHAR(255) NULL,
    rating TINYINT NOT NULL DEFAULT 5, review_text TEXT NULL, source_url VARCHAR(2048) NULL, reviewed_at TIMESTAMP NULL,
    property_id BIGINT UNSIGNED NULL,
    reservation_id BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_site_reviews_active (is_active, reviewed_at),
    KEY idx_site_reviews_property (property_id), UNIQUE KEY uk_site_reviews_reservation (reservation_id), CONSTRAINT fk_site_reviews_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
    CONSTRAINT fk_site_reviews_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_translations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, property_id BIGINT UNSIGNED NOT NULL, locale VARCHAR(5) NOT NULL,
    name VARCHAR(255) NULL, summary TEXT NULL, description LONGTEXT NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uk_property_translation_locale (property_id, locale),
    CONSTRAINT fk_property_translations_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS establishment_translations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, establishment_id BIGINT UNSIGNED NOT NULL, locale VARCHAR(5) NOT NULL,
    name VARCHAR(255) NULL, description TEXT NULL, features JSON NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uk_establishment_translation_locale (establishment_id, locale),
    CONSTRAINT fk_establishment_translations_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO amenities (name, slug, category) VALUES
    ('Wi-Fi', 'wifi', 'internet'),
    ('Parking', 'parking', 'parking'),
    ('Cuisine equipee', 'kitchen', 'kitchen'),
    ('Climatisation', 'air-conditioning', 'comfort'),
    ('Piscine', 'pool', 'recreation'),
    ('Terrasse', 'terrace', 'outdoor'),
    ('Concierge', 'concierge', 'service'),
    ('Lavage', 'laundry', 'service')
ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category);

INSERT INTO settings (`key`, value, type) VALUES
    ('site_name', 'Afrik Appart', 'string'),
    ('default_locale', 'fr', 'string'),
    ('secondary_locale', 'en', 'string'),
    ('default_currency', 'XOF', 'string'),
    ('eur_to_xof_rate', '655.957', 'decimal'),
    ('booking_mode', 'instant_confirmation', 'string'),
    ('payment_hold_minutes', '20', 'integer'),
    ('calendar_freshness_minutes', '15', 'integer')
ON DUPLICATE KEY UPDATE value = VALUES(value), type = VALUES(type);

INSERT INTO currency_configs (currency_code, name, xof_per_eur, is_enabled, effective_at) VALUES
    ('XOF', 'West African CFA Franc', 655.957000, 1, NOW()),
    ('EUR', 'Euro', 655.957000, 1, NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), xof_per_eur = VALUES(xof_per_eur), is_enabled = VALUES(is_enabled);


INSERT INTO establishments (name, slug, country_code, city, currency, description, email, phone, metadata) VALUES
    ('Afrik Appart Cotonou', 'afrik-appart-cotonou', 'BJ', 'Cotonou', 'XOF', 'Draft establishment placeholder awaiting owner validation.', '[TO BE PROVIDED]', '[TO BE PROVIDED]', JSON_OBJECT('draft', true, 'source', 'master_prompt'))
ON DUPLICATE KEY UPDATE name = VALUES(name), city = VALUES(city), currency = VALUES(currency), description = VALUES(description);

INSERT INTO properties (establishment_id, name, slug, status, is_published, currency, nightly_rate_xof, nightly_rate_eur, max_guests, bedrooms, bathrooms, beds, address, city, country, summary, description, cover_image, minimum_stay, metadata, calendar_export_token) 
VALUES
    (1, 'Appartement 401', 'appartement-401', 'draft', 1, 'XOF', 25000, 38.15, 2, 1, 1, 1, 'Draft address to be confirmed', 'Cotonou', 'Benin', 'Draft property summary awaiting confirmation.', 'Draft description to be replaced with owner-supplied content.', 'uploads/properties/appartement-401/cover.jpg', 2, JSON_OBJECT('source_folder', 'appartement 401', 'draft', true), REPLACE(UUID(), '-', '')),
    (1, 'Appartement 402', 'appartement-402', 'draft', 1, 'XOF', 32000, 48.78, 4, 2, 2, 2, 'Draft address to be confirmed', 'Cotonou', 'Benin', 'Draft property summary awaiting confirmation.', 'Draft description to be replaced with owner-supplied content.', 'uploads/properties/appartement-402/cover.jpg', 2, JSON_OBJECT('source_folder', 'appartement 402', 'draft', true), REPLACE(UUID(), '-', '')),
    (1, 'Appartement 403', 'appartement-403', 'draft', 1, 'XOF', 39000, 59.45, 4, 2, 2, 2, 'Draft address to be confirmed', 'Cotonou', 'Benin', 'Draft property summary awaiting confirmation.', 'Draft description to be replaced with owner-supplied content.', 'uploads/properties/appartement-403/cover.jpg', 2, JSON_OBJECT('source_folder', 'appartement 403', 'draft', true), REPLACE(UUID(), '-', '')),
    (1, 'Appartement 404', 'appartement-404', 'draft', 1, 'XOF', 45000, 68.58, 5, 3, 2, 3, 'Draft address to be confirmed', 'Cotonou', 'Benin', 'Draft property summary awaiting confirmation.', 'Draft description to be replaced with owner-supplied content.', 'uploads/properties/appartement-404/cover.jpg', 3, JSON_OBJECT('source_folder', 'appartement 404', 'draft', true), REPLACE(UUID(), '-', ''))
ON DUPLICATE KEY UPDATE name = VALUES(name), status = VALUES(status), nightly_rate_xof = VALUES(nightly_rate_xof), nightly_rate_eur = VALUES(nightly_rate_eur), city = VALUES(city), calendar_export_token = VALUES(calendar_export_token);

INSERT INTO property_images (property_id, file_path, file_name, mime_type, width, height, sort_order, is_cover, metadata) VALUES
    (1, 'uploads/properties/appartement-401/cover.jpg', 'cover.jpg', 'image/jpeg', 1600, 1200, 1, 1, JSON_OBJECT('source', 'appartement 401')),
    (2, 'uploads/properties/appartement-402/cover.jpg', 'cover.jpg', 'image/jpeg', 1600, 1200, 1, 1, JSON_OBJECT('source', 'appartement 402')),
    (3, 'uploads/properties/appartement-403/cover.jpg', 'cover.jpg', 'image/jpeg', 1600, 1200, 1, 1, JSON_OBJECT('source', 'appartement 403')),
    (4, 'uploads/properties/appartement-404/cover.jpg', 'cover.jpg', 'image/jpeg', 1600, 1200, 1, 1, JSON_OBJECT('source', 'appartement 404'))
ON DUPLICATE KEY UPDATE file_name = VALUES(file_name), mime_type = VALUES(mime_type), is_cover = VALUES(is_cover);

SET FOREIGN_KEY_CHECKS = 1;

