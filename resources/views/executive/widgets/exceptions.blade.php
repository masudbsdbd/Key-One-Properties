<ul class="divide-y divide-line">
    @forelse ($data['queue'] as $item)
        <li><a href="{{ $item['href'] }}" class="flex items-center gap-3 py-2 text-sm hover:text-accent-700"><x-icon :name="$item['icon']" size="h-4 w-4 text-ink-500" /><span class="flex-1">{{ $item['label'] }}</span><span class="nav-count">{{ $item['count'] }}</span></a></li>
    @empty
        <li class="py-2 text-sm text-ink-500">No approvals waiting</li>
    @endforelse
</ul>
@if ($data['overdue_milestones'])
    <p class="mb-1 mt-4 text-sm font-medium text-ink-900">Overdue milestones</p>
    @foreach ($data['overdue_milestones'] as $m)
        <p class="text-[13px] text-ink-600">{{ $m['project'] }} · {{ $m['title'] }} · {{ $m['planned'] }}</p>
    @endforeach
@endif
@if ($data['reconciliation'] !== null)
    <p class="mt-4 text-sm">Reconciliation: <x-badge :tone="$data['reconciliation'] ? 'danger' : 'success'">{{ $data['reconciliation'] ? $data['reconciliation'].' differences' : 'All ledgers match' }}</x-badge></p>
@endif
@include('executive.widgets._footer', ['basis' => 'Live'])
