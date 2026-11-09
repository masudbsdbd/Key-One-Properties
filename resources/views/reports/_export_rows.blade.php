@forelse ($runs as $run)
    @php $tone = ['queued' => 'neutral', 'running' => 'info', 'completed' => 'success', 'failed' => 'danger', 'expired' => 'neutral'][$run->status] ?? 'neutral'; @endphp
    <tr @if (in_array($run->status, ['queued', 'running'], true)) data-run-pending @endif>
        <td class="font-medium text-ink-900">{{ ($reports[$run->report_key] ?? null)?->name() ?? $run->report_key }}<span class="block text-[13px] font-normal text-ink-500">{{ collect($run->filters_json)->map(fn ($v, $k) => $k.': '.$v)->join(' · ') ?: 'No filters' }}</span></td>
        <td class="uppercase">{{ $run->format }}</td>
        <td><x-badge :tone="$tone">{{ App\Models\ReportRun::STATUSES[$run->status] ?? $run->status }}</x-badge>@if ($run->error_summary)<span class="block text-[13px] text-danger-600">{{ $run->error_summary }}</span>@endif</td>
        <td class="text-right tabular-nums">{{ $run->row_count !== null ? number_format($run->row_count) : '—' }}</td>
        <td class="text-right tabular-nums">{{ $run->file ? App\Support\Format::bytes($run->file->size_bytes) : '—' }}</td>
        <td class="whitespace-nowrap">{{ App\Support\Format::datetime($run->created_at) }}</td>
        <td class="whitespace-nowrap">{{ $run->expires_at ? App\Support\Format::date($run->expires_at) : '—' }}</td>
        <td class="text-right">@if ($run->isDownloadable())<a href="{{ route('report-exports.download', $run) }}" class="btn btn-ghost btn-icon" aria-label="Download {{ $run->file->file_name }}"><x-icon name="download" size="h-4 w-4" /></a>@endif</td>
    </tr>
@empty
    <tr><td colspan="8"><x-empty-state icon="download" title="No exports yet" /></td></tr>
@endforelse
