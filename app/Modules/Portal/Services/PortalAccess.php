<?php

namespace App\Modules\Portal\Services;

use App\Models\Customer;
use App\Models\CustomerUserLink;
use App\Models\Flat;
use App\Models\Investor;
use App\Models\InvestorFlatAgreement;
use App\Models\InvestorUserLink;
use App\Models\PortalAccessAudit;
use App\Models\PortalAccessGrant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Who a portal user is and what they may see. Identity comes only from a usable link; a capability is
 * open through the user's role or a current grant on the resource (or its project). Association is
 * checked separately: a grant never opens a unit or project the investor holds nothing in.
 */
class PortalAccess
{
    /** Capabilities that roles do not open by default; they are granted per project or unit. */
    public const GRANTABLE = [
        'investor.portal.project.financials.view' => 'Project financials',
        'investor.portal.cost.material.view' => 'Material cost',
        'investor.portal.cost.labour.view' => 'Labour cost',
        'investor.portal.cost.contractor.view' => 'Contractor cost',
        'investor.portal.cost.expense.view' => 'Project expenses',
    ];

    public static function investorFor(?User $user): ?Investor
    {
        if (! $user || $user->account_type !== 'investor' || ! $user->isActive()) {
            return null;
        }
        $link = InvestorUserLink::query()->where('user_id', $user->id)->usable()->latest('id')->first();
        $investor = $link ? Investor::query()->find($link->investor_id) : null;

        return $investor && $investor->status === 'active' ? $investor : null;
    }

    public static function customerFor(?User $user): ?Customer
    {
        if (! $user || $user->account_type !== 'customer' || ! $user->isActive()) {
            return null;
        }
        $link = CustomerUserLink::query()->where('user_id', $user->id)->usable()->latest('id')->first();

        return $link ? Customer::query()->find($link->customer_id) : null;
    }

    public static function homeRoute(User $user): string
    {
        return match ($user->account_type) {
            'investor' => route('investor.dashboard'),
            'customer' => route('customer.dashboard'),
            default => route('dashboard'),
        };
    }

    /** Units the investor holds or held through an approved agreement. */
    public static function investorFlatIds(Investor $investor): Collection
    {
        return InvestorFlatAgreement::query()->where('investor_id', $investor->id)->whereIn('status', ['active', 'closed'])->distinct()->pluck('flat_id');
    }

    public static function investorProjectIds(Investor $investor): Collection
    {
        return InvestorFlatAgreement::query()->where('investor_id', $investor->id)->whereIn('status', ['active', 'closed'])->distinct()->pluck('project_id');
    }

    public static function allows(User $user, string $capability, ?Model $resource = null): bool
    {
        if ($user->can($capability)) {
            return true;
        }
        if (! $resource) {
            return false;
        }
        $targets = match (true) {
            $resource instanceof Flat => [['flat', $resource->id], ['project', $resource->project_id]],
            $resource instanceof Project => [['project', $resource->id]],
            default => [],
        };

        return PortalAccessGrant::query()->current()->where('user_id', $user->id)->where('capability', $capability)
            ->where(fn ($q) => collect($targets)->each(fn ($t) => $q->orWhere(fn ($w) => $w->where('resource_type', $t[0])->where('resource_id', $t[1]))))
            ->exists();
    }

    public static function audit(?User $user, string $portal, string $action, ?Model $resource = null, string $result = 'ok', ?Request $request = null): void
    {
        $ip = ($request ?? request())->ip();
        PortalAccessAudit::query()->create([
            'user_id' => $user?->id, 'portal_type' => $portal, 'action' => $action,
            'resource_type' => $resource ? class_basename($resource) : null, 'resource_id' => $resource?->getKey(), 'result' => $result,
            'ip_hash' => $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
        ]);
    }

    /** Per-user cache namespace; bumping it drops every cached portal figure of that user at once. */
    public static function cacheKey(User $user, string $key): string
    {
        return 'portal:'.$user->id.':'.Cache::get('portal:v:'.$user->id, 0).':'.$key;
    }

    public static function forgetCache(int $userId): void
    {
        Cache::forever('portal:v:'.$userId, (int) Cache::get('portal:v:'.$userId, 0) + 1);
    }
}
