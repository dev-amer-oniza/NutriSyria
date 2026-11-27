<?php

namespace App\Models;

use App\Auth\Access;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /** Named, not only unguarded: the label column came after the first roles were made. */
    protected $fillable = ['name', 'guard_name', 'label'];

    /** Its Arabic name: as named in the panel, or the built-in one. */
    public function displayName(): string
    {
        return ($this->getAttribute('label') ?: null) ?? Access::builtInRoles()[$this->name] ?? $this->name;
    }

    public function isBuiltIn(): bool
    {
        return in_array($this->name, Access::PANEL_ROLES, true);
    }
}
