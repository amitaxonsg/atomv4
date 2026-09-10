SET NAMES utf8mb4;

START TRANSACTION;

-- Some JSON values were stored by json_encode(), so the en dash in
-- "Head–Heart Alignment" exists as the literal sequence \u2013.
-- Migration 017 handled literal UTF-8 text; this migration handles
-- the JSON-escaped representation as well.

UPDATE global_settings
SET setting_value =
    REPLACE(
      REPLACE(
        REPLACE(
          setting_value,
          CONCAT('Head', CHAR(92), 'u2013Heart Alignment'),
          'Growth Alignment'
        ),
        'Head–Heart Alignment',
        'Growth Alignment'
      ),
      'Head-Heart Alignment',
      'Growth Alignment'
    ),
    updated_at = NOW()
WHERE is_encrypted = 0
  AND (
       INSTR(setting_value, CONCAT('Head', CHAR(92), 'u2013Heart Alignment')) > 0
    OR setting_value LIKE '%Head–Heart Alignment%'
    OR setting_value LIKE '%Head-Heart Alignment%'
  );

UPDATE report_templates
SET free_content_json =
    REPLACE(
      REPLACE(
        REPLACE(
          free_content_json,
          CONCAT('Head', CHAR(92), 'u2013Heart Alignment'),
          'Growth Alignment'
        ),
        'Head–Heart Alignment',
        'Growth Alignment'
      ),
      'Head-Heart Alignment',
      'Growth Alignment'
    ),
    paid_content_json =
    REPLACE(
      REPLACE(
        REPLACE(
          paid_content_json,
          CONCAT('Head', CHAR(92), 'u2013Heart Alignment'),
          'Growth Alignment'
        ),
        'Head–Heart Alignment',
        'Growth Alignment'
      ),
      'Head-Heart Alignment',
      'Growth Alignment'
    ),
    updated_at = NOW()
WHERE free_content_json LIKE '%Head%Heart Alignment%'
   OR paid_content_json LIKE '%Head%Heart Alignment%';

UPDATE generated_reports
SET free_report_json =
    REPLACE(
      REPLACE(
        REPLACE(
          free_report_json,
          CONCAT('Head', CHAR(92), 'u2013Heart Alignment'),
          'Growth Alignment'
        ),
        'Head–Heart Alignment',
        'Growth Alignment'
      ),
      'Head-Heart Alignment',
      'Growth Alignment'
    ),
    paid_report_json =
    REPLACE(
      REPLACE(
        REPLACE(
          paid_report_json,
          CONCAT('Head', CHAR(92), 'u2013Heart Alignment'),
          'Growth Alignment'
        ),
        'Head–Heart Alignment',
        'Growth Alignment'
      ),
      'Head-Heart Alignment',
      'Growth Alignment'
    ),
    pdf_path = NULL,
    pdf_generated_at = NULL,
    updated_at = NOW()
WHERE free_report_json LIKE '%Head%Heart Alignment%'
   OR paid_report_json LIKE '%Head%Heart Alignment%';

-- Explicitly guarantee the live landing title.
UPDATE global_settings
SET setting_value = JSON_SET(
      setting_value,
      '$.title', 'Growth Alignment',
      '$.cardTitlePrefix', 'Growth Alignment:'
    ),
    updated_at = NOW()
WHERE setting_key = 'questionnaire.landing'
  AND JSON_VALID(setting_value);

COMMIT;
