<?php

namespace App\Modules\Reports\Reports;

use App\Models\FlatBooking;
use App\Models\Project;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Every booking and what became of it: turned into a sale, still open, or cancelled with the money refunded. */
class BookingRegisterReport extends BaseReport
{
    public function key(): string
    {
        return 'booking-register';
    }

    public function name(): string
    {
        return 'Bookings & Cancellations';
    }

    public function group(): string
    {
        return 'sales';
    }

    public function basis(): string
    {
        return 'Bookings by booking date; received is posted payments, refunded is refunds paid out';
    }

    public function filters(): array
    {
        return ['project', 'period', 'status'];
    }

    public function statuses(): array
    {
        return FlatBooking::STATUSES;
    }

    public function search(): array
    {
        return ['flat_bookings.booking_no'];
    }

    public function columns(): array
    {
        return ['Booking' => 'text', 'Date' => 'date', 'Customer' => 'text', 'Unit' => 'text', 'Agreed Price' => 'money', 'Status' => 'text', 'Received' => 'money', 'Refunded' => 'money'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = FlatBooking::query()->whereIn('flat_bookings.project_id', Project::query()->visibleTo($user)->select('projects.id'))
            ->with(['customer:id,full_name', 'flat:id,flat_no', 'project:id,code'])->select('flat_bookings.*')
            ->selectRaw('(SELECT s.sale_no FROM flat_sales s WHERE s.booking_id = flat_bookings.id ORDER BY s.id DESC LIMIT 1) AS sale_no')
            ->selectRaw("(SELECT COALESCE(SUM(p.amount), 0) FROM customer_payments p WHERE p.booking_id = flat_bookings.id AND p.status = 'posted') AS received_amount")
            ->selectRaw("(SELECT COALESCE(SUM(COALESCE(r.net_amount, r.amount)), 0) FROM customer_refunds r WHERE r.booking_id = flat_bookings.id AND r.status = 'paid') AS refunded_amount");
        if ($project = self::project($request, $user)) {
            $query->where('flat_bookings.project_id', $project->id);
        }
        if (array_key_exists($status = (string) $request->input('status'), FlatBooking::STATUSES)) {
            $query->where('flat_bookings.status', $status);
        }

        return $this->inPeriod($query, $request, 'flat_bookings.booking_date')->orderByDesc('flat_bookings.booking_date')->orderByDesc('flat_bookings.id');
    }

    public function row(mixed $b): array
    {
        return [$b->booking_no, $b->booking_date, $b->customer->full_name, $b->project->code.' · '.$b->flat->flat_no, $b->agreed_price,
            $b->sale_no ? 'Sale '.$b->sale_no : (FlatBooking::STATUSES[$b->status] ?? $b->status), Money::amount((string) $b->received_amount), Money::amount((string) $b->refunded_amount)];
    }

    public function totals(Builder $query): ?array
    {
        $rows = (clone $query)->reorder()->get();
        $sum = fn (string $field) => Money::amount(Money::add('0', ...$rows->map(fn ($r) => (string) $r->{$field})->all()));

        return ['Total · '.$rows->count().' bookings', null, null, null, $sum('agreed_price'), null, $sum('received_amount'), $sum('refunded_amount')];
    }
}
