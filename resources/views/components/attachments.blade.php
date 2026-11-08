@props(['owner', 'type', 'title' => 'Files'])
@php($canManage = Gate::allows(App\Support\Attachments\AttachableRegistry::get($type)['manage'], $owner))
<x-card :title="$title" :padded="false" {{ $attributes }}>
    @if ($canManage)
        <x-slot:actions>
            <form method="POST" action="{{ route('attachments.store', [$type, $owner->public_id]) }}" enctype="multipart/form-data" data-ajax-form data-reset data-refresh-partials>
                @csrf
                <label class="btn btn-secondary btn-sm cursor-pointer"><x-icon name="upload" size="h-4 w-4" />Attach<input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" class="sr-only" data-auto-submit></label>
                <span data-field><span class="form-error mt-0" data-error hidden></span><input type="hidden" name="files" disabled></span>
                <span class="form-error mt-0" data-form-error hidden></span>
            </form>
        </x-slot:actions>
    @endif
    <div data-partial-url="{{ route('attachments.index', [$type, $owner->public_id]) }}" data-lazy>
        <div class="p-5"><div class="skeleton h-10 w-full"></div></div>
    </div>
</x-card>
