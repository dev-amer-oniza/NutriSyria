<?php
namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;
enum RecipeStatus: string implements HasLabel, HasColor {
    case Draft     = 'draft';
    case Published = 'published';
    case Archived  = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft     => 'مسودة',
            self::Published => 'منشورة',
            self::Archived  => 'مؤرشفة',
        };
    }
    public function getColor(): string
    {
        return match ($this) {
            self::Draft     => 'gray',
            self::Published => 'success',
            self::Archived  => 'danger',
        };
    }
}
