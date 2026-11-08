@php($tones = ['security' => 'danger', 'users' => 'info', 'roles' => 'violet', 'departments' => 'accent', 'settings' => 'neutral', 'projects' => 'accent', 'flats' => 'orange', 'customers' => 'success'])
@forelse ($entries as $entry)
    @php($changes = App\Support\ActivityPresenter::changes($entry))
    @php($context = App\Support\ActivityPresenter::context($entry))
    <tr>
        <td class="whitespace-nowrap text-ink-600">{{ $entry->created_at->timezone(config('app.display_timezone'))->format('d M Y, h:i A') }}</td>
        <td>{{ $entry->causer?->name ?? 'System' }}</td>
        <td><x-badge :tone="$tones[$entry->log_name] ?? 'neutral'">{{ $modules[$entry->log_name] ?? Str::headline($entry->log_name) }}</x-badge></td>
        <td class="text-ink-900">{{ App\Support\ActivityPresenter::event($entry) }}</td>
        <td class="text-ink-600">{{ App\Support\ActivityPresenter::subject($entry) }}</td>
        <td class="text-right">
            @if ($changes || $context)
                <button type="button" class="btn btn-ghost btn-icon" aria-expanded="false" aria-label="Details" data-toggle-row><x-icon name="chevron-down" size="h-4 w-4" /></button>
            @endif
        </td>
    </tr>
    @if ($changes || $context)
        <tr hidden data-detail-row>
            <td colspan="6" class="bg-surface-muted">
                @if ($changes)
                    <table class="w-full text-[13px]">
                        <thead><tr class="text-left text-ink-500"><th class="py-1.5 pr-4 font-medium">Field</th><th class="py-1.5 pr-4 font-medium">Before</th><th class="py-1.5 font-medium">After</th></tr></thead>
                        <tbody>
                            @foreach ($changes as $change)
                                <tr><td class="py-1.5 pr-4 text-ink-700">{{ $change['field'] }}</td><td class="py-1.5 pr-4 text-danger-600">{{ $change['old'] }}</td><td class="py-1.5 text-success-600">{{ $change['new'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                @if ($context)
                    <dl class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-[13px]">
                        @foreach ($context as $label => $value)<div><dt class="inline text-ink-500">{{ $label }}:</dt> <dd class="inline text-ink-700">{{ $value }}</dd></div>@endforeach
                    </dl>
                @endif
            </td>
        </tr>
    @endif
@empty
    <tr><td colspan="6"><x-empty-state icon="activity" title="No activity found" /></td></tr>
@endforelse
