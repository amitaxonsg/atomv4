-- Remove the retired Head-Heart asset from the isolated V4 media catalogue.
-- Reassign first in case an older deployment normaliser restored the retired
-- V3 image after migration 018 ran. This makes the cleanup restart-safe and
-- satisfies both content-stage foreign keys before deleting the media row.

UPDATE content_stages AS stage
JOIN media_library AS replacement
  ON replacement.storage_path = '/media/stages/reflection-portrait.png'
SET
  stage.desktop_media_id = replacement.id,
  stage.mobile_media_id = NULL,
  stage.image_alt = 'A thoughtful professional reflecting on how they think',
  stage.focal_x = 52,
  stage.focal_y = 50,
  stage.overlay_strength = 0,
  stage.updated_at = NOW();

DELETE FROM media_library
WHERE storage_path = '/media/stages/sunil-head-heart-v3.webp';
