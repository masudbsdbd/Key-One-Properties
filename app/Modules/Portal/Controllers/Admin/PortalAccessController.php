<?php

namespace App\Modules\Portal\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerUserLink;
use App\Models\Flat;
use App\Models\Investor;
use App\Models\InvestorUserLink;
use App\Models\PortalAccessGrant;
use App\Models\Project;
use App\Models\User;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalAccountService;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PortalAccessController extends Controller
{
    public function __construct(private PortalAccountService $service) {}

    public function panel(Request $request, string $type, string $owner): JsonResponse
    {
        abort_unless($request->user()->canAny(['portal.links.manage', 'portal.grants.manage']), 403);
        $record = $this->owner($request, $type, $owner);
        $links = $this->linkModel($type)::query()->where("{$type}_id", $record->id)->with(['user:id,name,email,mobile,last_login_at', 'user.roles:id,name'])->latest('id')->get();
        $grants = $type === 'investor'
            ? PortalAccessGrant::query()->whereIn('user_id', $links->pluck('user_id'))->with('granter:id,name')->latest('id')->get()->groupBy('user_id')
            : collect();

        return Ajax::success(null, ['html' => view('portal.admin._panel', [
            'type' => $type, 'owner' => $record, 'links' => $links, 'grants' => $grants, 'service' => $this->service,
        ])->render()]);
    }

    public function create(Request $request, string $type, string $owner): JsonResponse
    {
        abort_unless($request->user()->can('portal.links.manage'), 403);
        $record = $this->owner($request, $type, $owner);

        return Ajax::success(null, ['title' => 'New Portal Account', 'html' => view('portal.admin._account-form', ['type' => $type, 'owner' => $record])->render()]);
    }

    public function store(Request $request, string $type, string $owner): JsonResponse
    {
        abort_unless($request->user()->can('portal.links.manage'), 403);
        $record = $this->owner($request, $type, $owner);
        $request->merge([
            'email' => $request->filled('email') ? mb_strtolower(trim((string) $request->input('email'))) : null,
            'mobile' => User::normalizeMobile($request->input('mobile')),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:mobile', 'email', 'max:255', Rule::unique('users', 'email')],
            'mobile' => ['nullable', 'required_without:email', 'string', 'max:20', Rule::unique('users', 'mobile')],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['email.required_without' => 'Enter an email or a mobile number.', 'mobile.required_without' => 'Enter an email or a mobile number.']);
        $this->service->createAccount($record, $data, $request->user());

        return Ajax::success('Portal account created. Waiting for verification.');
    }

    public function verify(Request $request, string $type, string $link): JsonResponse
    {
        abort_unless($request->user()->can('portal.links.manage'), 403);
        try {
            $this->service->verify($this->link($request, $type, $link), $request->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['link' => collect($e->errors())->flatten()->first()]);
        }

        return Ajax::success('Portal access verified.');
    }

    public function revokeForm(Request $request, string $type, string $link): JsonResponse
    {
        abort_unless($request->user()->can('portal.links.manage'), 403);
        $record = $this->link($request, $type, $link);

        return Ajax::success(null, ['title' => 'Revoke · '.$record->user->name, 'html' => view('partials.reason-form', [
            'action' => route('portal-access.links.revoke', [$type, $record->public_id]), 'button' => 'Revoke Access', 'reload' => false,
        ])->render()]);
    }

    public function revoke(Request $request, string $type, string $link): JsonResponse
    {
        abort_unless($request->user()->can('portal.links.manage'), 403);
        $record = $this->link($request, $type, $link);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];
        $this->service->revoke($record, $reason, $request->user());

        return Ajax::success('Portal access revoked.', ['event' => 'portal:changed']);
    }

    public function passwordForm(Request $request, string $type, string $link): JsonResponse
    {
        abort_unless($request->user()->can('portal.links.manage'), 403);
        $record = $this->link($request, $type, $link);

        return Ajax::success(null, ['title' => 'Reset Password · '.$record->user->name, 'html' => view('portal.admin._password-form', ['type' => $type, 'link' => $record])->render()]);
    }

    public function password(Request $request, string $type, string $link): JsonResponse
    {
        abort_unless($request->user()->can('portal.links.manage'), 403);
        $record = $this->link($request, $type, $link);
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        $this->service->resetPassword($record, $data['password'], $request->user());

        return Ajax::success('Password reset. The account must change it at next sign-in.');
    }

    public function grantForm(Request $request, string $link): JsonResponse
    {
        abort_unless($request->user()->can('portal.grants.manage'), 403);
        $record = $this->link($request, 'investor', $link);

        return Ajax::success(null, ['title' => 'Add Grant · '.$record->user->name, 'html' => view('portal.admin._grant-form', [
            'link' => $record, 'resources' => $this->grantResources($record->investor),
        ])->render()]);
    }

    public function grant(Request $request, string $link): JsonResponse
    {
        abort_unless($request->user()->can('portal.grants.manage'), 403);
        $record = $this->link($request, 'investor', $link);
        $data = $request->validate([
            'resource' => ['required', 'string', 'regex:/^(project|flat):[A-Za-z0-9]{16}$/'],
            'capability' => ['required', Rule::in(array_keys(PortalAccess::GRANTABLE))],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);
        [$kind, $publicId] = explode(':', $data['resource']);
        $resource = ($kind === 'project' ? Project::findByPublicId($publicId) : Flat::findByPublicId($publicId))
            ?? throw ValidationException::withMessages(['resource' => 'Select a project or unit.']);
        $this->service->grant($record, $resource, $data['capability'], $data['expires_at'] ?? null, $request->user());

        return Ajax::success('Grant added.');
    }

    public function revokeGrant(Request $request, PortalAccessGrant $grant): JsonResponse
    {
        abort_unless($request->user()->can('portal.grants.manage'), 403);
        $link = InvestorUserLink::query()->where('user_id', $grant->user_id)->firstOrFail();
        $this->owner($request, 'investor', $link->investor->public_id);
        $this->service->revokeGrant($grant, $request->user());

        return Ajax::success('Grant revoked.');
    }

    /** @return array<string, string> "project:{public_id}" or "flat:{public_id}" => label */
    private function grantResources(Investor $investor): array
    {
        $projects = Project::query()->whereIn('id', PortalAccess::investorProjectIds($investor))->orderBy('code')->get(['id', 'public_id', 'code', 'name'])
            ->mapWithKeys(fn ($p) => ['project:'.$p->public_id => 'Project · '.$p->code.' · '.$p->name]);
        $flats = Flat::query()->whereIn('id', PortalAccess::investorFlatIds($investor))->with('project:id,code')->orderBy('project_id')->orderBy('flat_no')->get(['id', 'public_id', 'flat_no', 'project_id'])
            ->mapWithKeys(fn ($f) => ['flat:'.$f->public_id => 'Unit · '.$f->project->code.' · '.$f->flat_no]);

        return $projects->merge($flats)->all();
    }

    private function owner(Request $request, string $type, string $publicId): Investor|Customer
    {
        return match ($type) {
            'investor' => $request->user()->can('investor.view') ? (Investor::findByPublicId($publicId) ?? abort(404)) : abort(403),
            'customer' => Customer::query()->visibleTo($request->user())->where('public_id', $publicId)->first() ?? abort(404),
            default => abort(404),
        };
    }

    private function link(Request $request, string $type, string $publicId): InvestorUserLink|CustomerUserLink
    {
        $link = $this->linkModel($type)::query()->where('public_id', $publicId)->with('user')->first() ?? abort(404);
        $this->owner($request, $type, ($type === 'investor' ? $link->investor : $link->customer)->public_id);

        return $link;
    }

    /** @return class-string<InvestorUserLink|CustomerUserLink> */
    private function linkModel(string $type): string
    {
        return match ($type) {
            'investor' => InvestorUserLink::class,
            'customer' => CustomerUserLink::class,
            default => abort(404),
        };
    }
}
