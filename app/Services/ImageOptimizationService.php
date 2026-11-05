<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageOptimizationService
{
    /**
     * Simpan file asli (Hi-Res) dan buat versi web-optimized (thumbnail/ringan).
     *
     * @param UploadedFile $file
     * @param string $directory Sub-direktori penyimpanan (contoh: 'players/photos')
     * @param string $disk Disk storage ('private' atau 'public')
     * @param int $maxDimension Dimensi maksimal untuk versi web-optimized (px)
     * @param int $quality Kualitas kompresi JPEG (1-100)
     * @return array{original_path: string, optimized_path: string, size_bytes: int}
     */
    public function storeWithOptimizedVariant(
        UploadedFile $file,
        string $directory,
        string $disk = 'private',
        int $maxDimension = 800,
        int $quality = 82
    ): array {
        $extension = strtolower($file->getClientOriginalExtension());
        $baseName = Str::uuid()->toString();

        // 1. Simpan berkas asli resolusi tinggi (Hi-Res)
        $originalFileName = "{$baseName}_original.{$extension}";
        $originalPath = Storage::disk($disk)->putFileAs($directory, $file, $originalFileName);

        // 2. Jika bukan gambar yang didukung GD, return berkas asli saja
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
            return [
                'original_path' => $originalPath,
                'optimized_path' => $originalPath,
                'size_bytes' => $file->getSize(),
            ];
        }

        // 3. Buat versi Web-Optimized menggunakan PHP GD
        $optimizedPath = $this->createOptimizedImage(
            $file->getRealPath(),
            $extension,
            $disk,
            $directory,
            "{$baseName}_optimized.jpg",
            $maxDimension,
            $quality
        );

        return [
            'original_path' => $originalPath,
            'optimized_path' => $optimizedPath ?: $originalPath,
            'size_bytes' => $file->getSize(),
        ];
    }

    /**
     * Memproses gambar dengan GD dan menyimpannya ke disk storage.
     */
    protected function createOptimizedImage(
        string $realPath,
        string $extension,
        string $disk,
        string $directory,
        string $outputFileName,
        int $maxDimension,
        int $quality
    ): ?string {
        if (!extension_loaded('gd')) {
            return null;
        }

        try {
            $sourceImage = match ($extension) {
                'jpg', 'jpeg' => @imagecreatefromjpeg($realPath),
                'png' => @imagecreatefrompng($realPath),
                'webp' => @imagecreatefromwebp($realPath),
                default => null,
            };

            if (!$sourceImage) {
                return null;
            }

            $origWidth = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);

            $newWidth = $origWidth;
            $newHeight = $origHeight;

            if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
                if ($origWidth > $origHeight) {
                    $newHeight = (int) round(($origHeight * $maxDimension) / $origWidth);
                    $newWidth = $maxDimension;
                } else {
                    $newWidth = (int) round(($origWidth * $maxDimension) / $origHeight);
                    $newHeight = $maxDimension;
                }
            }

            $targetImage = imagecreatetruecolor($newWidth, $newHeight);

            // Background putih bersih jika input PNG transparan dikonversi ke JPEG
            $white = imagecolorallocate($targetImage, 255, 255, 255);
            imagefilledrectangle($targetImage, 0, 0, $newWidth, $newHeight, $white);

            imagecopyresampled(
                $targetImage,
                $sourceImage,
                0, 0, 0, 0,
                $newWidth,
                $newHeight,
                $origWidth,
                $origHeight
            );

            // Simpan ke temporary buffer
            ob_start();
            imagejpeg($targetImage, null, $quality);
            $imageData = ob_get_clean();

            imagedestroy($sourceImage);
            imagedestroy($targetImage);

            if (!$imageData) {
                return null;
            }

            $finalPath = "{$directory}/{$outputFileName}";
            Storage::disk($disk)->put($finalPath, $imageData);

            return $finalPath;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}

