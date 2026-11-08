<?php

namespace App\Support\Export;

use App\Support\Format;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A single business document (order, bill, voucher, note) rendered as a print page or PDF.
 * ?format=pdf downloads; anything else opens the print page.
 */
class Document
{
    private array $parties = [];

    private array $meta = [];

    private array $columns = [];

    private array $lines = [];

    private array $summary = [];

    private ?string $notes = null;

    private array $signatures = ['Prepared by', 'Checked by', 'Approved by'];

    private ?string $stamp = null;

    public function __construct(private string $title, private string $number) {}

    public static function make(string $title, string $number): self
    {
        return new self($title, $number);
    }

    /** Status shown next to the number, e.g. "Approved". */
    public function stamp(?string $stamp): self
    {
        $this->stamp = $stamp;

        return $this;
    }

    /** Up to two boxes, e.g. ['Supplier' => ["Acme", "Dhaka"], 'Deliver to' => [...]]. */
    public function parties(array $parties): self
    {
        $this->parties = array_map(fn ($lines) => array_values(array_filter((array) $lines, fn ($l) => $l !== null && $l !== '')), $parties);

        return $this;
    }

    /** label => value; dates and money are formatted by type prefix "money:" is not needed — pass formatted strings or Carbon dates. */
    public function meta(array $meta): self
    {
        foreach ($meta as $label => $value) {
            if ($value !== null && $value !== '') {
                $this->meta[$label] = $value instanceof CarbonInterface ? Format::date($value) : (string) $value;
            }
        }

        return $this;
    }

    /**
     * @param  array<string, string>  $columns  label => type (text, money, qty, number)
     * @param  iterable<array<int, mixed>>  $rows
     */
    public function lines(array $columns, iterable $rows): self
    {
        $this->columns = collect($columns)->map(fn ($type, $label) => ['label' => $label, 'type' => $type])->values()->all();
        $this->lines = collect($rows)->map(fn ($row) => array_values($row))->all();

        return $this;
    }

    /** label => money amount; the last entry is printed as the grand total. */
    public function summary(array $summary): self
    {
        $this->summary = array_filter($summary, fn ($v) => $v !== null);

        return $this;
    }

    public function notes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function signatures(array $labels): self
    {
        $this->signatures = $labels;

        return $this;
    }

    public function respond(Request $request): Response
    {
        if ($request->input('format') !== 'pdf') {
            return response()->view('exports.document', $this->data(false));
        }

        Format::$plainSymbol = true;
        try {
            return Report::pdfResponse(Pdf::loadView('exports.document', $this->data(true))->setPaper('a4'), Str::slug($this->title.'-'.$this->number).'.pdf');
        } finally {
            Format::$plainSymbol = false;
        }
    }

    private function data(bool $pdf): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->number.($this->stamp ? ' · '.$this->stamp : ''),
            'parties' => $this->parties,
            'meta' => $this->meta,
            'columns' => $this->columns,
            'lines' => array_map(fn ($row) => array_map(fn ($v, $i) => $this->cell($v, $this->columns[$i]['type']), $row, array_keys($row)), $this->lines),
            'summary' => collect($this->summary)->map(fn ($v) => Format::money((string) $v))->all(),
            'notes' => $this->notes,
            'signatures' => $this->signatures,
            'pdf' => $pdf,
        ];
    }

    private function cell(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'money' => Format::money((string) $value),
            'qty' => Format::qty((string) $value),
            'number' => (string) $value,
            default => $value instanceof CarbonInterface ? Format::date($value) : (string) $value,
        };
    }
}
