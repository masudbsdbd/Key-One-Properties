@php
    $o = App\Modules\Projects\Services\ProjectOverview::for($project, auth()->user());
    $t = $o['timeline'];
    $fmt = fn ($v) => App\Support\Format::money($v, false);
    $states = ['on_track' => ['On track', 'success'], 'behind' => ['Behind schedule', 'warning'], 'late' => ['Past target', 'danger'],
        'not_started' => ['Not started', 'info'], 'completed' => ['Completed', 'violet'], 'unscheduled' => ['No schedule', 'neutral']];
    [$stateLabel, $stateTone] = $states[$t['state']];
@endphp
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
    <div class="card flex gap-4 p-5">
        <span class="icon-tile tone-accent"><x-icon name="bar-chart" size="h-6 w-6" /></span>
        <div class="min-w-0 flex-1">
            <p class="text-sm text-ink-500">Construction</p>
            <p class="text-[26px] font-semibold leading-8 tabular-nums text-ink-900">{{ App\Support\Format::percent($o['progress']) }}</p>
            <x-progress :value="$o['progress']" :label="false" class="mt-2" />
            <p class="mt-1.5 text-[12px] text-ink-500">{{ $o['stages_done'] }} of {{ $o['stages'] }} stages done</p>
        </div>
    </div>

    <div class="card flex gap-4 p-5">
        <span class="icon-tile tone-{{ $stateTone === 'neutral' ? 'accent' : $stateTone }}"><x-icon name="calendar" size="h-6 w-6" /></span>
        <div class="min-w-0 flex-1">
            <p class="flex items-center justify-between gap-2 text-sm text-ink-500">Schedule <x-badge :tone="App\Models\Project::STATUS_TONES[$project->status] ?? 'neutral'">{{ App\Models\Project::STATUSES[$project->status] ?? $project->status }}</x-badge></p>
            <p class="text-[26px] font-semibold leading-8 tabular-nums text-ink-900">
                @switch($t['state'])
                    @case('completed') Done @break
                    @case('unscheduled') — @break
                    @case('not_started') {{ number_format(abs($t['start']->diffInDays(now(config('app.display_timezone'))->startOfDay()))) }} <span class="text-base font-medium text-ink-500">days to start</span> @break
                    @case('late') {{ number_format(abs($t['days_left'])) }} <span class="text-base font-medium text-danger-600">days late</span> @break
                    @default {{ number_format($t['days_left']) }} <span class="text-base font-medium text-ink-500">days left</span>
                @endswitch
            </p>
            @if ($t['time_pct'] !== null)<x-progress :value="$t['time_pct']" :label="false" class="mt-2" />@endif
            <p class="mt-1.5 truncate text-[12px] text-ink-500"><span @class(['font-medium', 'text-success-600' => $stateTone === 'success', 'text-warning-600' => $stateTone === 'warning', 'text-danger-600' => $stateTone === 'danger'])>{{ $stateLabel }}</span>@if ($t['target']) · target {{ App\Support\Format::date($t['target']) }}@endif</p>
        </div>
    </div>

    @if ($o['units'])
        @php $u = $o['units']; @endphp
        <div class="card flex gap-4 p-5">
            <span class="icon-tile tone-info"><x-icon name="door" size="h-6 w-6" /></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm text-ink-500">Units Sold</p>
                <p class="text-[26px] font-semibold leading-8 tabular-nums text-ink-900">{{ $u['sold'] }} <span class="text-base font-medium text-ink-500">/ {{ $u['total'] }}</span></p>
                <div class="mt-2 flex h-2 overflow-hidden rounded-full bg-ink-900/[0.07]" aria-hidden="true">
                    <span class="bg-success-600" style="width: {{ $u['total'] ? $u['sold'] / $u['total'] * 100 : 0 }}%"></span>
                    <span class="bg-warning-600" style="width: {{ $u['total'] ? $u['booked'] / $u['total'] * 100 : 0 }}%"></span>
                </div>
                <p class="mt-1.5 truncate text-[12px] text-ink-500">{{ $u['booked'] }} booked · {{ $u['available'] }} available@if ($u['not_released']) · {{ $u['not_released'] }} not released@endif</p>
            </div>
        </div>
    @else
        <x-stat-card label="Stages Completed" :value="$o['stages_done'].' / '.$o['stages']" icon="check-square" />
    @endif

    @if ($o['cost'])
        @php $c = $o['cost']; @endphp
        <div class="card flex gap-4 p-5">
            <span @class(['icon-tile', 'tone-danger' => $c['over'], 'tone-warning' => ! $c['over']])><x-icon name="tag" size="h-6 w-6" /></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm text-ink-500">Budget Used</p>
                <p class="text-[26px] font-semibold leading-8 tabular-nums text-ink-900">{{ $c['used_pct'] !== null ? App\Support\Format::percent($c['used_pct']) : $fmt($c['spent']) }}</p>
                @if ($c['used_pct'] !== null)<x-progress :value="$c['used_pct']" :label="false" class="mt-2" />@endif
                <p @class(['mt-1.5 truncate text-[12px]', 'font-medium text-danger-600' => $c['over'], 'text-ink-500' => ! $c['over']])>{{ $fmt($c['spent']) }}{{ $c['budget'] !== null ? ' of '.$fmt($c['budget']) : ' spent · no budget set' }}</p>
            </div>
        </div>
    @else
        <x-stat-card label="Team Members" :value="$project->members_count ?? $project->members()->count()" icon="users" />
    @endif
</div>
