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
}
