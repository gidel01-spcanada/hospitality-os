INSERT INTO establishments (name, slug, country_code, city, currency, description, email, phone, metadata) VALUES
    ('Afrik Appart Cotonou', 'afrik-appart-cotonou', 'BJ', 'Cotonou', 'XOF', 'Draft establishment placeholder awaiting owner validation.', '[TO BE PROVIDED]', '[TO BE PROVIDED]', JSON_OBJECT('draft', true, 'source', 'master_prompt'))
ON DUPLICATE KEY UPDATE name = VALUES(name), city = VALUES(city), currency = VALUES(currency), description = VALUES(description);

INSERT INTO properties (establishment_id, name, slug, status, is_published, currency, nightly_rate_xof, nightly_rate_eur, max_guests, bedrooms, bathrooms, beds, address, city, country, summary, description, cover_image, minimum_stay, metadata) 
VALUES
    (1, 'Appartement 401', 'appartement-401', 'draft', 1, 'XOF', 25000, 38.15, 2, 1, 1, 1, 'Draft address to be confirmed', 'Cotonou', 'Benin', 'Draft property summary awaiting confirmation.', 'Draft description to be replaced with owner-supplied content.', 'uploads/properties/appartement-401/cover.jpg', 2, JSON_OBJECT('source_folder', 'appartement 401', 'draft', true)),
    (1, 'Appartement 402', 'appartement-402', 'draft', 1, 'XOF', 32000, 48.78, 4, 2, 2, 2, 'Draft address to be confirmed', 'Cotonou', 'Benin', 'Draft property summary awaiting confirmation.', 'Draft description to be replaced with owner-supplied content.', 'uploads/properties/appartement-402/cover.jpg', 2, JSON_OBJECT('source_folder', 'appartement 402', 'draft', true)),
    (1, 'Appartement 403', 'appartement-403', 'draft', 1, 'XOF', 39000, 59.45, 4, 2, 2, 2, 'Draft address to be confirmed', 'Cotonou', 'Benin', 'Draft property summary awaiting confirmation.', 'Draft description to be replaced with owner-supplied content.', 'uploads/properties/appartement-403/cover.jpg', 2, JSON_OBJECT('source_folder', 'appartement 403', 'draft', true)),
    (1, 'Appartement 404', 'appartement-404', 'draft', 1, 'XOF', 45000, 68.58, 5, 3, 2, 3, 'Draft address to be confirmed', 'Cotonou', 'Benin', 'Draft property summary awaiting confirmation.', 'Draft description to be replaced with owner-supplied content.', 'uploads/properties/appartement-404/cover.jpg', 3, JSON_OBJECT('source_folder', 'appartement 404', 'draft', true))
ON DUPLICATE KEY UPDATE name = VALUES(name), status = VALUES(status), nightly_rate_xof = VALUES(nightly_rate_xof), nightly_rate_eur = VALUES(nightly_rate_eur), city = VALUES(city);

INSERT INTO property_images (property_id, file_path, file_name, mime_type, width, height, sort_order, is_cover, metadata) VALUES
    (1, 'uploads/properties/appartement-401/cover.jpg', 'cover.jpg', 'image/jpeg', 1600, 1200, 1, 1, JSON_OBJECT('source', 'appartement 401')),
    (2, 'uploads/properties/appartement-402/cover.jpg', 'cover.jpg', 'image/jpeg', 1600, 1200, 1, 1, JSON_OBJECT('source', 'appartement 402')),
    (3, 'uploads/properties/appartement-403/cover.jpg', 'cover.jpg', 'image/jpeg', 1600, 1200, 1, 1, JSON_OBJECT('source', 'appartement 403')),
    (4, 'uploads/properties/appartement-404/cover.jpg', 'cover.jpg', 'image/jpeg', 1600, 1200, 1, 1, JSON_OBJECT('source', 'appartement 404'))
ON DUPLICATE KEY UPDATE file_name = VALUES(file_name), mime_type = VALUES(mime_type), is_cover = VALUES(is_cover);
