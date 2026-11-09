<?php

namespace App\Modules\Investors\Services;

use App\Models\Investor;
use App\Models\InvestorLedgerEntry;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Operational investor ledger. Capital (capital_in / capital_out) and profit (profit_payable /
 * profit_paid) are separate balances. Signed amounts; reversals negate the original row.
 */
class InvestorLedger
{
    public function post(Investor $investor, string $kind, Model $source, CarbonInterface $date, string $amount, string $description, ?int $agreementId = null, ?int $flatId = null): InvestorLedgerEntry
    {
        return InvestorLedgerEntry::query()->firstOrCreate(
            ['source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(), 'entry_kind' => $kind],
            [
                'investor_id' => $investor->id, 'agreement_id' => $agreementId, 'flat_id' => $flatId,
                'entry_date' => $date->toDateString(), 'amount' => Money::amount($amount),
                'description' => mb_substr($description, 0, 255), 'posted_at' => now(),
            ],
        );
    }

    public function reverse(Model $source, string $kind, string $reason): ?InvestorLedgerEntry
    {
        $original = InvestorLedgerEntry::query()->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->where('entry_kind', $kind)->first();
        if (! $original) {
            return null;
        }

        return InvestorLedgerEntry::query()->firstOrCreate(
            ['source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(), 'entry_kind' => 'reversal'],
            [
                'investor_id' => $original->investor_id, 'agreement_id' => $original->agreement_id, 'flat_id' => $original->flat_id,
                'entry_date' => now(config('app.display_timezone'))->toDateString(), 'amount' => Money::neg($original->amount),
                'description' => mb_substr('Reversal of '.$original->description.': '.$reason, 0, 255), 'posted_at' => now(), 'reversal_of_id' => $original->id,
            ],
        );
    }

    /**
     * @return array{capital: string, payable: string, paid: string, profit_due: string}
     */
    public static function balances(Investor $investor, ?int $flatId = null): array
    {
        $rows = InvestorLedgerEntry::query()->where('investor_id', $investor->id)->when($flatId, fn ($q) => $q->where('flat_id', $flatId))->get(['entry_kind', 'amount', 'reversal_of_id']);
        $kindOf = InvestorLedgerEntry::query()->whereIn('id', $rows->pluck('reversal_of_id')->filter())->pluck('entry_kind', 'id');
        $sum = ['capital_in' => '0', 'capital_out' => '0', 'profit_payable' => '0', 'profit_paid' => '0'];
        foreach ($rows as $r) {
            $kind = $r->entry_kind === 'reversal' ? ($kindOf[$r->reversal_of_id] ?? null) : $r->entry_kind;
            if ($kind && isset($sum[$kind])) {
                $sum[$kind] = Money::add($sum[$kind], (string) $r->amount);
            }
        }

        return [
            'capital' => Money::amount(Money::sub($sum['capital_in'], $sum['capital_out'])),
            'payable' => Money::amount($sum['profit_payable']),
            'paid' => Money::amount($sum['profit_paid']),
            'profit_due' => Money::amount(Money::sub($sum['profit_payable'], $sum['profit_paid'])),
        ];
    }
}
