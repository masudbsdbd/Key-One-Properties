<?php

namespace Tests\Feature;

use App\Support\Format;
use Tests\TestCase;

class MoneyFormatTest extends TestCase
{
    public function test_paisa_show_only_when_not_zero(): void
    {
        $this->assertSame('৳ 7,50,000', Format::money('750000.00'));
        $this->assertSame('৳ 7,50,000.01', Format::money('750000.01'));
        $this->assertSame('৳ 7,50,000.53', Format::money('750000.53'));
        $this->assertSame('৳ 7,50,000.50', Format::money('750000.5'));
        $this->assertSame('-৳ 1,250', Format::money('-1250'));
        $this->assertSame('৳ 0', Format::money('0'));
        $this->assertSame('৳ 12', Format::money('12.34', false));
    }
}
