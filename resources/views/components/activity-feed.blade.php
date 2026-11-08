@props(['entries'])
@php
    $tz = config('app.display_timezone');
    $today = now($tz)->startOfDay();
    $groups = collect(App\Support\ActivityPresenter::group($entries))->groupBy(fn ($g) => $g['entry']->created_at->timezone($tz)->toDateString());
    $tones = [
        'success' => 'bg-success-50 text-success-600 ring-success-600/20',
        'danger' => 'bg-danger-50 text-danger-600 ring-danger-500/20',
        'warning' => 'bg-warning-50 text-warning-600 ring-warning-600/20',
        'info' => 'bg-info-50 text-info-600 ring-info-600/20',
        'accent' => 'bg-accent-50 text-accent-700 ring-accent-500/20',
    ];
@endphp
@if (collect($entries)->isEmpty())
    <x-empty-state icon="activity" title="No activity yet" />
@else
    <div class="px-5 py-5">
        @foreach ($groups as $day => $items)
            @php($date = Carbon\Carbon::parse($day, $tz))
            <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-ink-400 {{ $loop->first ? '' : 'mt-6' }}">
                {{ $date->equalTo($today) ? 'Today' : ($date->equalTo($today->copy()->subDay()) ? 'Yesterday' : $date->format('d M Y')) }}
            </p>
            <ol class="relative">
                @foreach ($items as $group)
                    @php($entry = $group['entry'])
                    @php($style = App\Support\ActivityPresenter::style($entry))
                    @php($changes = $group['count'] > 1 ? [] : App\Support\ActivityPresenter::displayChanges($entry))
                    @php($creation = App\Support\ActivityPresenter::isCreation($entry))
                    @php($context = App\Support\ActivityPresenter::context($entry))
                    <li class="relative flex gap-4 pb-6 last:pb-0">
                        @unless ($loop->last)<span class="absolute left-[17px] top-9 bottom-0 w-px bg-line" aria-hidden="true"></span>@endunless
                        <span class="relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-full ring-4 ring-surface {{ $tones[$style['tone']] }}">
                            <x-icon :name="$style['icon']" size="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1 pt-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                <p class="text-sm text-ink-700">
                                    <span class="font-semibold text-ink-900">{{ $entry->causer?->name ?? 'System' }}</span>
                                    {{ Str::lower(App\Support\ActivityPresenter::event($entry)) }}
                                    @if ($group['count'] > 1)
                                        <span class="font-medium text-ink-900">{{ $group['count'] }} {{ Str::plural(Str::lower(Str::headline(class_basename($entry->subject_type ?? 'record')))) }}</span>
                                    @else
                                        <span class="font-medium text-ink-900">{{ App\Support\ActivityPresenter::subject($entry) }}</span>
                                    @endif
                                </p>
                                <time class="shrink-0 text-[13px] text-ink-400" datetime="{{ $entry->created_at->toIso8601String() }}" data-tooltip="{{ App\Support\Format::datetime($entry->created_at) }}">{{ $entry->created_at->timezone($tz)->format('h:i A') }}</time>
                            </div>
                            @if ($group['count'] > 1)
                                <p class="mt-1.5 text-[13px] text-ink-500">{{ Str::limit(implode(', ', array_reverse($group['subjects'])), 160) }}</p>
                            @elseif ($changes && $creation)
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($changes as $change)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-surface-muted px-2 py-0.5 text-[12px] text-ink-500 ring-1 ring-line">{{ $change['field'] }} <span class="font-medium text-ink-800">{{ $change['new'] }}</span></span>
                                    @endforeach
                                </div>
                            @elseif ($changes)
                                <div class="mt-2 overflow-hidden rounded-lg border border-line bg-surface-muted">
                                    @foreach ($changes as $change)
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-line px-3 py-1.5 text-[13px] last:border-b-0">
                                            <span class="w-36 shrink-0 text-ink-500">{{ $change['field'] }}</span>
                                            @if ($change['old'] !== null)
                                                <span class="text-danger-600 line-through decoration-danger-500/40">{{ $change['old'] }}</span>
                                                <x-icon name="arrow-right" size="h-3.5 w-3.5 text-ink-400" />
                                            @endif
                                            <span class="font-medium text-success-600">{{ $change['new'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if ($context)
                                <p class="mt-1.5 text-[13px] text-ink-500">
                                    @foreach ($context as $label => $value)<span class="mr-3">{{ $label }}: <span class="text-ink-700">{{ $value }}</span></span>@endforeach
                                </p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endforeach
    </div>
@endif
