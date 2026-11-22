<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\TaskBoard;

use InvalidArgumentException;

final class Screenshot
{
    public const MAX_BYTES = 4194304;

    /** Re-encode raster input; raw uploads and metadata are never served. */
    public static function normalize(string $bytes): array
    {
        if (strlen($bytes) > self::MAX_BYTES || $bytes === '') throw new InvalidArgumentException('Screenshot musi mieć od 1 bajta do 4 MiB.');
        $size = @getimagesizefromstring($bytes);
        if (!$size || !in_array($size['mime'], ['image/png', 'image/jpeg', 'image/webp'], true) || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 16000000) throw new InvalidArgumentException('Wybierz PNG, JPEG lub WebP do 16 milionów pikseli.');
        if (!function_exists('imagecreatefromstring')) throw new InvalidArgumentException('Obsługa screenshotów wymaga rozszerzenia GD.');
        $image = @imagecreatefromstring($bytes);
        if (!$image) throw new InvalidArgumentException('Nie można odczytać screenshotu.');
        imagesavealpha($image, true);
        ob_start();
        try { $ok = imagepng($image, null, 6); $png = ob_get_contents(); }
        finally { ob_end_clean(); imagedestroy($image); }
        if (!$ok || !is_string($png) || strlen($png) > self::MAX_BYTES) throw new InvalidArgumentException('Obraz po bezpiecznym przetworzeniu przekracza 4 MiB.');
        return ['name' => 'Screenshot.png', 'mime_type' => 'image/png', 'image_data' => $png, 'media_id' => null];
    }
}
