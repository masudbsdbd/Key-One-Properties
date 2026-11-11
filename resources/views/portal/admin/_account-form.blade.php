<form method="POST" action="{{ route('portal-access.store', [$type, $owner->public_id]) }}" data-ajax-form class="space-y-5">
    @csrf
    <x-form.input name="name" label="Name" :value="$type === 'investor' ? $owner->name : $owner->full_name" required autofocus />
    <div class="grid gap-5 sm:grid-cols-2">
        <x-form.input name="email" type="email" label="Email" :value="$owner->email" />
        <x-form.input name="mobile" label="Mobile" :value="$type === 'investor' ? $owner->mobile : $owner->primary_mobile" inputmode="tel" />
        <x-form.input name="password" type="password" label="Temporary Password" required autocomplete="new-password" hint="At least 8 characters, letters and numbers" />
        <x-form.input name="password_confirmation" type="password" label="Confirm Password" required autocomplete="new-password" />
    </div>
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Create Account</x-button>
    </x-form.actions>
</form>
