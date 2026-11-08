@props(['name', 'label' => null, 'accept' => null, 'required' => false, 'hint' => null, 'multiple' => false, 'wrapperClass' => ''])
@php
    $id = $attributes->get('id') ?? 'f-'.\Illuminate\Support\Str::slug($name);
@endphp
<x-form.field :name="$name" :label="$label" :id="$id" :required="$required" :hint="$hint" :class="$wrapperClass">
    <input type="file" id="{{ $id }}" name="{{ $name }}{{ $multiple ? '[]' : '' }}" @if ($accept) accept="{{ $accept }}" @endif @if ($multiple) multiple @endif @if ($required) required @endif
        aria-describedby="{{ $id }}-error"
        {{ $attributes->except('id')->merge(['class' => 'block w-full text-sm text-ink-600 file:mr-3 file:h-10 file:rounded-control file:border file:border-line-strong file:bg-surface file:px-4 file:text-sm file:font-medium file:text-ink-800 hover:file:bg-surface-muted']) }}>
</x-form.field>
