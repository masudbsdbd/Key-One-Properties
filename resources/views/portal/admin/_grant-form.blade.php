<form method="POST" action="{{ route('portal-access.grants.store', $link->public_id) }}" data-ajax-form class="space-y-5">
    @csrf
    <x-form.select name="resource" label="Project or Unit" :options="$resources" required :enhance="count($resources) > 7" placeholder="Select" />
    <x-form.select name="capability" label="Capability" :options="App\Modules\Portal\Services\PortalAccess::GRANTABLE" required :enhance="false" />
    <x-form.input name="expires_at" type="date" label="Expires" :min="now(config('app.display_timezone'))->addDay()->toDateString()" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Add Grant</x-button>
    </x-form.actions>
</form>
