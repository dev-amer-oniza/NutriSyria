<?php

namespace App\Models;

use App\Enums\CriterionSource;
use Illuminate\Database\Eloquent\Model;

/** One change to a person's criteria: who, which, from what, to what (null = back to default). */
class UserCriterionChange extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'central';

    protected $table = 'user_criteria_changes';

    protected $fillable = ['user_id', 'criterion', 'source', 'old_value', 'new_value', 'changed_by'];

    protected function casts(): array
    {
        return ['source' => CriterionSource::class, 'old_value' => 'float', 'new_value' => 'float'];
    }
}
