@props(['name', 'label' => null, 'value' => null, 'required' => false, 'hint' => null, 'rows' => 3, 'wrapperClass' => ''])
@php
    $id = $attributes->get('id') ?? 'f-'.\Illuminate\Support\Str::slug($name);
@endphp
<x-form.field :name="$name" :label="$label" :id="$id" :required="$required" :hint="$hint" :class="$wrapperClass">
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($required) required @endif
        aria-describedby="{{ $id }}-error" @error($name) aria-invalid="true" @enderror
        {{ $attributes->except('id')->merge(['class' => 'form-control']) }}>{{ old($name, $value) }}</textarea>
</x-form.field>
