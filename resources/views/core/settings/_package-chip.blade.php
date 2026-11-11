<span class="chip" data-package>
    <span data-package-name>{{ $package }}</span>
    <input type="hidden" name="packages[{{ $index }}][]" value="{{ $package }}">
    <button type="button" class="chip-remove" data-package-remove aria-label="Remove {{ $package }}"><x-icon name="x" size="h-3.5 w-3.5" /></button>
</span>
