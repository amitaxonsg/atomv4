SET NAMES utf8mb4;

START TRANSACTION;

-- Global public/CMS settings, including stored questionnaire landing
-- configuration and branded email footer text.
UPDATE global_settings
SET setting_value =
      REPLACE(
        REPLACE(setting_value,
          'Head–Heart Alignment', 'Growth Alignment'),
          'Head-Heart Alignment', 'Growth Alignment'
      ),
    updated_at = NOW()
WHERE is_encrypted = 0
  AND (
    setting_value LIKE '%Head–Heart Alignment%'
    OR setting_value LIKE '%Head-Heart Alignment%'
  );


-- Email subjects and branded message bodies.
UPDATE email_templates
SET template_name =
      REPLACE(
        REPLACE(template_name,
          'Head–Heart Alignment', 'Growth Alignment'),
          'Head-Heart Alignment', 'Growth Alignment'
      ),
    subject =
      REPLACE(
        REPLACE(subject,
          'Head–Heart Alignment', 'Growth Alignment'),
          'Head-Heart Alignment', 'Growth Alignment'
      ),
    html_body =
      REPLACE(
        REPLACE(html_body,
          'Head–Heart Alignment', 'Growth Alignment'),
          'Head-Heart Alignment', 'Growth Alignment'
      ),
    text_body =
      REPLACE(
        REPLACE(text_body,
          'Head–Heart Alignment', 'Growth Alignment'),
          'Head-Heart Alignment', 'Growth Alignment'
      ),
    updated_at = NOW()
WHERE template_name LIKE '%Head–Heart Alignment%'
   OR template_name LIKE '%Head-Heart Alignment%'
   OR subject LIKE '%Head–Heart Alignment%'
   OR subject LIKE '%Head-Heart Alignment%'
   OR html_body LIKE '%Head–Heart Alignment%'
   OR html_body LIKE '%Head-Heart Alignment%'
   OR text_body LIKE '%Head–Heart Alignment%'
   OR text_body LIKE '%Head-Heart Alignment%';


-- Public SEO / AEO / structured-data copy.
UPDATE seo_pages
SET page_title =
      REPLACE(REPLACE(page_title,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    meta_description =
      REPLACE(REPLACE(meta_description,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    og_title =
      REPLACE(REPLACE(og_title,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    og_description =
      REPLACE(REPLACE(og_description,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    heading =
      REPLACE(REPLACE(heading,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    introductory_content =
      REPLACE(REPLACE(introductory_content,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    faq_json =
      REPLACE(REPLACE(faq_json,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    structured_data_json =
      REPLACE(REPLACE(structured_data_json,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    updated_at = NOW();


-- CMS stage text / accessibility text.
UPDATE content_stages
SET image_alt =
      REPLACE(REPLACE(image_alt,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    headline =
      REPLACE(REPLACE(headline,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    supporting_text =
      REPLACE(REPLACE(supporting_text,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    updated_at = NOW()
WHERE image_alt LIKE '%Head–Heart Alignment%'
   OR image_alt LIKE '%Head-Heart Alignment%'
   OR headline LIKE '%Head–Heart Alignment%'
   OR headline LIKE '%Head-Heart Alignment%'
   OR supporting_text LIKE '%Head–Heart Alignment%'
   OR supporting_text LIKE '%Head-Heart Alignment%';


-- Assessment titles/descriptions.
UPDATE assessment_tracks
SET name =
      REPLACE(REPLACE(name,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    description =
      REPLACE(REPLACE(description,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    updated_at = NOW()
WHERE name LIKE '%Head–Heart Alignment%'
   OR name LIKE '%Head-Heart Alignment%'
   OR description LIKE '%Head–Heart Alignment%'
   OR description LIKE '%Head-Heart Alignment%';


-- CMS-configurable assessment experience.
UPDATE assessment_track_settings
SET public_title =
      REPLACE(REPLACE(public_title,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    short_title =
      REPLACE(REPLACE(short_title,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    audience_label =
      REPLACE(REPLACE(audience_label,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    introductory_note =
      REPLACE(REPLACE(introductory_note,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    intro_headline =
      REPLACE(REPLACE(intro_headline,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    intro_body =
      REPLACE(REPLACE(intro_body,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    intro_offer =
      REPLACE(REPLACE(intro_offer,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    updated_at = NOW();


-- Report templates used for future Lite + Full Reports.
UPDATE report_templates
SET profile_name =
      REPLACE(REPLACE(profile_name,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    free_content_json =
      REPLACE(REPLACE(free_content_json,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    paid_content_json =
      REPLACE(REPLACE(paid_content_json,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    updated_at = NOW()
WHERE profile_name LIKE '%Head–Heart Alignment%'
   OR profile_name LIKE '%Head-Heart Alignment%'
   OR free_content_json LIKE '%Head–Heart Alignment%'
   OR free_content_json LIKE '%Head-Heart Alignment%'
   OR paid_content_json LIKE '%Head–Heart Alignment%'
   OR paid_content_json LIKE '%Head-Heart Alignment%';


-- Existing generated reports:
-- correct branding in stored Lite/Full JSON.
UPDATE generated_reports
SET free_report_json =
      REPLACE(REPLACE(free_report_json,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    paid_report_json =
      REPLACE(REPLACE(paid_report_json,
        'Head–Heart Alignment','Growth Alignment'),
        'Head-Heart Alignment','Growth Alignment'),
    updated_at = NOW()
WHERE free_report_json LIKE '%Head–Heart Alignment%'
   OR free_report_json LIKE '%Head-Heart Alignment%'
   OR paid_report_json LIKE '%Head–Heart Alignment%'
   OR paid_report_json LIKE '%Head-Heart Alignment%';

-- Regenerate every existing unlocked Full Report PDF from the corrected
-- V4 PDF renderer so no previously cached PDF can retain old branding.
UPDATE generated_reports
SET pdf_path = NULL,
    pdf_generated_at = NULL,
    updated_at = NOW()
WHERE is_unlocked = 1
  AND pdf_path IS NOT NULL;

COMMIT;
