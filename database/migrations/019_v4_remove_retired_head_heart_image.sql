-- Remove the retired Head-Heart asset from the isolated V4 media catalogue.
-- Migration 018 has already reassigned every V4 content stage to Sunil's
-- supplied reflection-portrait.png image, so this row is no longer needed.

DELETE FROM media_library
WHERE storage_path = '/media/stages/sunil-head-heart-v3.webp';
