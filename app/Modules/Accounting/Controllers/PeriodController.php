<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Services\PeriodControl;
use App\Modules\Accounting\Services\Periods;
use App\Support\Ajax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PeriodController extends Controller
{
    public function __construct(private PeriodControl $control) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->canAny(['accounting.period.lock', 'accounting.period.close', 'accounting.reports.view']), 403);
        Periods::for(Ledger::today()); // the current fiscal year always exists

        return view('accounting.periods.index');
    }

    public function list(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['accounting.period.lock', 'accounting.period.close', 'accounting.reports.view']), 403);
        $years = FiscalYear::query()->with(['periods.locker'])->orderByDesc('start_date')->get();
        $counts = JournalEntry::query()->posted()->whereNotNull('accounting_period_id')->groupBy('accounting_period_id')->selectRaw('accounting_period_id, COUNT(*) AS n')->pluck('n', 'accounting_period_id');

        return Ajax::success(null, ['html' => view('accounting.periods._list', [
            'years' => $years, 'counts' => $counts, 'today' => Ledger::today(),
            'canLock' => $request->user()->can('accounting.period.lock'), 'canClose' => $request->user()->can('accounting.period.close'),
        ])->render()]);
    }

    public function lock(Request $request, AccountingPeriod $period): JsonResponse
    {
        abort_unless($request->user()->can('accounting.period.lock'), 403);
        $this->control->lock($period, $request->user());
        $this->audit($request, $period, 'period_locked', $period->name);

        return Ajax::success($period->name.' locked.');
    }

    public function reasonForm(Request $request, string $type, string $id): JsonResponse
    {
        $subject = $type === 'period' ? AccountingPeriod::findByPublicIdOrFail($id) : FiscalYear::findByPublicIdOrFail($id);
        abort_unless($request->user()->can($type === 'period' ? 'accounting.period.lock' : 'accounting.period.close'), 403);

        return Ajax::success(null, ['title' => ($type === 'period' ? 'Unlock ' : 'Reopen ').$subject->name,
            'html' => view('accounting.periods._reason', ['type' => $type, 'subject' => $subject])->render()]);
    }

    public function unlock(Request $request, AccountingPeriod $period): JsonResponse
    {
        abort_unless($request->user()->can('accounting.period.lock'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->control->unlock($period, $data['reason'], $request->user());
        $this->audit($request, $period, 'period_unlocked', $period->name, $data['reason']);

        return Ajax::success($period->name.' unlocked.');
    }

    public function close(Request $request, FiscalYear $year): JsonResponse
    {
        abort_unless($request->user()->can('accounting.period.close'), 403);
        $this->control->closeYear($year, $request->user());
        $this->audit($request, $year, 'fiscal_year_closed', $year->name);

        return Ajax::success($year->name.' closed.');
    }

    public function reopen(Request $request, FiscalYear $year): JsonResponse
    {
        abort_unless($request->user()->can('accounting.period.close'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->control->reopenYear($year, $data['reason'], $request->user());
        $this->audit($request, $year, 'fiscal_year_reopened', $year->name, $data['reason']);

        return Ajax::success($year->name.' reopened.');
    }

    private function audit(Request $request, $subject, string $event, string $name, ?string $reason = null): void
    {
        activity('accounting')->event($event)->causedBy($request->user())->performedOn($subject)
            ->withProperties(['name' => $name, 'attributes' => array_filter(['reason' => $reason])])->log($event);
    }
}
