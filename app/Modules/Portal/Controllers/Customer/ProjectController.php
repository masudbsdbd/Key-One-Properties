<?php

namespace App\Modules\Portal\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\ConstructionProgressUpdate;
use App\Models\Project;
use App\Modules\Portal\Services\CustomerPortal;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Construction progress of a project the customer holds a unit in. Off by default: the Customer role,
 * or a grant on the project or one of the customer's units in it, opens it. Only portal-published updates show.
 */
class ProjectController extends Controller
{
    public function show(PortalContext $context, string $project): View
    {
        $record = Project::query()->where('public_id', $project)
            ->whereIn('id', PortalAccess::customerProgressProjectIds($context->user, $context->customer))->first() ?? abort(404);
        $updates = ConstructionProgressUpdate::query()->where('project_id', $record->id)->investorVisible()
            ->with(['workPackage:id,name', 'milestone:id,title', 'attachments'])->latest('published_at')->limit(30)->get();

        return view('portal.customer.projects.show', [
            'project' => $record->load('stages'),
            'milestones' => $record->milestones()->get(),
            'updates' => $updates,
            'units' => CustomerPortal::for($context)->sales(true)->where('project_id', $record->id)->get()
                ->map(fn ($s) => $s->flat->flat_no)->unique()->values(),
        ]);
    }

    public function photo(Request $request, PortalContext $context, string $file): Response
    {
        $updates = ConstructionProgressUpdate::query()->investorVisible()
            ->whereIn('project_id', PortalAccess::customerProgressProjectIds($context->user, $context->customer))->select('id');
        $attachment = Attachment::query()->where('public_id', $file)->where('attachable_type', (new ConstructionProgressUpdate)->getMorphClass())
            ->whereIn('attachable_id', $updates)->first();
        abort_unless($attachment && $attachment->isImage(), 404);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type, 'Cache-Control' => 'private, max-age=600']);
    }
}
