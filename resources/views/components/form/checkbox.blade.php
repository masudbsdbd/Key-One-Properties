@props(['name', 'label', 'value' => '1', 'checked' => false])
@php
    $id = $attributes->get('id') ?? 'f-'.\Illuminate\Support\Str::slug($name.'-'.$value);
@endphp
<label for="{{ $id }}" class="inline-flex items-center gap-2.5 text-sm text-ink-700 cursor-pointer select-none">
    <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked)) {{ $attributes->except('id')->merge(['class' => 'form-check']) }}>
    <span>{{ $label }}</span>
</label>
