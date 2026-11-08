@props(['name', 'label' => null, 'id' => null, 'required' => false, 'hint' => null])
@php
    $key = rtrim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $message = $errors->first($key);
@endphp
<div {{ $attributes->merge(['class' => 'min-w-0']) }} data-field>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required) <span class="form-required" aria-hidden="true">*</span>@endif</label>
    @endif
    {{ $slot }}
    @if ($hint)<p class="form-hint" id="{{ $id }}-hint">{{ $hint }}</p>@endif
    <p class="form-error" id="{{ $id }}-error" data-error @if (! $message) hidden @endif>{{ $message }}</p>
</div>
