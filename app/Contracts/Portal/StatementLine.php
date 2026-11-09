<?php

namespace App\Contracts\Portal;

final readonly class StatementLine
{
    public function __construct(
        public string $date,
        public string $type,
        public string $description,
        public string $debit,
        public string $credit,
        public string $balance,
    ) {}
}
