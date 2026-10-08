<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Domain extends Model
{
    protected $fillable = ['domain' , 'tenant_id'];

    protected $connection = 'central';

    public function tenant():BelongsTo{
        return $this->belongsTo(Tenant::class);
    }
}
