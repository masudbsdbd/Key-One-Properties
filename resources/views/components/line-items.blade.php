@props([
    'availableUrl' => null,
    'warehouseSelector' => null,
    'cost' => false,
    'costLabel' => 'Unit Cost',
    'qtyLabel' => 'Quantity',
    'signed' => false,
    'lines' => [],
])
{{-- Repeating material lines. Names: lines[i][material], lines[i][quantity], lines[i][unit_cost]. --}}
<div data-lines @if ($availableUrl) data-available-url="{{ $availableUrl }}" data-warehouse="{{ $warehouseSelector }}" @endif {{ $attributes->merge(['class' => 'space-y-3']) }}>
    <div class="hidden gap-3 text-[13px] font-medium text-ink-600 sm:grid {{ $cost ? 'sm:grid-cols-[1fr_140px_140px_36px]' : 'sm:grid-cols-[1fr_160px_36px]' }}">
        <span>Material</span><span>{{ $qtyLabel }}</span>@if ($cost)<span>{{ $costLabel }}</span>@endif<span></span>
    </div>
    <div class="space-y-3" data-line-rows></div>
    <template data-line-template>
        <div class="grid items-start gap-3 rounded-card border border-line p-3 sm:border-0 sm:p-0 {{ $cost ? 'sm:grid-cols-[1fr_140px_140px_36px]' : 'sm:grid-cols-[1fr_160px_36px]' }}" data-line>
            <div data-field>
                <select name="lines[__I__][material]" data-select data-select-url="{{ route('options.materials') }}" data-placeholder="Search material" class="form-control" aria-label="Material" data-line-material><option value=""></option></select>
                <p class="mt-1 text-[12px] text-ink-500" data-line-available></p>
                <p class="form-error" data-error hidden></p>
            </div>
            <div data-field>
                <input name="lines[__I__][quantity]" class="form-control" inputmode="decimal" placeholder="{{ $signed ? '+10 or -2' : '0' }}" aria-label="{{ $qtyLabel }}" data-line-qty>
                <p class="form-error" data-error hidden></p>
            </div>
            @if ($cost)
                <div data-field>
                    <input name="lines[__I__][unit_cost]" class="form-control" inputmode="decimal" placeholder="0.00" aria-label="{{ $costLabel }}" data-line-cost>
                    <p class="form-error" data-error hidden></p>
                </div>
            @endif
            <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-line-remove aria-label="Remove line"><x-icon name="x" size="h-4 w-4" /></button>
        </div>
    </template>
    <script type="application/json" data-line-initial nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">@json($lines)</script>
    <div class="flex items-center justify-between gap-3">
        <button type="button" class="btn btn-sm btn-secondary" data-line-add><x-icon name="plus" size="h-4 w-4" />Add material</button>
        @if ($cost)<p class="text-sm text-ink-600">Total <span class="ml-1 font-semibold tabular-nums text-ink-900" data-lines-total>৳ 0.00</span></p>@endif
    </div>
    <p class="form-error" data-field-error="lines" hidden></p>
</div>
