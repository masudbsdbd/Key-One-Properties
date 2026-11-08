<?php

namespace App\Contracts\Portal;

/** One unit the customer bought or booked. Public ids and business numbers only. */
final readonly class CustomerUnit
{
    /**
     * @param  array<int, array{sequence: int, due_date: string, amount: string, paid: string, status: string}>  $schedule
     */
    public function __construct(
        public string $reference,
        public string $kind,
        public string $status,
        public string $projectName,
        public string $unitNo,
        public string $contractValue,
        public string $paid,
        public string $balance,
        public ?string $nextDueDate,
        public ?string $nextDueAmount,
        public float $constructionProgress,
        public array $schedule,
    ) {}
}
