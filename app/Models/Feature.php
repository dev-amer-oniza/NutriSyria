<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $fillable = ['name', 'code', 'type', 'description'];
    protected $connection = 'central' ;

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_feature')
                    ->withPivot('value')
                    ->withTimestamps();
    }
}