@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'icon' => null, 'wrapperClass' => ''])
@php
    $id = $attributes->get('id') ?? 'f-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], '-', $name));
    $key = rtrim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $current = $type === 'password' ? null : old($key, $value);
@endphp
<x-form.field :name="$name" :label="$label" :id="$id" :required="$required" :hint="$hint" :class="$wrapperClass">
    <div class="relative">
        @if ($icon)<x-icon :name="$icon" size="h-4 w-4" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" />@endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if (! is_null($current)) value="{{ $current }}" @endif
            @if ($required) required @endif
            aria-describedby="{{ $id }}-error{{ $hint ? ' '.$id.'-hint' : '' }}"
            @error($key) aria-invalid="true" @enderror
            {{ $attributes->except('id')->merge(['class' => 'form-control'.($icon ? ' pl-9' : '')]) }}>
    </div>
</x-form.field>
