<?php

namespace App\Support;

use RuntimeException;

/**
 * Re-encodes user-supplied images. Decoding to pixels and writing a fresh JPEG drops
 * EXIF/XMP (GPS coordinates, device serials) and anything smuggled after the image
 * data, and bounds what we serve back to other users.
 */
class ImageSanitizer
{
    /** Centre-crops to a square of at most $size px and returns JPEG bytes. */
    public static function squareJpeg(string $bytes, int $size = 512, int $quality = 85): string
    {
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            throw new RuntimeException('Unreadable image.');
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $target = min($size, $side);

        $dst = imagecreatetruecolor($target, $target);
        // Transparent PNG/WebP areas become white rather than black.
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $target, $target, $side, $side);

        ob_start();
        imagejpeg($dst, null, $quality);
        $out = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return $out;
    }

    /**
     * Keeps the aspect ratio, fits the longer side in $max px, applies the camera's EXIF
     * rotation first (the tag itself is dropped with the rest of the metadata).
     *
     * @return array{bytes: string, width: int, height: int}
     */
    public static function fitJpeg(string $bytes, int $max = 1600, int $quality = 82): array
    {
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            throw new RuntimeException('Unreadable image.');
        }
        $src = self::orient($src, $bytes);

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($tw, $th);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);

        ob_start();
        imagejpeg($dst, null, $quality);
        $out = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return ['bytes' => $out, 'width' => $tw, 'height' => $th];
    }

    /** EXIF Orientation 3/6/8 → upright pixels. */
    private static function orient(\GdImage $img, string $bytes): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($bytes, "\xFF\xD8")) {
            return $img;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $img;
        }
        $rotated = imagerotate($img, $angle, 0);
        if ($rotated === false) {
            return $img;
        }
        imagedestroy($img);

        return $rotated;
    }
}
