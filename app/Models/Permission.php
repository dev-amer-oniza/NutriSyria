<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    /** Named, not only unguarded: label and description came after the permissions were made. */
    protected $fillable = ['name', 'guard_name', 'label', 'description'];
}
