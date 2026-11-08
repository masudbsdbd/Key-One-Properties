<form method="POST" action="{{ route('projects.media.update', [$project, $media]) }}" data-ajax-form class="space-y-5">
    @csrf @method('PUT')
    <img src="{{ route('projects.media.show', [$project, $media]) }}" alt="" class="aspect-[4/3] w-full rounded-card object-cover">
    <x-form.input name="caption" label="Caption" :value="$media->caption" autofocus />
    <x-form.select name="visibility" label="Visibility" :options="App\Models\ProjectMedia::VISIBILITIES" :selected="$media->visibility" required />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save</x-button>
    </x-form.actions>
</form>
