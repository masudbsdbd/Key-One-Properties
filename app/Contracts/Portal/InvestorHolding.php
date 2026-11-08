<?php

namespace App\Contracts\Portal;

/** One unit an investor holds a share in. Profit figures are approved amounts only. */
final readonly class InvestorHolding
{
    /**
     * @param  array<int, array{reference: string, approved_at: string, net_profit: string, share_percent: string, profit: string}>  $approvedProfit
     */
    public function __construct(
        public string $agreementNo,
        public string $status,
        public string $projectName,
        public string $unitNo,
        public string $sharePercent,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public string $committedCapital,
        public string $capital,
        public string $profitPayable,
        public string $profitPaid,
        public string $profitDue,
        public float $constructionProgress,
        public array $approvedProfit,
    ) {}
}
