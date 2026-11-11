<li class="rounded-lg border border-line p-3" data-stage-template>
    <div class="flex items-center gap-2">
        <span class="cursor-grab text-ink-400" data-drag-handle aria-label="Reorder"><x-icon name="drag" size="h-5 w-5" /></span>
        <span class="w-6 text-right text-sm tabular-nums text-ink-500" data-stage-number>{{ $number }}</span>
        <input type="text" name="stages[]" value="{{ $name }}" maxlength="80" class="form-control flex-1" aria-label="Stage name">
        <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-stage-remove aria-label="Remove stage"><x-icon name="trash" size="h-4 w-4" /></button>
    </div>
    <div class="mt-2.5 flex flex-wrap items-center gap-2 pl-[3.75rem]" data-package-list>
        @foreach ($packages as $package)
            @include('core.settings._package-chip', ['package' => $package, 'index' => (int) $number - 1])
        @endforeach
        <span class="inline-flex items-center gap-1.5" data-package-adder>
            <input type="text" maxlength="160" class="form-control h-8 w-44 text-[13px]" placeholder="Work package" aria-label="Work package name" data-package-new>
            <button type="button" class="btn btn-secondary btn-sm h-8" data-package-add>Add</button>
        </span>
    </div>
</li>
