<?php
declare(strict_types=1);

namespace AtomGlobal\Services;

use AtomGlobal\Database;

final class MediaService
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/svg+xml' => 'svg',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    public function __construct(private Database $db, private array $config) {}

    public function upload(array $file, array $metadata, int $adminId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('A valid image upload is required.');
        }

        $maxBytes = (int) ($_ENV['MAX_UPLOAD_BYTES'] ?? 10 * 1024 * 1024);
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > $maxBytes) {
            throw new \InvalidArgumentException('The image exceeds the configured upload limit.');
        }

        $temporary = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($temporary)) {
            throw new \InvalidArgumentException('The uploaded file could not be verified.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary) ?: '';
        if (!isset(self::EXTENSIONS[$mime])) {
            throw new \InvalidArgumentException('Only JPG, PNG, GIF, WebP, AVIF, SVG and ICO images are allowed.');
        }

        if ($mime === 'image/svg+xml') {
            $svg = file_get_contents($temporary) ?: '';
            if (preg_match('/<(script|foreignObject)|on[a-z]+\s*=|javascript:/i', $svg)) {
                throw new \InvalidArgumentException('The SVG contains unsafe active content.');
            }
        }

        $storage = rtrim((string) $this->config['storage'], '/') . '/media';
        if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) {
            throw new \RuntimeException('Media storage is unavailable.');
        }

        $extension = self::EXTENSIONS[$mime];
        $name = bin2hex(random_bytes(20)) . '.' . $extension;
        $target = $storage . '/' . $name;

        if (!move_uploaded_file($temporary, $target)) {
            throw new \RuntimeException('The image could not be stored.');
        }
        chmod($target, 0640);

        $width = null;
        $height = null;
        if ($mime !== 'image/svg+xml') {
            $dimensions = @getimagesize($target);
            if (is_array($dimensions)) {
                $width = (int) ($dimensions[0] ?? 0) ?: null;
                $height = (int) ($dimensions[1] ?? 0) ?: null;
            }
        }

        $publicPrefix = rtrim((string) ($_ENV['MEDIA_PUBLIC_PREFIX'] ?? '/media-uploads'), '/');
        $publicUrl = $publicPrefix . '/' . $name;
        $id = $this->db->insert(
            'INSERT INTO media_library (file_name, storage_path, mime_type, file_size, width, height, alt_text, focal_x, focal_y, variants_json, uploaded_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                basename((string) ($file['name'] ?? $name)),
                $publicUrl,
                $mime,
                $size,
                $width,
                $height,
                trim((string) ($metadata['altText'] ?? '')),
                max(0, min(100, (float) ($metadata['focalX'] ?? 50))),
                max(0, min(100, (float) ($metadata['focalY'] ?? 50))),
                json_encode(['diskName' => $name], JSON_UNESCAPED_SLASHES),
                $adminId,
            ]
        );

        return [
            'id' => $id,
            'url' => $publicUrl,
            'mimeType' => $mime,
            'fileSize' => $size,
            'width' => $width,
            'height' => $height,
            'altText' => trim((string) ($metadata['altText'] ?? '')),
        ];
    }

    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT id, file_name fileName, storage_path url, mime_type mimeType, file_size fileSize, width, height, alt_text altText, focal_x focalX, focal_y focalY, created_at createdAt FROM media_library ORDER BY created_at DESC LIMIT 250'
        );
    }
}
