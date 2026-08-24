INSERT INTO amenities (name, slug, category) VALUES
    ('Wi‑Fi', 'wifi', 'internet'),
    ('Parking', 'parking', 'parking'),
    ('Cuisine équipée', 'kitchen', 'kitchen'),
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
