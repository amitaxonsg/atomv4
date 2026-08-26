-- Growth Alignment V4 frozen blueprint (confirmed by Sunil, 2026-08-25).
-- This migration adds the data foundation required by V4. The CMS screens
-- for these fields are intentionally deferred to the next phase.

CREATE TABLE IF NOT EXISTS report_commitments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  generated_report_id BIGINT UNSIGNED NOT NULL UNIQUE,
  commitment_text TEXT NOT NULL,
  check_in_date DATE NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_commitment_report FOREIGN KEY (generated_report_id)
    REFERENCES generated_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO global_settings (setting_key, setting_value, is_encrypted, updated_at) VALUES
('product.name', 'Growth Alignment', 0, NOW()),
('reports.commitment_heading', 'My 90-day development commitment', 0, NOW()),
('reports.commitment_prompt', 'Choose one or two development areas and write down the action you will practise consistently.', 0, NOW()),
('reports.coach_heading', 'Talk to a Coach', 0, NOW()),
('reports.coach_body', 'Turn your report into a focused development plan with an Atom Global coach.', 0, NOW()),
('reports.coach_primary_name', 'Reeta Nathwani', 0, NOW()),
('reports.coach_primary_email', 'reeta.nathwani@atomglobal.com', 0, NOW()),
('reports.coach_secondary_name', 'Sunil Setpaul', 0, NOW()),
('reports.coach_secondary_email', 'sunil.setpaul@atomglobal.com', 0, NOW()),
('reports.payment_wording', 'Secure payment unlocks your private Full Development Report.', 0, NOW()),
('reports.payment_wording_location', 'lite', 0, NOW()),
('retest.wait_days', '90', 0, NOW()),
('retest.price_personal_minor', '299', 0, NOW()),
('retest.price_newjoiner_minor', '995', 0, NOW()),
('retest.price_manager_minor', '2995', 0, NOW()),
('retest.price_executive_minor', '4995', 0, NOW()),
-- Stripe Price IDs are identifiers, not secrets. They are intentionally empty
-- until live V4 products are created; the secret key and webhook secret remain
-- encrypted settings transferred through the V3 integration script.
('stripe.retest_price_personal', '', 0, NOW()),
('stripe.retest_price_newjoiner', '', 0, NOW()),
('stripe.retest_price_manager', '', 0, NOW()),
('stripe.retest_price_executive', '', 0, NOW())
ON DUPLICATE KEY UPDATE updated_at = updated_at;

UPDATE assessment_tracks SET price_minor = CASE track_key
  WHEN 'personal' THEN 499
  WHEN 'newjoiner' THEN 1995
  WHEN 'manager' THEN 4995
  WHEN 'executive' THEN 9995
  ELSE price_minor
END, currency = 'USD', updated_at = NOW()
WHERE track_key IN ('personal', 'newjoiner', 'manager', 'executive');

UPDATE assessment_track_settings ats
JOIN assessment_tracks t ON t.id = ats.track_id
SET ats.public_title = CONCAT('Growth Alignment: ', t.name),
    ats.question_count = 40,
    ats.section_count = 10,
    ats.updated_at = NOW()
WHERE t.track_key IN ('personal', 'newjoiner', 'manager', 'executive');

UPDATE email_templates
SET subject = REPLACE(REPLACE(subject, 'Head–Heart Alignment', 'Growth Alignment'), 'Head-Heart Alignment', 'Growth Alignment'),
    html_body = REPLACE(REPLACE(html_body, 'Head–Heart Alignment', 'Growth Alignment'), 'Head-Heart Alignment', 'Growth Alignment'),
    text_body = REPLACE(REPLACE(text_body, 'Head–Heart Alignment', 'Growth Alignment'), 'Head-Heart Alignment', 'Growth Alignment'),
    updated_at = NOW()
WHERE subject LIKE '%Head%Heart%Alignment%'
   OR html_body LIKE '%Head%Heart%Alignment%'
   OR text_body LIKE '%Head%Heart%Alignment%';

UPDATE seo_pages
SET page_title = REPLACE(REPLACE(page_title, 'Head–Heart Alignment', 'Growth Alignment'), 'Head-Heart Alignment', 'Growth Alignment'),
    meta_description = REPLACE(REPLACE(meta_description, 'Head–Heart Alignment', 'Growth Alignment'), 'Head-Heart Alignment', 'Growth Alignment'),
    og_title = REPLACE(REPLACE(og_title, 'Head–Heart Alignment', 'Growth Alignment'), 'Head-Heart Alignment', 'Growth Alignment'),
    og_description = REPLACE(REPLACE(og_description, 'Head–Heart Alignment', 'Growth Alignment'), 'Head-Heart Alignment', 'Growth Alignment'),
    heading = REPLACE(REPLACE(heading, 'Head–Heart Alignment', 'Growth Alignment'), 'Head-Heart Alignment', 'Growth Alignment'),
    introductory_content = REPLACE(REPLACE(introductory_content, 'Head–Heart Alignment', 'Growth Alignment'), 'Head-Heart Alignment', 'Growth Alignment'),
    updated_at = NOW();
