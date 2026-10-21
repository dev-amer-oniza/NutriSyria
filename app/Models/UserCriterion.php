<?php

namespace App\Models;

use App\Enums\CriterionSource;
use Illuminate\Database\Eloquent\Model;

/** A criterion value set by a person or their nutritionist instead of the computed default. */
class UserCriterion extends Model
{
    protected $connection = 'central';

    protected $table = 'user_criteria';

    protected $fillable = ['user_id', 'criterion', 'source', 'value', 'note', 'set_by'];

    protected function casts(): array
    {
        return ['source' => CriterionSource::class, 'value' => 'float'];
    }
}
