@php
    $contractorOptions = $order->contractor ? [$order->contractor->public_id => $order->contractor->name.' · '.$order->contractor->contractor_code] : [];
    $projectOptions = $order->project ? [$order->project->public_id => $order->project->name] : [];
    $packageOptions = $order->workPackage ? [$order->workPackage->public_id => $order->workPackage->label()] : [];
@endphp
<form method="POST" action="{{ $order->exists ? route('work-orders.update', $order) : route('work-orders.store') }}" data-ajax-form class="grid gap-5 sm:grid-cols-2">
    @csrf
    @if ($order->exists) @method('PUT') @endif
    <x-form.select name="contractor" label="Contractor" :url="route('options.contractors')" :options="$contractorOptions" :selected="$order->contractor?->public_id" required placeholder="Search contractors" />
    <x-form.select name="project" id="wo-project" label="Project" :url="route('options.projects')" :options="$projectOptions" :selected="$order->project?->public_id" required placeholder="Search projects" />
    <x-form.input name="title" label="Title" :value="$order->title" required wrapper-class="sm:col-span-2" />
    <x-form.select name="work_package" label="Work Package" :url="route('options.work-packages')" depends-on="#wo-project" depends-param="project" :options="$packageOptions" :selected="$order->workPackage?->public_id" placeholder="None" />
    <div class="grid grid-cols-2 gap-5">
        <x-form.input name="contract_amount" label="Contract Amount" :value="$order->contract_amount" inputmode="decimal" required />
        <x-form.input name="retention_percent" label="Retention %" :value="$order->retention_percent ?? '0'" inputmode="decimal" />
    </div>
    <x-form.input name="start_date" type="date" label="Start" :value="$order->start_date?->toDateString()" />
    <x-form.input name="end_date" type="date" label="End" :value="$order->end_date?->toDateString()" />
    <x-form.textarea name="scope" label="Scope" rows="3" :value="$order->scope" wrapper-class="sm:col-span-2" />
    <x-form.actions class="sm:col-span-2 mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save Draft</x-button>
    </x-form.actions>
</form>
