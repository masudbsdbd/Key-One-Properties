<?php

namespace App\Support\Export;

use App\Support\Format;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * One tabular report rendered as CSV, PDF or a print page, picked by ?format=csv|pdf|print.
 * Column types: text, money, qty, date, number. Money stays a plain decimal in CSV.
 */
class Report
{
    public const MAX_PDF_ROWS = 3000;

    /** @var array<int, array{label: string, type: string}> */
    private array $columns = [];

    /** @var array<string, string> */
    private array $meta = [];

    private ?array $totals = null;

    private string $orientation = 'portrait';

    private Closure $rows;

    public function __construct(private string $title) {}

    public static function make(string $title): self
    {
        return new self($title);
    }

    /** @param array<string, string> $columns label => type */
    public function columns(array $columns): self
    {
        foreach ($columns as $label => $type) {
            $this->columns[] = ['label' => $label, 'type' => $type];
        }
        $this->orientation = count($columns) > 6 ? 'landscape' : 'portrait';

        return $this;
    }

    /** @param Closure(): iterable<array<int, mixed>> $rows */
    public function rows(Closure $rows): self
    {
        $this->rows = $rows;

        return $this;
    }

    /** Filter summary shown under the title, e.g. ['Project' => 'Lake View']. */
    public function meta(array $meta): self
    {
        $this->meta = array_filter($meta, fn ($v) => $v !== null && $v !== '');

        return $this;
    }

    /** @param array<int, mixed> $totals same positions as the columns; null cells stay empty */
    public function totals(array $totals): self
    {
        $this->totals = $totals;

        return $this;
    }

    public function respond(Request $request): Response
    {
        return match ($request->input('format', 'csv')) {
            'pdf' => $this->pdf(),
            'print' => $this->print(),
            default => $this->csv(),
        };
    }

    public function csv(): Response
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            $this->writeCsv($out);
            fclose($out);
        }, $this->filename('csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Writes the CSV to an open stream and returns the number of data rows. */
    public function writeCsv($out): int
    {
        $count = 0;
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_column($this->columns, 'label'));
        foreach (($this->rows)() as $row) {
            fputcsv($out, array_map(fn ($v, $i) => $this->plain($v, $this->columns[$i]['type']), $row, array_keys($row)));
            $count++;
        }
        if ($this->totals) {
            fputcsv($out, array_map(fn ($v, $i) => $v === null ? '' : $this->plain($v, $this->columns[$i]['type']), $this->totals, array_keys($this->totals)));
        }

        return $count;
    }

    /**
     * PDF bytes and row count (same layout and row limit as the download).
     *
     * @return array{0: string, 1: int}
     */
    public function pdfBytes(): array
    {
        Format::$plainSymbol = true;
        try {
            $data = $this->viewData(true);
            if (count($data['rows']) > self::MAX_PDF_ROWS) {
                throw new HttpException(422, 'Too many rows for a PDF. Narrow the filters or use CSV.');
            }
            $dompdf = Pdf::loadView('exports.report', $data)->setPaper('a4', $this->orientation)->getDomPDF();
            $dompdf->render();
            $canvas = $dompdf->getCanvas();
            $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 28, 'Page {PAGE_NUM} of {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.6, 0.6, 0.6]);

            return [$dompdf->output(), count($data['rows'])];
        } finally {
            Format::$plainSymbol = false;
        }
    }

    public function print(): Response
    {
        return response()->view('exports.report', $this->viewData(false));
    }

    public function pdf(): Response
    {
        Format::$plainSymbol = true;
        try {
            $data = $this->viewData(true);
            if (count($data['rows']) > self::MAX_PDF_ROWS) {
                throw new HttpException(422, 'Too many rows for a PDF. Narrow the filters or use CSV.');
            }

            return self::pdfResponse(Pdf::loadView('exports.report', $data)->setPaper('a4', $this->orientation), $this->filename('pdf'));
        } finally {
            Format::$plainSymbol = false;
        }
    }

    /** Adds "Page x of y" to every page and returns the download. */
    public static function pdfResponse(\Barryvdh\DomPDF\PDF $pdf, string $filename): Response
    {
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 28, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7, [0.6, 0.6, 0.6]);

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function viewData(bool $pdf): array
    {
        $rows = [];
        foreach (($this->rows)() as $row) {
            $rows[] = array_map(fn ($v, $i) => $this->display($v, $this->columns[$i]['type']), $row, array_keys($row));
        }

        return [
            'title' => $this->title,
            'meta' => $this->meta,
            'columns' => $this->columns,
            'rows' => $rows,
            'totals' => $this->totals ? array_map(fn ($v, $i) => $v === null ? '' : $this->display($v, $this->columns[$i]['type']), $this->totals, array_keys($this->totals)) : null,
            'pdf' => $pdf,
            'orientation' => $this->orientation,
        ];
    }

    private function plain(mixed $value, string $type): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof CarbonInterface => $value->toDateString(),
            default => (string) $value,
        };
    }

    private function display(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return $type === 'text' ? '' : '—';
        }

        return match ($type) {
            'money' => Format::money((string) $value),
            'qty' => Format::qty((string) $value),
            'number' => number_format((float) $value),
            'date' => $value instanceof CarbonInterface ? Format::date($value) : (string) $value,
            default => (string) $value,
        };
    }

    public function filename(string $ext): string
    {
        return Str::slug($this->title).'-'.now(config('app.display_timezone'))->format('Ymd-Hi').'.'.$ext;
    }
}
