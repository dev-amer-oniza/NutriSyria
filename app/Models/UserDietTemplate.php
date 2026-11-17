<?php

namespace App\Models;

use App\Enums\CriterionSource;
use Illuminate\Database\Eloquent\Model;

/**
 * A diet template chosen by a person or their nutritionist instead of the one their conditions bring.
 *
 * @property CriterionSource $source
 * @property string $template
 * @property ?string $note
 */
class UserDietTemplate extends Model
{
    protected $fillable = ['user_id', 'source', 'template', 'note', 'set_by'];

    protected function casts(): array
    {
        return ['source' => CriterionSource::class];
    }
}
