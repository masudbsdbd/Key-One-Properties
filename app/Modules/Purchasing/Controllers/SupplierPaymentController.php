<?php

namespace App\Modules\Purchasing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Support\Ajax;
use App\Support\Money;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierPaymentController extends Controller
{
    public function __construct(private PurchasingService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('purchases.view'), 403);

        return view('purchasing.payments.index');
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('purchases.view'), 403);

        $query = SupplierPayment::query()->with('supplier:id,public_id,name');
        if (array_key_exists($status = (string) $request->input('status'), SupplierPayment::STATUSES)) {
            $query->where('status', $status);
        }
        if ($supplier = Supplier::findByPublicId($request->input('supplier'))) {
            $query->where('supplier_id', $supplier->id);
        }

        $payments = TableQuery::apply($query, $request, ['payment_no', 'payment_reference'], ['date' => 'payment_date', 'amount' => 'amount'], 'date', 'desc');

        return Ajax::table(view('purchasing.payments._rows', ['payments' => $payments, 'showSupplier' => ! $supplier])->render(), $payments);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('purchases.payment.create'), 403);

        return Ajax::success(null, ['title' => 'New Supplier Payment', 'html' => view('purchasing.payments._form', [
            'supplier' => Supplier::findByPublicId($request->input('supplier')), 'key' => (string) Str::uuid(),
        ])->render()]);
    }

    /** Approved bills with an outstanding amount. */
    public function allocatable(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('purchases.payment.create'), 403);
        $supplier = Supplier::findByPublicId($request->input('supplier')) ?? abort(404);

        $bills = SupplierBill::query()->whereBelongsTo($supplier)->where('status', 'approved')->with('allocations.payment')->orderBy('due_date')->orderBy('invoice_date')->get()
            ->filter(fn (SupplierBill $b) => Money::isPositive($b->outstanding()));

        return Ajax::success(null, ['html' => view('purchasing.payments._allocatable', ['bills' => $bills, 'balance' => $supplier->balance()])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('purchases.payment.create'), 403);
        $request->merge([
            'amount' => str_replace(',', '', (string) $request->input('amount')),
            'allocations' => collect((array) $request->input('allocations', []))->map(fn ($v) => $v === null ? null : str_replace(',', '', (string) $v))->all(),
        ]);
        $data = $request->validate([
            'supplier' => ['required', 'string', 'size:16'],
            'payment_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(array_keys(SupplierPayment::METHODS))],
            'payment_reference' => [Rule::requiredIf(in_array($request->input('payment_method'), ['bank', 'cheque', 'mobile'], true)), 'nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['nullable', 'array', 'max:200'],
            'allocations.*' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'idempotency_key' => ['required', 'uuid'],
        ], ['payment_reference.required' => 'Enter the transaction or cheque reference.']);
        $supplier = Supplier::findByPublicId($data['supplier']) ?? throw ValidationException::withMessages(['supplier' => 'Select a supplier.']);

        $wanted = collect($data['allocations'] ?? [])->filter(fn ($v) => $v !== null && Money::isPositive((string) $v));
        $bills = SupplierBill::query()->whereBelongsTo($supplier)->whereIn('public_id', $wanted->keys())->pluck('id', 'public_id');
        if ($bills->count() !== $wanted->count()) {
            throw ValidationException::withMessages(['allocations' => 'Some bills could not be found for this supplier.']);
        }
        $allocations = $wanted->mapWithKeys(fn ($v, $publicId) => [$bills[$publicId] => (string) $v])->all();

        try {
            $payment = $this->service->createPayment($supplier, (string) $data['amount'], $allocations, $data, $request->user(), $data['idempotency_key']);
        } catch (ValidationException $e) {
            $byId = $bills->flip();
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => [preg_replace_callback('/^allocations\.(\d+)/', fn ($x) => 'allocations.'.($byId[(int) $x[1]] ?? 'x'), $k) => $m])->all());
        }

        return Ajax::success('Payment '.$payment->payment_no.' recorded.', ['redirect' => route('supplier-payments.show', $payment)]);
    }

    public function show(Request $request, SupplierPayment $payment): View
    {
        abort_unless($request->user()->can('purchases.view'), 403);
        $payment->load(['supplier', 'allocations.bill', 'creator']);

        return view('purchasing.payments.show', ['payment' => $payment]);
    }

    public function approve(Request $request, SupplierPayment $payment): JsonResponse
    {
        abort_unless($request->user()->can('purchases.payment.approve'), 403);
        try {
            $this->service->approvePayment($payment, $request->user());
        } catch (ValidationException $e) {
            throw new ConflictHttpException(collect($e->errors())->flatten()->first());
        }

        return Ajax::success('Payment approved.');
    }

    public function reasonForm(Request $request, SupplierPayment $payment, string $action): JsonResponse
    {
        abort_unless($request->user()->can('purchases.payment.approve'), 403);

        return Ajax::success(null, ['title' => Str::headline($action).' '.$payment->payment_no, 'html' => view('partials.reason-form', [
            'action' => route('supplier-payments.'.$action, $payment), 'button' => Str::headline($action), 'reload' => true,
        ])->render()]);
    }

    public function reject(Request $request, SupplierPayment $payment): JsonResponse
    {
        abort_unless($request->user()->can('purchases.payment.approve'), 403);
        $this->service->rejectPayment($payment, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Payment rejected.');
    }

    public function reverse(Request $request, SupplierPayment $payment): JsonResponse
    {
        abort_unless($request->user()->can('purchases.payment.approve'), 403);
        $this->service->reversePayment($payment, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Payment reversed.');
    }
}
