<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class PortalAccessAudit extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit rows are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit rows are immutable.'));
    }
}
