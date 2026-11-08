<div {{ $attributes->merge(['class' => 'mt-6 flex items-center justify-end gap-3']) }}>
    <p class="form-error mr-auto mt-0" data-form-error hidden></p>
    {{ $slot }}
</div>
