<?php

namespace App\Modules\Portal\Services;

use App\Models\Customer;
use App\Models\CustomerUserLink;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatSale;
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
        'customer.portal.project.progress.view' => 'Project progress',
    ];

    /** Capabilities an admin may grant to each kind of portal account. */
    public static function grantable(string $portal): array
    {
        return array_filter(self::GRANTABLE, fn ($label, $key) => str_starts_with($key, $portal.'.portal.'), ARRAY_FILTER_USE_BOTH);
    }

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

    /** Units the customer holds through a current sale or a confirmed booking. */
    public static function customerFlatIds(Customer $customer): Collection
    {
        return FlatSale::query()->where('customer_id', $customer->id)->whereIn('status', FlatSale::ACTIVE)->pluck('flat_id')
            ->merge(FlatBooking::query()->where('customer_id', $customer->id)->where('status', 'confirmed')->pluck('flat_id'))->unique()->values();
    }

    public static function customerProjectIds(Customer $customer): Collection
    {
        return Flat::query()->whereIn('id', self::customerFlatIds($customer))->distinct()->pluck('project_id');
    }

    /** Projects whose progress the customer may see: by role, or by a grant on the project or one of their units in it. */
    public static function customerProgressProjectIds(User $user, Customer $customer): Collection
    {
        $projects = self::customerProjectIds($customer);
        if ($user->can('customer.portal.project.progress.view')) {
            return $projects;
        }
        $flats = Flat::query()->whereIn('id', self::customerFlatIds($customer))->get(['id', 'project_id']);
        $grants = PortalAccessGrant::query()->current()->where('user_id', $user->id)->where('capability', 'customer.portal.project.progress.view')->get(['resource_type', 'resource_id']);

        return $projects->filter(fn ($projectId) => $grants->contains(fn ($g) => ($g->resource_type === 'project' && $g->resource_id === $projectId)
            || ($g->resource_type === 'flat' && $flats->where('project_id', $projectId)->contains('id', $g->resource_id))))->values();
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
