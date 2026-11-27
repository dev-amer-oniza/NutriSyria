<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

/**
 * One setup for every picture field in the panel (logo, dish, food, nutritionist photo).
 *
 * A phone photo is often 3–8 MB, more than many servers take (PHP's upload_max_filesize is
 * 2 MB by default). The browser shrinks it to at most 1600 px before sending, so a usual
 * photo arrives as a few hundred KB whatever the server's limit; 10 MB stays the ceiling.
 */
final class ImageUpload
{
    public const MAX_KB = 10240;

    public const MAX_PX = '1600';

    public static function apply(FileUpload $field): FileUpload
    {
        return $field
            ->image()
            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
            ->imageResizeMode('contain')
            ->imageResizeTargetWidth(self::MAX_PX)
            ->imageResizeTargetHeight(self::MAX_PX)
            ->imageResizeUpscale(false)
            ->maxSize(self::MAX_KB)
            ->validationMessages([
                'max' => 'الصورة أكبر من 10 ميغا. صغّرها أو اختر صورة تانية.',
                'mimes' => 'الصورة لازم تكون PNG أو JPG أو WebP.',
                'mimetypes' => 'الصورة لازم تكون PNG أو JPG أو WebP.',
            ]);
    }
}
