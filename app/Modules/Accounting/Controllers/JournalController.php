<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Project;
use App\Modules\Accounting\Services\Ledger;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class JournalController extends Controller
{
    private const ACTION_PERMISSIONS = [
        'submit' => ['accounting.journal.create'], 'approve' => ['accounting.journal.approve'], 'post' => ['accounting.journal.post'],
        'approve-post' => ['accounting.journal.approve', 'accounting.journal.post'], 'reject' => ['accounting.journal.approve'], 'reverse' => ['accounting.journal.reverse'],
    ];

    public function __construct(private Ledger $ledger) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('accounting.journal.view'), 403);

        return view('accounting.journals.index');
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.journal.view'), 403);
        $query = JournalEntry::query()->with(['source', 'reversalOf:id,public_id,journal_no', 'reversedBy:id,public_id,journal_no'])
            ->when($request->input('status'), fn (Builder $q, $s) => $s === 'reversed' ? $q->whereNotNull('reversed_by_id') : $q->where('status', $s))
            ->when($request->input('type'), fn (Builder $q, $t) => $q->where('journal_type', $t));
        TableQuery::period($query, $request, 'accounting_date');
        $rows = TableQuery::apply($query, $request, ['journal_no', 'narration', 'reference'], ['date' => 'accounting_date', 'amount' => 'total_amount'], 'date', 'desc');

        return Ajax::table(view('accounting.journals._rows', ['journals' => $rows])->render(), $rows);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('accounting.journal.create'), 403);

        return view('accounting.journals.form', ['journal' => new JournalEntry(['accounting_date' => Ledger::today()]), 'lines' => []]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accounting.journal.create'), 403);

        return $this->save($request, null);
    }

    public function edit(Request $request, JournalEntry $journal): View
    {
        abort_unless($request->user()->can('accounting.journal.create') && $this->editable($journal), 403);
        $journal->load(['lines.account', 'lines.project']);

        return view('accounting.journals.form', ['journal' => $journal, 'lines' => $journal->lines->map(fn ($l) => [
            'account' => ['id' => $l->account->public_id, 'text' => $l->account->label()],
            'project' => $l->project ? ['id' => $l->project->public_id, 'text' => $l->project->code.' · '.$l->project->name] : null,
            'description' => $l->description, 'debit' => (float) $l->debit ? $l->debit : '', 'credit' => (float) $l->credit ? $l->credit : '',
        ])->all()]);
    }

    public function update(Request $request, JournalEntry $journal): JsonResponse
    {
        abort_unless($request->user()->can('accounting.journal.create') && $this->editable($journal), 403);

        return $this->save($request, $journal);
    }

    public function destroy(Request $request, JournalEntry $journal): JsonResponse
    {
        abort_unless($request->user()->can('accounting.journal.create') && $this->editable($journal), 403);
        $journal->delete();

        return Ajax::success($journal->journal_no.' deleted.', ['redirect' => route('accounting.journals.index')]);
    }

    public function show(Request $request, JournalEntry $journal): View
    {
        abort_unless($request->user()->can('accounting.journal.view'), 403);
        $journal->load(['lines.account', 'lines.project', 'lines.flat', 'lines.customer', 'lines.supplier', 'lines.contractor', 'lines.investor', 'lines.moneyAccount',
            'period', 'source', 'creator', 'approver', 'poster', 'reversalOf', 'reversedBy']);

        return view('accounting.journals.show', [
            'journal' => $journal,
            'editable' => $this->editable($journal),
            'activity' => Activity::query()->where('subject_type', $journal->getMorphClass())->where('subject_id', $journal->id)->with('causer')->latest()->limit(30)->get(),
        ]);
    }

    public function decide(Request $request, JournalEntry $journal, string $action): JsonResponse
    {
        abort_unless($request->user()->can(self::ACTION_PERMISSIONS[$action]), 403);
        $user = $request->user();
        match ($action) {
            'submit' => $this->ledger->submit($journal, $user),
            'approve' => $this->ledger->approve($journal, $user),
            'post' => $this->ledger->postDraft($journal, $user),
            'approve-post' => $this->ledger->postDraft($this->ledger->approve($journal, $user), $user),
        };
        $this->audit($request, $journal, $action);

        return Ajax::success($journal->journal_no.' '.['submit' => 'submitted', 'approve' => 'approved', 'post' => 'posted', 'approve-post' => 'posted'][$action].'.');
    }

    public function reasonForm(Request $request, JournalEntry $journal, string $action): JsonResponse
    {
        abort_unless($request->user()->can(self::ACTION_PERMISSIONS[$action]), 403);

        return Ajax::success(null, ['title' => ($action === 'reverse' ? 'Reverse ' : 'Reject ').$journal->journal_no,
            'html' => view('accounting.journals._reason', ['journal' => $journal, 'action' => $action])->render()]);
    }

    public function reason(Request $request, JournalEntry $journal, string $action): JsonResponse
    {
        abort_unless($request->user()->can(self::ACTION_PERMISSIONS[$action]), 403);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'date' => [Rule::requiredIf($action === 'reverse'), 'nullable', 'date', 'after_or_equal:'.$journal->accounting_date->toDateString()],
        ], ['date.after_or_equal' => 'The reversal cannot be dated before the journal.']);

        if ($action === 'reverse') {
            $reversal = $this->ledger->reverse($journal, Carbon::parse($data['date']), $data['reason'], $request->user());
            $this->audit($request, $journal, 'reverse', ['reason' => $data['reason'], 'reversal' => $reversal->journal_no]);

            return Ajax::success($journal->journal_no.' reversed by '.$reversal->journal_no.'.', ['redirect' => route('accounting.journals.show', $reversal)]);
        }
        $this->ledger->reject($journal, $data['reason'], $request->user());
        $this->audit($request, $journal, 'reject', ['reason' => $data['reason']]);

        return Ajax::success($journal->journal_no.' rejected.', ['redirect' => route('accounting.journals.show', $journal)]);
    }

    private function editable(JournalEntry $journal): bool
    {
        return $journal->journal_type === 'manual' && in_array($journal->status, ['draft', 'rejected'], true);
    }

    private function save(Request $request, ?JournalEntry $journal): JsonResponse
    {
        $rows = collect((array) $request->input('lines', []))->filter(fn ($l) => is_array($l))->values();
        $request->merge(['lines' => $rows->map(fn (array $l) => [
            'debit' => str_replace(',', '', (string) ($l['debit'] ?? '')), 'credit' => str_replace(',', '', (string) ($l['credit'] ?? '')),
        ] + $l)->all()]);
        $data = $request->validate([
            'accounting_date' => ['required', 'date'],
            'narration' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:80'],
            'lines' => ['required', 'array', 'min:2', 'max:100'],
            'lines.*.account' => ['nullable', 'string', 'size:16'],
            'lines.*.project' => ['nullable', 'string', 'size:16'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99', 'decimal:0,2'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99', 'decimal:0,2'],
            'then' => ['nullable', Rule::in(['submit'])],
            'lock_version' => ['nullable', 'integer'],
        ], ['lines.min' => 'A journal needs at least two lines.']);
        if ($journal && (int) ($data['lock_version'] ?? $journal->lock_version) !== $journal->lock_version) {
            return Ajax::error('Someone else changed this journal. Reload and try again.', 409);
        }

        $accounts = LedgerAccount::query()->whereIn('public_id', array_filter(array_column($data['lines'], 'account')))->get()->keyBy('public_id');
        $projects = Project::query()->visibleTo($request->user())->whereIn('public_id', array_filter(array_column($data['lines'], 'project')))->pluck('id', 'public_id');
        $lines = [];
        foreach ($data['lines'] as $i => $line) {
            if (! empty($line['project']) && ! isset($projects[$line['project']])) {
                throw ValidationException::withMessages(["lines.$i.project" => 'Select a project you can access.']);
            }
            $lines[] = [
                'account' => $accounts[$line['account'] ?? ''] ?? null, 'description' => $line['description'] ?? null,
                'debit' => $line['debit'] ?: 0, 'credit' => $line['credit'] ?: 0, 'project_id' => $projects[$line['project'] ?? ''] ?? null,
            ];
        }

        $saved = $this->ledger->saveDraft($journal, ['date' => Carbon::parse($data['accounting_date']), 'narration' => $data['narration'], 'reference' => $data['reference'] ?? null], $lines, $request->user());
        $this->audit($request, $saved, $journal ? 'update' : 'create');
        if (($data['then'] ?? null) === 'submit') {
            $this->ledger->submit($saved, $request->user());
            $this->audit($request, $saved, 'submit');
        }

        return Ajax::success($saved->journal_no.(($data['then'] ?? null) === 'submit' ? ' submitted.' : ' saved.'), ['redirect' => route('accounting.journals.show', $saved)]);
    }

    private function audit(Request $request, JournalEntry $journal, string $action, array $extra = []): void
    {
        activity('accounting')->event('journal_'.str_replace('-', '_', $action))->causedBy($request->user())->performedOn($journal)
            ->withProperties(['name' => $journal->journal_no, 'attributes' => ['amount' => (string) $journal->total_amount] + $extra])->log('journal_'.$action);
    }
}
