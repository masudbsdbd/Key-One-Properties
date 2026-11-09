<?php

namespace App\Modules\Portal\Services;

use App\Models\Customer;
use App\Models\Investor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** The resolved portal identity of the current request; set by the portal middleware only. */
final class PortalContext
{
    public function __construct(
        public readonly User $user,
        public readonly string $portal,
        public readonly ?Investor $investor = null,
        public readonly ?Customer $customer = null,
    ) {}

    public function allows(string $capability, ?Model $resource = null): bool
    {
        return PortalAccess::allows($this->user, $capability, $resource);
    }
}
