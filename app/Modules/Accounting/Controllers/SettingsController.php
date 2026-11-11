<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FinancialEvent;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\MoneyAccount;
use App\Modules\Accounting\Services\Balances;
use App\Modules\Accounting\Services\EventPoster;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Support\AccountingSettings;
use App\Modules\Accounting\Support\ChartOfAccounts;
use App\Modules\Accounting\Support\MoneyAccounts;
use App\Support\Ajax;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->can('accounting.settings.manage'), 403);
        $settings = app(Settings::class);

        return view('accounting.settings.edit', [
            'enabled' => AccountingSettings::enabled(),
            'startDate' => AccountingSettings::startDate(),
            'firstRecord' => FinancialEvent::query()->min('effective_at'),
            'fiscalMonth' => AccountingSettings::fiscalStartMonth(),
            'backlog' => AccountingSettings::enabled() ? EventPoster::backlog()->count() : 0,
            'accounts' => MoneyAccount::query()->active()->orderBy('name')->get()->groupBy('kind'),
            'defaults' => collect(MoneyAccount::KINDS)->map(fn ($label, $kind) => $settings->get(MoneyAccounts::defaultKey($kind))),
            'mapped' => LedgerAccount::query()->whereNotNull('system_key')->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Settings $settings, EventPoster $poster): JsonResponse
    {
        abort_unless($request->user()->can('accounting.settings.manage'), 403);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'start_date' => [Rule::requiredIf($request->boolean('enabled')), 'nullable', 'date'],
            'fiscal_start_month' => ['required', 'integer', 'between:1,12'],
        ], ['start_date.required' => 'Choose the first day of the books.']);

        $start = $data['start_date'] ? Carbon::parse($data['start_date'])->toDateString() : null;
        $earliest = JournalEntry::query()->posted()->where('journal_type', 'system')->min('accounting_date');
        if ($start && $earliest && $start > $earliest) {
            throw ValidationException::withMessages(['start_date' => 'Transactions are already posted from '.Carbon::parse($earliest)->format('j M Y').'. The start date cannot be later.']);
        }
        if ((int) $data['fiscal_start_month'] !== AccountingSettings::fiscalStartMonth() && DB::table('fiscal_years')->exists()) {
            throw ValidationException::withMessages(['fiscal_start_month' => 'Fiscal years already exist. The first month can no longer be changed.']);
        }

        $before = ['enabled' => AccountingSettings::enabled(), 'start_date' => AccountingSettings::startDate()?->toDateString(), 'fiscal_start_month' => AccountingSettings::fiscalStartMonth()];
        $settings->put([
            AccountingSettings::ENABLED => $data['enabled'] ? '1' : '0',
            AccountingSettings::START_DATE => $start,
            AccountingSettings::FISCAL_START_MONTH => (string) $data['fiscal_start_month'],
        ]);
        $after = ['enabled' => (bool) $data['enabled'], 'start_date' => $start, 'fiscal_start_month' => (int) $data['fiscal_start_month']];
        activity('accounting')->event('accounting_settings_updated')->causedBy($request->user())
            ->withProperties(['name' => 'Accounting settings', 'old' => $before, 'attributes' => $after])->log('accounting_settings_updated');

        $result = $data['enabled'] ? $poster->postBacklog(2000) : ['posted' => 0, 'failed' => 0];

        return Ajax::success($data['enabled'] ? 'Books are on. '.$result['posted'].' posted'.($result['failed'] ? ', '.$result['failed'].' need attention' : '').'.' : 'Books are off.',
            ['redirect' => route('accounting.settings.edit')]);
    }

    public function defaults(Request $request, Settings $settings): JsonResponse
    {
        abort_unless($request->user()->can('accounting.settings.manage'), 403);
        $data = $request->validate(['defaults' => ['array'], 'defaults.*' => ['nullable', 'string', 'size:16']]);
        $values = [];
        foreach (array_keys(MoneyAccount::KINDS) as $kind) {
            $id = $data['defaults'][$kind] ?? null;
            if ($id && ! MoneyAccount::query()->active()->where('kind', $kind)->where('public_id', $id)->exists()) {
                throw ValidationException::withMessages(["defaults.$kind" => 'Select an active '.strtolower(MoneyAccount::KINDS[$kind]).' account.']);
            }
            $values[MoneyAccounts::defaultKey($kind)] = $id;
        }
        $settings->put($values);
        activity('accounting')->event('default_accounts_updated')->causedBy($request->user())->withProperties(['name' => 'Default accounts'])->log('default_accounts_updated');

        return Ajax::success('Default accounts saved.');
    }

    public function mappingForm(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.settings.manage'), 403);
        $current = LedgerAccount::query()->where('system_key', (string) $request->input('key'))->firstOrFail();

        return Ajax::success(null, ['title' => Str::headline($current->system_key), 'html' => view('accounting.settings._mapping', ['current' => $current])->render()]);
    }

    /** Point a posting key at another account of the same type. */
    public function mapping(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.settings.manage'), 403);
        $data = $request->validate(['key' => ['required', 'string', 'max:40'], 'account' => ['required', 'string', 'size:16']]);
        $current = LedgerAccount::query()->where('system_key', $data['key'])->firstOrFail();
        $target = LedgerAccount::query()->postable()->where('public_id', $data['account'])->first();
        if (! $target || $target->category !== $current->category || ($target->system_key && $target->isNot($current)) || $target->moneyAccount()->exists()) {
            throw ValidationException::withMessages(['account' => 'Choose a free '.strtolower(ChartOfAccounts::CATEGORIES[$current->category]).' account.']);
        }
        if ($target->isNot($current)) {
            DB::transaction(function () use ($current, $target, $data) {
                $current->forceFill(['system_key' => null])->save();
                $target->forceFill(['system_key' => $data['key']])->save();
            });
            activity('accounting')->event('account_mapping_changed')->causedBy($request->user())->performedOn($target)
                ->withProperties(['name' => $data['key'], 'old' => ['account' => $current->label()], 'attributes' => ['account' => $target->label()]])->log('account_mapping_changed');
        }

        return Ajax::success('Mapping saved.', ['redirect' => route('accounting.settings.edit')]);
    }

    public function opening(Request $request): View
    {
        abort_unless($request->user()->can('accounting.openingbalance.manage'), 403);
        $journal = JournalEntry::query()->where('journal_type', 'opening')->where('source_key', 'like', 'opening:books:%')->whereNull('reversed_by_id')->posted()->latest('id')->first();
        $current = $journal ? $journal->lines()->get()->groupBy('account_id')->map(fn ($lines) => Money::sub((string) $lines->sum('debit'), (string) $lines->sum('credit'))) : collect();
        $accounts = LedgerAccount::query()->postable()->whereIn('category', ['asset', 'liability', 'equity'])->whereDoesntHave('moneyAccount')
            ->where(fn ($q) => $q->whereNull('system_key')->orWhere('system_key', '!=', 'opening_equity'))->orderBy('code')->get()
            ->each(fn (LedgerAccount $a) => $a->setAttribute('opening', isset($current[$a->id]) ? Balances::natural($a, $current[$a->id]) : null));

        return view('accounting.settings.opening', [
            'accounts' => $accounts->groupBy('category'), 'journal' => $journal,
            'date' => $journal?->accounting_date ?? AccountingSettings::startDate() ?? Ledger::today(),
            'moneyAccounts' => MoneyAccount::query()->orderBy('name')->get(),
        ]);
    }

    public function saveOpening(Request $request, Ledger $ledger): JsonResponse
    {
        abort_unless($request->user()->can('accounting.openingbalance.manage'), 403);
        $request->merge(['amounts' => collect((array) $request->input('amounts', []))->map(fn ($v) => str_replace(',', '', (string) $v))->all()]);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'amounts' => ['array', 'max:500'],
            'amounts.*' => ['nullable', 'numeric', 'max:9999999999999999.99', 'decimal:0,2'],
        ]);
        $accounts = LedgerAccount::query()->postable()->whereIn('category', ['asset', 'liability', 'equity'])->whereDoesntHave('moneyAccount')
            ->whereIn('public_id', array_keys($data['amounts'] ?? []))->get()->keyBy('public_id');
        $lines = [];
        $net = '0';
        foreach ($data['amounts'] ?? [] as $id => $amount) {
            $account = $accounts[$id] ?? null;
            if (! $account || $account->system_key === 'opening_equity' || $amount === null || $amount === '' || Money::cmp($amount, 0) === 0) {
                continue;
            }
            // A positive amount sits on the account's own side; a negative one on the other.
            $debit = $account->isDebitNormal() === (Money::cmp($amount, 0) > 0);
            $value = Money::cmp($amount, 0) > 0 ? Money::amount($amount) : Money::neg($amount);
            $lines[] = ['account' => $account, $debit ? 'debit' : 'credit' => $value];
            $net = $debit ? Money::add($net, $value) : Money::sub($net, $value);
        }
        if (Money::cmp($net, 0) !== 0) {
            $lines[] = ['account' => 'opening_equity', Money::cmp($net, 0) > 0 ? 'credit' : 'debit' => Money::cmp($net, 0) > 0 ? $net : Money::neg($net)];
        }
        $date = Carbon::parse($data['date']);

        DB::transaction(function () use ($ledger, $lines, $date, $request) {
            $previous = JournalEntry::query()->where('journal_type', 'opening')->where('source_key', 'like', 'opening:books:%')->whereNull('reversed_by_id')->posted()->lockForUpdate()->get();
            foreach ($previous as $journal) {
                $ledger->reverse($journal, $journal->accounting_date, 'Opening balances entered again', $request->user());
            }
            if ($lines !== []) {
                $ledger->post(['type' => 'opening', 'date' => $date, 'narration' => 'Opening balances', 'source_key' => 'opening:books:'.Str::uuid()], $lines, $request->user());
            }
        });
        activity('accounting')->event('opening_balances_saved')->causedBy($request->user())->withProperties(['name' => 'Opening balances', 'attributes' => ['accounts' => count($lines)]])->log('opening_balances_saved');

        return Ajax::success('Opening balances saved.', ['redirect' => route('accounting.opening.edit')]);
    }
}
