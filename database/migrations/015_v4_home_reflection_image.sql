-- Persist the V4 landing-page visual selected in production on 2026-08-27.
-- Clearing the CMS desktop media assignment makes the public configuration
-- use the deployed fallback asset: /media/stages/reflection-portrait.png.

UPDATE content_stages
SET desktop_media_id = NULL
WHERE stage_key = 'version';
