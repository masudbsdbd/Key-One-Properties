<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasPublicId;

    public function isSuperAdmin(): bool
    {
        return $this->name === config('keyone.super_admin_role');
    }
}
