-- Growth Alignment V4 pricing reconfirmed against Sunil's approved frozen blueprint.
-- Full Report: Personal 4.99, New Joiner 19.95, Manager 49.95, Executive 99.95 USD.
-- Retest: Personal 2.99, New Joiner 9.95, Manager 29.95, Executive 49.95 USD.
-- Stripe Price IDs remain runtime settings because they are created in the LIVE Stripe account.

UPDATE assessment_tracks
SET price_minor = CASE track_key
    WHEN 'personal' THEN 499
    WHEN 'newjoiner' THEN 1995
    WHEN 'manager' THEN 4995
    WHEN 'executive' THEN 9995
    ELSE price_minor
END,
currency = 'USD',
updated_at = NOW()
WHERE track_key IN ('personal', 'newjoiner', 'manager', 'executive');

INSERT INTO global_settings (setting_key, setting_value, is_encrypted, updated_at) VALUES
('retest.wait_days', '90', 0, NOW()),
('retest.price_personal_minor', '299', 0, NOW()),
('retest.price_newjoiner_minor', '995', 0, NOW()),
('retest.price_manager_minor', '2995', 0, NOW()),
('retest.price_executive_minor', '4995', 0, NOW())
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    is_encrypted = VALUES(is_encrypted),
    updated_at = NOW();
