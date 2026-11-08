@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'required' => false,
    'hint' => null,
    'placeholder' => 'Select',
    'url' => null,
    'multiple' => false,
    'dependsOn' => null,
    'dependsParam' => null,
    'enhance' => true,
    'wrapperClass' => '',
])
{{--
    options: [value => label]. For remote selects pass url; preselected remote values
    go in options too so the current choice renders on edit.
--}}
@php
    $id = $attributes->get('id') ?? 'f-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], '-', $name));
    $key = rtrim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $current = old($key, $selected);
    $currentValues = collect(is_array($current) ? $current : [$current])->map(fn ($v) => (string) $v)->all();
@endphp
<x-form.field :name="$name" :label="$label" :id="$id" :required="$required" :hint="$hint" :class="$wrapperClass">
    <select id="{{ $id }}" name="{{ $name }}{{ $multiple && ! str_ends_with($name, '[]') ? '[]' : '' }}"
        @if ($multiple) multiple @endif
        @if ($required) required @endif
        @if ($enhance) data-select data-placeholder="{{ $placeholder }}" @endif
        @if ($url) data-select-url="{{ $url }}" @endif
        @if ($dependsOn) data-depends-on="{{ $dependsOn }}" @endif
        @if ($dependsParam) data-depends-param="{{ $dependsParam }}" @endif
        aria-describedby="{{ $id }}-error" @error($key) aria-invalid="true" @enderror
        {{ $attributes->except('id')->merge(['class' => 'form-control']) }}>
        @unless ($multiple)<option value="">{{ $enhance ? '' : $placeholder }}</option>@endunless
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected(in_array((string) $value, $currentValues, true))>{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>
</x-form.field>
