@php $title = $journal->exists ? 'Edit '.$journal->journal_no : 'New Journal'; @endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :breadcrumbs="['Dashboard' => route('dashboard'), 'Journals' => route('accounting.journals.index'), $title => null]" />

    <form method="POST" action="{{ $journal->exists ? route('accounting.journals.update', $journal) : route('accounting.journals.store') }}" data-ajax-form data-journal-form
        data-journal-lines='@json($lines)' class="space-y-6">
        @csrf
        @if ($journal->exists) @method('PUT') @endif
        <input type="hidden" name="lock_version" value="{{ $journal->lock_version ?? 0 }}">
        <input type="hidden" name="then" value="" data-journal-then>

        <x-card>
            <div class="grid gap-5 md:grid-cols-4">
                <x-form.input name="accounting_date" type="date" label="Date" :value="$journal->accounting_date?->toDateString()" required />
                <x-form.input name="reference" label="Reference" :value="$journal->reference" />
                <x-form.input name="narration" label="Details" :value="$journal->narration" required wrapper-class="md:col-span-2" />
            </div>
        </x-card>

        <x-card title="Lines" :padded="false">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th class="min-w-64">Account</th><th class="min-w-48">Note</th><th class="min-w-52">Project</th><th class="w-40 text-right">Debit</th><th class="w-40 text-right">Credit</th><th class="w-12"></th></tr></thead>
                    <tbody data-journal-rows></tbody>
                    <tfoot>
                        <tr class="font-semibold text-ink-900">
                            <td colspan="3"><x-button size="sm" variant="secondary" icon="plus" data-journal-add>Add Line</x-button></td>
                            <td class="text-right tabular-nums" data-journal-debit>0.00</td>
                            <td class="text-right tabular-nums" data-journal-credit>0.00</td>
                            <td></td>
                        </tr>
                        <tr data-journal-difference-row hidden>
                            <td colspan="3" class="text-right font-medium text-danger-600">Difference</td>
                            <td colspan="2" class="text-right font-semibold tabular-nums text-danger-600" data-journal-difference></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="form-error px-5 pb-4" data-form-error hidden></p>
        </x-card>

        <template data-journal-row>
            <tr data-journal-line>
                <td><div data-field><select name="lines[__I__][account]" class="form-control" data-select data-select-url="{{ route('options.ledger-accounts') }}" data-placeholder="Select account" aria-label="Account"><option value=""></option></select><p class="form-error" data-error hidden></p></div></td>
                <td><input type="text" name="lines[__I__][description]" maxlength="255" class="form-control" aria-label="Note"></td>
                <td><div data-field><select name="lines[__I__][project]" class="form-control" data-select data-select-url="{{ route('options.projects') }}" data-placeholder="No project" data-allow-clear aria-label="Project"><option value=""></option></select><p class="form-error" data-error hidden></p></div></td>
                <td><div data-field><input type="text" inputmode="decimal" name="lines[__I__][debit]" class="form-control text-right tabular-nums" aria-label="Debit" data-journal-amount="debit"><p class="form-error" data-error hidden></p></div></td>
                <td><input type="text" inputmode="decimal" name="lines[__I__][credit]" class="form-control text-right tabular-nums" aria-label="Credit" data-journal-amount="credit"></td>
                <td><button type="button" class="btn btn-ghost btn-icon text-danger-600" data-journal-remove aria-label="Remove line"><x-icon name="trash" size="h-4 w-4" /></button></td>
            </tr>
        </template>

        <x-form.actions class="mt-0">
            <x-button variant="secondary" :href="$journal->exists ? route('accounting.journals.show', $journal) : route('accounting.journals.index')">Cancel</x-button>
            <x-button type="submit" variant="secondary" icon="check" data-journal-save="">Save Draft</x-button>
            <x-button type="submit" icon="arrow-right" data-journal-save="submit" data-journal-submit>Submit for Approval</x-button>
        </x-form.actions>
    </form>
</x-layouts.app>
