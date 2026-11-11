<?php

namespace App\Modules\Accounting\Support;

use App\Models\MoneyAccount;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The "which account" field shared by every payment and receipt form. */
class MoneyAccounts
{
    /** Payment method => kind of account it moves through. */
    public const KIND_FOR_METHOD = ['cash' => 'cash', 'bank' => 'bank', 'cheque' => 'bank', 'mobile' => 'mobile'];

    /** Anyone who records money somewhere may pick an account. */
    public const PAYMENT_PERMISSIONS = [
        'purchases.payment.create', 'contractors.payment.create', 'expenses.pay', 'sales.refund.pay', 'collections.payment.collect',
        'investor.capital.record', 'investor.capital.return', 'investor.profit.payout.post', 'accounting.cashbank.view', 'accounting.cashbank.manage',
    ];

    /** Validation rules for the `money_account` field: needed once the books are switched on. */
    public static function rules(bool $needed = true): array
    {
        return ['money_account' => [Rule::requiredIf($needed && AccountingSettings::enabled()), 'nullable', 'string', 'size:16']];
    }

    /** The chosen account's id, checked against the payment method. */
    public static function resolve(Request $request, string $methodField = 'payment_method'): ?int
    {
        if (! $request->filled('money_account')) {
            return null;
        }
        $account = MoneyAccount::query()->active()->where('public_id', (string) $request->input('money_account'))->first();
        $kind = self::KIND_FOR_METHOD[(string) $request->input($methodField)] ?? null;
        if (! $account || ($kind && $account->kind !== $kind)) {
            throw ValidationException::withMessages(['money_account' => $account ? $account->name.' is not a '.strtolower(MoneyAccount::KINDS[$kind]).' account.' : 'Select an active account.']);
        }

        return $account->id;
    }

    /**
     * The account a payment went through: the one recorded on it, else the default chosen in Accounting
     * Settings for that kind (used for payments entered before accounts existed), else the only active one.
     */
    public static function for(?MoneyAccount $recorded, ?string $method): ?MoneyAccount
    {
        if ($recorded) {
            return $recorded;
        }
        $kind = self::KIND_FOR_METHOD[(string) $method] ?? 'cash';
        if ($default = MoneyAccount::query()->where('public_id', (string) app(Settings::class)->get(self::defaultKey($kind)))->first()) {
            return $default;
        }
        $accounts = MoneyAccount::query()->active()->where('kind', $kind)->limit(2)->get();

        return $accounts->count() === 1 ? $accounts->first() : null;
    }

    public static function defaultKey(string $kind): string
    {
        return 'accounting.default_account.'.$kind;
    }
}
