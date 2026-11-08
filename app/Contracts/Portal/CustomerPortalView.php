<?php

namespace App\Contracts\Portal;

/** Everything a customer portal may show for one customer. */
final readonly class CustomerPortalView
{
    /**
     * @param  array<int, CustomerUnit>  $units
     * @param  array<int, StatementLine>  $statement
     */
    public function __construct(
        public string $customerCode,
        public string $name,
        public string $balance,
        public array $units,
        public array $statement,
    ) {}
}
