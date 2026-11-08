<?php

namespace App\Contracts\Portal;

final readonly class InvestorPortalView
{
    /**
     * @param  array<int, InvestorHolding>  $holdings
     * @param  array<int, StatementLine>  $ledger  debit = capital or profit in, credit = capital returned or profit paid
     */
    public function __construct(
        public string $investorCode,
        public string $name,
        public string $capital,
        public string $profitDue,
        public array $holdings,
        public array $ledger,
    ) {}
}
