<?php

namespace App\Modules\Portal\Services;

use App\Models\Customer;
use App\Models\CustomerUserLink;
use App\Models\Flat;
use App\Models\Investor;
use App\Models\InvestorUserLink;
use App\Models\PortalAccessGrant;
use App\Models\Project;
use App\Models\User;
use App\Support\Workflow\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Portal accounts and their links. A new link is unusable until a second person verifies it;
 * revoking a link or a grant ends access on the next request and drops the user's sessions and cache.
 */
class PortalAccountService
{
    public function createAccount(Investor|Customer $owner, array $data, User $actor): InvestorUserLink|CustomerUserLink
    {
        return DB::transaction(function () use ($owner, $data, $actor) {
            $type = $owner instanceof Investor ? 'investor' : 'customer';
            $user = new User([
                'name' => $data['name'], 'email' => $data['email'] ?? null, 'mobile' => $data['mobile'] ?? null, 'status' => User::STATUS_ACTIVE,
            ]);
            $user->forceFill(['account_type' => $type, 'password' => $data['password'], 'password_changed_at' => now(), 'must_change_password' => true])->save();
            $user->syncRoles([$type === 'investor' ? 'Investor' : 'Customer']);

            $link = $this->linkModel($type)::query()->create([
                'user_id' => $user->id, "{$type}_id" => $owner->id, 'is_active' => true, 'created_by' => $actor->id,
            ]);
            activity('portal')->causedBy($actor)->performedOn($owner)->event('portal_account_created')
                ->withProperties(['account' => $user->name])->log('portal_account_created');

            return $link;
        });
    }

    public function verify(InvestorUserLink|CustomerUserLink $link, User $actor): void
    {
        DB::transaction(function () use ($link, $actor) {
            $link = $link::query()->lockForUpdate()->findOrFail($link->id);
            if ($link->revoked_at || $link->verified_at) {
                throw ValidationException::withMessages(['link' => 'This link was already decided.']);
            }
            Approval::ensureNotSelf($actor, $link->created_by, 'a portal link');
            $link->forceFill(['verified_at' => now(), 'verified_by' => $actor->id])->save();
            activity('portal')->causedBy($actor)->performedOn($this->owner($link))->event('portal_link_verified')
                ->withProperties(['account' => $link->user->name])->log('portal_link_verified');
        });
    }

    public function revoke(InvestorUserLink|CustomerUserLink $link, string $reason, User $actor): void
    {
        DB::transaction(function () use ($link, $reason, $actor) {
            $link = $link::query()->lockForUpdate()->findOrFail($link->id);
            if ($link->revoked_at) {
                return;
            }
            $link->forceFill(['is_active' => false, 'revoked_at' => now(), 'revoked_by' => $actor->id])->save();
            PortalAccessGrant::query()->where('user_id', $link->user_id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_by' => $actor->id]);
            $this->endSessions($link->user_id);
            activity('portal')->causedBy($actor)->performedOn($this->owner($link))->event('portal_link_revoked')
                ->withProperties(['account' => $link->user->name, 'reason' => $reason])->log('portal_link_revoked');
        });
    }

    public function resetPassword(InvestorUserLink|CustomerUserLink $link, string $password, User $actor): void
    {
        DB::transaction(function () use ($link, $password, $actor) {
            $link->user->forceFill(['password' => $password, 'password_changed_at' => now(), 'must_change_password' => true, 'remember_token' => Str::random(60)])->save();
            $this->endSessions($link->user_id);
            activity('portal')->causedBy($actor)->performedOn($this->owner($link))->event('portal_password_reset')
                ->withProperties(['account' => $link->user->name])->log('portal_password_reset');
        });
    }

    /** A grant can only point at a project or unit the investor holds through an approved agreement. */
    public function grant(InvestorUserLink $link, Project|Flat $resource, string $capability, ?string $expiresAt, User $actor): PortalAccessGrant
    {
        if (! array_key_exists($capability, PortalAccess::GRANTABLE)) {
            throw ValidationException::withMessages(['capability' => 'Select a capability.']);
        }
        if (! $link->isUsable()) {
            throw ValidationException::withMessages(['account' => 'The account link is not active.']);
        }
        $investor = $link->investor;
        $held = $resource instanceof Project ? PortalAccess::investorProjectIds($investor) : PortalAccess::investorFlatIds($investor);
        if (! $held->contains($resource->id)) {
            throw ValidationException::withMessages(['resource' => 'The investor holds nothing in this '.($resource instanceof Project ? 'project' : 'unit').'.']);
        }

        return DB::transaction(function () use ($link, $resource, $capability, $expiresAt, $actor) {
            $grant = PortalAccessGrant::query()->create([
                'user_id' => $link->user_id, 'portal_type' => 'investor', 'resource_type' => $resource instanceof Project ? 'project' : 'flat',
                'resource_id' => $resource->id, 'capability' => $capability, 'granted_by' => $actor->id, 'starts_at' => now(), 'expires_at' => $expiresAt,
            ]);
            PortalAccess::forgetCache($link->user_id);
            activity('portal')->causedBy($actor)->performedOn($link->investor)->event('portal_grant_added')
                ->withProperties(['account' => $link->user->name, 'capability' => PortalAccess::GRANTABLE[$capability], 'resource' => $this->resourceLabel($resource)])->log('portal_grant_added');

            return $grant;
        });
    }

    public function revokeGrant(PortalAccessGrant $grant, User $actor): void
    {
        DB::transaction(function () use ($grant, $actor) {
            $grant = PortalAccessGrant::query()->lockForUpdate()->findOrFail($grant->id);
            if ($grant->revoked_at) {
                return;
            }
            $grant->forceFill(['revoked_at' => now(), 'revoked_by' => $actor->id])->save();
            PortalAccess::forgetCache($grant->user_id);
            $investor = InvestorUserLink::query()->where('user_id', $grant->user_id)->latest('id')->first()?->investor;
            activity('portal')->causedBy($actor)->performedOn($investor ?? $grant)->event('portal_grant_revoked')
                ->withProperties(['account' => $grant->user->name, 'capability' => PortalAccess::GRANTABLE[$grant->capability] ?? $grant->capability])->log('portal_grant_revoked');
        });
    }

    public function resourceLabel(?Model $resource): string
    {
        return match (true) {
            $resource instanceof Project => $resource->code.' · '.$resource->name,
            $resource instanceof Flat => $resource->loadMissing('project:id,code')->project->code.' · Unit '.$resource->flat_no,
            default => '—',
        };
    }

    private function endSessions(int $userId): void
    {
        DB::table('sessions')->where('user_id', $userId)->delete();
        PortalAccess::forgetCache($userId);
    }

    private function owner(InvestorUserLink|CustomerUserLink $link): Model
    {
        return $link instanceof InvestorUserLink ? $link->investor : $link->customer;
    }

    /** @return class-string<InvestorUserLink|CustomerUserLink> */
    private function linkModel(string $type): string
    {
        return $type === 'investor' ? InvestorUserLink::class : CustomerUserLink::class;
    }
}
