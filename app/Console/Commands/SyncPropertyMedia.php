<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Console\Command;

class SyncPropertyMedia extends Command
{
    protected $signature = 'property:sync-media {--source=} {--target=} {--manifest=} {--dry-run}';

    protected $description = 'Generate optimized media variants for the four supplied apartment folders and sync their metadata to the database.';

    protected array $expectedCounts = [
        'appartement 401' => 14,
        'appartement 402' => 12,
        'appartement 403' => 19,
        'appartement 404' => 13,
    ];

    protected array $propertyMap = [
        'appartement 401' => 'appartement-401',
        'appartement 402' => 'appartement-402',
        'appartement 403' => 'appartement-403',
        'appartement 404' => 'appartement-404',
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '1G');

        $source = rtrim((string) ($this->option('source') ?: dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'photo'), '/\\');
        $target = rtrim((string) ($this->option('target') ?: public_path('uploads/properties')), '/\\');
        $manifestPath = rtrim((string) ($this->option('manifest') ?: dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'seed-assets' . DIRECTORY_SEPARATOR . 'properties' . DIRECTORY_SEPARATOR . 'manifest.json'), '/\\');

        if (! is_dir($source)) {
            $this->error('Source photo directory not found: ' . $source);

            return self::FAILURE;
        }

        $this->ensureDirectory($target);
        $this->ensureDirectory(dirname($manifestPath));

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'source_directory' => $source,
            'properties' => [],
        ];

        foreach ($this->expectedCounts as $folderName => $expectedCount) {
            $folderPath = $source . DIRECTORY_SEPARATOR . $folderName;
            if (! is_dir($folderPath)) {
                $this->error("Missing expected source folder: {$folderPath}");

                return self::FAILURE;
            }

            $files = array_values(array_filter(scandir($folderPath) ?: [], function ($file) use ($folderPath) {
                if ($file === '.' || $file === '..') {
                    return false;
                }

                $fullPath = $folderPath . DIRECTORY_SEPARATOR . $file;

                return is_file($fullPath) && in_array(strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
            }));

            sort($files, SORT_NATURAL | SORT_FLAG_CASE);

            if (count($files) !== $expectedCount) {
                $this->error("Folder {$folderName} expected {$expectedCount} images but found " . count($files) . '.');

                return self::FAILURE;
            }

            $propertySlug = $this->propertyMap[$folderName];
            $propertyPath = $target . DIRECTORY_SEPARATOR . $propertySlug;
            $this->ensureDirectory($propertyPath);

            $property = Property::query()->firstOrCreate(
                ['slug' => $propertySlug],
                ['name' => ucfirst($propertySlug), 'status' => 'draft', 'is_published' => false]
            );
            PropertyImage::query()->where('property_id', $property->id)->delete();

            $entries = [];
            $sortOrder = 1;

            foreach ($files as $fileName) {
                $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                $safeBase = $this->safeStem($baseName);
                $sourcePath = $folderPath . DIRECTORY_SEPARATOR . $fileName;
                $primaryName = $safeBase . '-card.webp';
                $primaryTarget = $propertyPath . DIRECTORY_SEPARATOR . $primaryName;

                $this->generateVariantSet($sourcePath, $primaryTarget, $propertyPath, $safeBase);

                $relativePrimary = 'uploads/properties/' . $propertySlug . '/' . $primaryName;
                $variantPaths = [
                    'full' => 'uploads/properties/' . $propertySlug . '/' . $safeBase . '-full.webp',
                    'full_jpg' => 'uploads/properties/' . $propertySlug . '/' . $safeBase . '-full.jpg',
                    'large' => 'uploads/properties/' . $propertySlug . '/' . $safeBase . '-large.webp',
                    'large_jpg' => 'uploads/properties/' . $propertySlug . '/' . $safeBase . '-large.jpg',
                    'card' => 'uploads/properties/' . $propertySlug . '/' . $safeBase . '-card.webp',
                    'card_jpg' => 'uploads/properties/' . $propertySlug . '/' . $safeBase . '-card.jpg',
                    'thumb' => 'uploads/properties/' . $propertySlug . '/' . $safeBase . '-thumb.webp',
                    'thumb_jpg' => 'uploads/properties/' . $propertySlug . '/' . $safeBase . '-thumb.jpg',
                ];

                $entries[] = [
                    'property_slug' => $propertySlug,
                    'source_folder' => $folderName,
                    'source_file' => $fileName,
                    'relative_path' => $relativePrimary,
                    'variants' => $variantPaths,
                    'sort_order' => $sortOrder,
                    'is_cover' => $sortOrder === 1,
                ];

                PropertyImage::query()->updateOrCreate(
                    ['property_id' => $property->id, 'file_path' => $relativePrimary],
                    [
                        'file_name' => $primaryName,
                        'mime_type' => 'image/webp',
                        'width' => 800,
                        'height' => 600,
                        'sort_order' => $sortOrder,
                        'is_cover' => $sortOrder === 1,
                        'metadata' => [
                            'source_folder' => $folderName,
                            'source_file' => $fileName,
                            'variants' => $variantPaths,
                        ],
                    ]
                );

                if ($sortOrder === 1) {
                    $property->cover_image = $relativePrimary;
                    $property->save();
                }

                $sortOrder++;
            }

            $manifest['properties'][] = [
                'property_slug' => $propertySlug,
                'source_folder' => $folderName,
                'image_count' => count($entries),
                'images' => $entries,
            ];
        }

        if (! $this->option('dry-run')) {
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        }

        $this->info('Processed ' . count($manifest['properties']) . ' properties into ' . $target . '.');

        return self::SUCCESS;
    }

    protected function generateVariantSet(string $sourcePath, string $primaryTarget, string $propertyPath, string $safeBase): void
    {
        $image = $this->loadImage($sourcePath);
        if ($image === null) {
            throw new \RuntimeException('Unable to read image: ' . $sourcePath);
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);

        $variants = [
            'full' => 1800,
            'large' => 1200,
            'card' => 800,
            'thumb' => 320,
        ];

        foreach ($variants as $name => $maxWidth) {
            $scaled = $this->resizeToWidth($image, min($maxWidth, $sourceWidth));
            $webpPath = $propertyPath . DIRECTORY_SEPARATOR . $safeBase . '-' . $name . '.webp';
            $jpgPath = $propertyPath . DIRECTORY_SEPARATOR . $safeBase . '-' . $name . '.jpg';
            imagewebp($scaled, $webpPath, 82);
            imagejpeg($scaled, $jpgPath, 82);
            imagedestroy($scaled);
        }

        // Keep a consistent JPEG fallback while also creating a WebP primary preview.
        $fallback = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagecopy($fallback, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagejpeg($fallback, preg_replace('/\.webp$/i', '.jpg', $primaryTarget), 82);
        imagedestroy($fallback);
        imagedestroy($image);
    }

    protected function loadImage(string $sourcePath)
    {
        if (! is_readable($sourcePath)) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($sourcePath));
        if ($image === false) {
            return null;
        }

        if (function_exists('\exif_read_data')) {
            $exif = @\exif_read_data($sourcePath);
            if (is_array($exif) && isset($exif['Orientation'])) {
                switch ((int) $exif['Orientation']) {
                    case 3:
                        $image = imagerotate($image, 180, 0);
                        break;
                    case 6:
                        $image = imagerotate($image, -90, 0);
                        break;
                    case 8:
                        $image = imagerotate($image, 90, 0);
                        break;
                }
            }
        }

        return $image;
    }

    protected function resizeToWidth($image, int $maxWidth)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $targetWidth = min($width, $maxWidth);
        $targetHeight = (int) round(($height / $width) * $targetWidth);

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $resized;
    }

    protected function safeStem(string $value): string
    {
        $stem = preg_replace('/[^A-Za-z0-9]+/', '-', $value);
        $stem = trim((string) $stem, '-');

        return strtolower((string) $stem) ?: 'image';
    }

    protected function ensureDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}
