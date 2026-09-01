-- Restore Sunil's exact V4 landing image supplied and deployed on 2026-08-27.
-- This supersedes the older Head-Heart CMS assignment without altering the
-- source image. The frontend keeps the separate overlay copy at the bottom.

INSERT INTO media_library
  (file_name, storage_path, mime_type, file_size, width, height, alt_text,
   focal_x, focal_y, variants_json, created_at, updated_at)
SELECT
  'reflection-portrait.png',
  '/media/stages/reflection-portrait.png',
  'image/png',
  0,
  853,
  1280,
  'A thoughtful professional reflecting on how they think',
  52,
  50,
  JSON_OBJECT('source', 'repository', 'supplied_image_commit', '2196068424d0fd0756232cb89da3dfcc5ec1cab2'),
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1
  FROM media_library
  WHERE storage_path = '/media/stages/reflection-portrait.png'
);

UPDATE content_stages AS stage
JOIN media_library AS media
  ON media.storage_path = '/media/stages/reflection-portrait.png'
SET
  stage.desktop_media_id = media.id,
  stage.image_alt = 'A thoughtful professional reflecting on how they think',
  stage.focal_x = 52,
  stage.focal_y = 50,
  stage.overlay_strength = 0,
  stage.updated_at = NOW()
WHERE stage.stage_key = 'version';
