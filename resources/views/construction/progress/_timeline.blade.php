@php($actor = auth()->user())
@php($canApprove = $actor->can('construction:progress-approve', $project))
@if ($updates->isEmpty())
    <x-empty-state icon="activity" title="No progress submitted" />
@else
    <ol class="divide-y divide-line">
        @foreach ($updates as $update)
            <li class="px-5 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-ink-900">{{ $update->workPackage?->label() ?? 'Project' }}@if ($update->milestone) <span class="text-ink-500">· {{ $update->milestone->title }}</span>@endif</p>
                        <p class="text-[13px] text-ink-500">{{ App\Support\Format::date($update->report_date) }} · {{ $update->submitter?->name }}@if ($update->approver && $update->status !== 'submitted') · {{ $update->status === 'approved' ? 'Approved' : 'Rejected' }} by {{ $update->approver->name }}@endif</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        @if ($update->visibility === 'investor')<x-badge tone="violet">{{ $update->published_at ? 'Published' : 'Investor' }}</x-badge>@endif
                        <x-badge tone="accent">{{ App\Support\Format::percent($update->previous_percent) }} → {{ App\Support\Format::percent($update->progress_percent) }}</x-badge>
                        <x-status-badge :status="$update->status" />
                    </div>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-ink-700">{{ $update->notes }}</p>
                @if ($update->rejection_reason)<p class="mt-1 text-[13px] text-danger-600">{{ $update->rejection_reason }}</p>@endif
                @if ($update->attachments->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($update->attachments as $file)
                            <a href="{{ route('attachments.show', $file) }}" target="_blank" rel="noopener" class="block">
                                @if ($file->isImage())
                                    <img src="{{ route('attachments.show', $file) }}" alt="" class="h-16 w-20 rounded-lg border border-line object-cover" loading="lazy">
                                @else
                                    <span class="inline-flex h-16 items-center gap-2 rounded-lg border border-line px-3 text-[13px] text-ink-700"><x-icon name="file" size="h-4 w-4" />{{ Str::limit($file->original_name, 20) }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
                @if ($update->status === 'submitted' && $canApprove)
                    <div class="mt-3 flex gap-2">
                        <x-button size="sm" icon="check" data-action="post" data-url="{{ route('construction.progress.approve', [$project, $update]) }}" data-confirm="Approve this progress update?" data-tone="primary" data-confirm-text="Approve">Approve</x-button>
                        <x-button size="sm" variant="danger-soft" data-remote-modal="{{ route('construction.progress.reject.form', [$project, $update]) }}" data-size="max-w-md">Reject</x-button>
                    </div>
                @endif
            </li>
        @endforeach
    </ol>
@endif
