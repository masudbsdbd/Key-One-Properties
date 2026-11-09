<?php

namespace App\Modules\Sales\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatHandover;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\SalesCancellation;
use App\Models\User;
use App\Modules\Collections\Services\CustomerStatement;
use App\Modules\Flats\Services\FlatService;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Bookings, sales, cancellations, registration and handover. A flat is held by exactly one
 * confirmed booking or approved sale through FlatClaims; money moves only through Collections.
 */
class SalesService
{
    public function __construct(
        private FlatClaims $claims,
        private FlatService $flats,
        private CustomerStatement $statement,
        private FinancialEventRecorder $events,
    ) {}

    // ---------------------------------------------------------------- bookings

    public function saveBooking(?FlatBooking $booking, Flat $flat, Customer $customer, array $data, User $user): FlatBooking
    {
        return DB::transaction(function () use ($booking, $flat, $customer, $data, $user) {
            if ($booking) {
                $booking = FlatBooking::query()->lockForUpdate()->findOrFail($booking->id);
                Approval::ensureStatus($booking->status, ['draft', 'rejected'], 'Only draft bookings can be edited.');
            } else {
                $booking = new FlatBooking(['booking_no' => DocumentNumber::next('BK'), 'status' => 'draft', 'created_by' => $user->id]);
            }
            $this->ensureBookable($flat, $booking);
            $this->checkAmounts($data['agreed_price'], $data['booking_amount_expected'] ?? '0');

            $booking->fill([
                'project_id' => $flat->project_id, 'flat_id' => $flat->id, 'customer_id' => $customer->id,
                'booking_date' => $data['booking_date'], 'booking_expires_at' => $data['booking_expires_at'] ?? null,
                'asking_price_snapshot' => $flat->list_price, 'agreed_price' => Money::amount($data['agreed_price']),
                'booking_amount_expected' => Money::amount($data['booking_amount_expected'] ?? '0'), 'notes' => $data['notes'] ?? null,
            ]);
            if ($booking->exists) {
                $booking->version++;
                $booking->status = 'draft';
            }
            $booking->save();

            return $booking;
        });
    }

    public function submitBooking(FlatBooking $booking, User $user): void
    {
        DB::transaction(function () use ($booking, $user) {
            $booking = FlatBooking::query()->with('flat')->lockForUpdate()->findOrFail($booking->id);
            Approval::ensureStatus($booking->status, ['draft', 'rejected'], 'This booking was already submitted.');
            $this->ensureBookable($booking->flat, $booking);
            $booking->forceFill(['status' => 'pending', 'submitted_by' => $user->id, 'submitted_at' => now(), 'status_reason' => null])->save();
        });
    }

    /** Takes the flat: lock, claim (unique), mark booked. A concurrent winner makes this a 409. */
    public function approveBooking(FlatBooking $booking, User $user): void
    {
        DB::transaction(function () use ($booking, $user) {
            $booking = FlatBooking::query()->lockForUpdate()->findOrFail($booking->id);
            Approval::ensureStatus($booking->status, 'pending', 'Only bookings waiting for approval can be approved.');
            Approval::ensureNotSelf($user, $booking->created_by, 'a booking');
            $flat = Flat::query()->lockForUpdate()->findOrFail($booking->flat_id);

            $this->claims->acquire($flat, $booking, $user);
            $this->flats->setSalesStatus($flat, 'booked', 'Booking '.$booking->booking_no, $user);
            $booking->forceFill(['status' => 'confirmed', 'approved_by' => $user->id, 'approved_at' => now()])->save();

            $this->events->record('sales', $booking, 'booking.created', $booking->agreed_price, $booking->booking_date,
                ['booking_no' => $booking->booking_no, 'flat' => $flat->flat_no, 'booking_amount' => $booking->booking_amount_expected],
                project: $booking->project, flat: $flat, customer: $booking->customer, user: $user);
        });
    }

    public function rejectBooking(FlatBooking $booking, string $reason, User $user): void
    {
        DB::transaction(function () use ($booking, $reason) {
            $booking = FlatBooking::query()->lockForUpdate()->findOrFail($booking->id);
            Approval::ensureStatus($booking->status, 'pending', 'Only bookings waiting for approval can be rejected.');
            $booking->forceFill(['status' => 'rejected', 'status_reason' => $reason])->save();
        });
    }

    /** Draft or pending bookings hold nothing and are withdrawn directly. */
    public function withdrawBooking(FlatBooking $booking, string $reason, User $user): void
    {
        DB::transaction(function () use ($booking, $reason) {
            $booking = FlatBooking::query()->lockForUpdate()->findOrFail($booking->id);
            Approval::ensureStatus($booking->status, ['draft', 'pending', 'rejected'], 'A confirmed booking needs a cancellation request.');
            if ($booking->payments()->whereIn('status', ['pending', 'posted'])->exists()) {
                throw new ConflictHttpException('Money was received for this booking. Request a cancellation instead.');
            }
            $booking->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'status_reason' => $reason])->save();
        });
    }

    // ------------------------------------------------------------------- sales

    public function saveSale(?FlatSale $sale, Flat $flat, Customer $customer, ?FlatBooking $booking, array $data, User $user): FlatSale
    {
        return DB::transaction(function () use ($sale, $flat, $customer, $booking, $data, $user) {
            if ($sale) {
                $sale = FlatSale::query()->lockForUpdate()->findOrFail($sale->id);
                Approval::ensureStatus($sale->status, ['draft', 'rejected'], 'Only draft sales can be edited.');
                $booking = $sale->booking;
            } else {
                $sale = new FlatSale(['sale_no' => DocumentNumber::next('SL'), 'status' => 'draft', 'created_by' => $user->id]);
            }

            if ($booking) {
                $booking = FlatBooking::query()->lockForUpdate()->findOrFail($booking->id);
                Approval::ensureStatus($booking->status, 'confirmed', 'Only a confirmed booking can be converted to a sale.');
                if (! $sale->exists && FlatSale::query()->where('booking_id', $booking->id)->exists()) {
                    throw new ConflictHttpException('This booking already has a sale.');
                }
                $flat = $booking->flat;
                $customer = $booking->customer;
            } else {
                $holder = $this->claims->holder($flat);
                if ($holder || $flat->status !== 'available') {
                    throw new ConflictHttpException("Unit {$flat->flat_no} is not available.");
                }
            }

            $price = Money::amount($data['sale_price']);
            $discount = Money::amount($data['discount_amount'] ?? '0');
            if (! Money::isPositive($price) || Money::cmp($discount, '0') < 0 || Money::cmp($discount, $price) >= 0) {
                throw ValidationException::withMessages(['discount_amount' => 'Discount must be below the sale price.']);
            }

            $sale->fill([
                'booking_id' => $booking?->id, 'project_id' => $flat->project_id, 'flat_id' => $flat->id, 'customer_id' => $customer->id,
                'agreement_date' => $data['agreement_date'], 'sale_price' => $price, 'discount_amount' => $discount,
                'net_contract_value' => Money::amount(Money::sub($price, $discount)), 'notes' => $data['notes'] ?? null,
            ]);
            if ($sale->exists) {
                $sale->version++;
                $sale->status = 'draft';
            }
            $sale->save();

            return $sale;
        });
    }

    public function submitSale(FlatSale $sale, User $user): void
    {
        DB::transaction(function () use ($sale) {
            $sale = FlatSale::query()->lockForUpdate()->findOrFail($sale->id);
            Approval::ensureStatus($sale->status, ['draft', 'rejected'], 'This sale was already submitted.');
            $sale->forceFill(['status' => 'pending', 'status_reason' => null])->save();
        });
    }

    /** Converts the booking's claim (or takes a new one), marks the flat sold and charges the contract value. */
    public function approveSale(FlatSale $sale, User $user): void
    {
        DB::transaction(function () use ($sale, $user) {
            $sale = FlatSale::query()->lockForUpdate()->findOrFail($sale->id);
            Approval::ensureStatus($sale->status, 'pending', 'Only sales waiting for approval can be approved.');
            Approval::ensureNotSelf($user, $sale->created_by, 'a sale');
            $flat = Flat::query()->lockForUpdate()->findOrFail($sale->flat_id);

            if ($sale->booking_id) {
                $booking = FlatBooking::query()->lockForUpdate()->findOrFail($sale->booking_id);
                Approval::ensureStatus($booking->status, 'confirmed', 'The booking is no longer confirmed.');
                $this->claims->transfer($flat, $booking, $sale);
                $booking->forceFill(['status' => 'converted_to_sale'])->save();
            } else {
                $this->claims->acquire($flat, $sale, $user);
            }
            $this->flats->setSalesStatus($flat, 'sold', 'Sale '.$sale->sale_no, $user);
            $sale->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();

            $sale->agreement()->create(['agreement_no' => DocumentNumber::next('AG'), 'status' => 'draft', 'updated_by' => $user->id]);
            $sale->registration()->create(['status' => 'pending', 'updated_by' => $user->id]);
            $sale->handover()->create(['status' => 'pending', 'checklist_json' => []]);

            $this->statement->post($sale->customer, 'sale', $sale, $sale->agreement_date, 'Sale '.$sale->sale_no.' · Unit '.$flat->flat_no, $sale->net_contract_value, '0', $sale->id, $sale->booking_id);
            $this->events->record('sales', $sale, 'sale.confirmed', $sale->net_contract_value, $sale->agreement_date,
                ['sale_no' => $sale->sale_no, 'flat' => $flat->flat_no, 'booking_no' => $sale->booking?->booking_no, 'discount' => $sale->discount_amount],
                project: $sale->project, flat: $flat, customer: $sale->customer, user: $user);
        });
    }

    public function rejectSale(FlatSale $sale, string $reason, User $user): void
    {
        DB::transaction(function () use ($sale, $reason) {
            $sale = FlatSale::query()->lockForUpdate()->findOrFail($sale->id);
            Approval::ensureStatus($sale->status, 'pending', 'Only sales waiting for approval can be rejected.');
            $sale->forceFill(['status' => 'rejected', 'status_reason' => $reason])->save();
        });
    }

    public function updateAgreement(FlatSale $sale, array $data, User $user): void
    {
        DB::transaction(function () use ($sale, $data, $user) {
            $sale = FlatSale::query()->with('agreement')->lockForUpdate()->findOrFail($sale->id);
            Approval::ensureStatus($sale->status, array_diff(FlatSale::ACTIVE, ['cancel_requested']), 'The sale is not active.');
            $sale->agreement->forceFill([
                'signed_at' => $data['signed_at'] ?? null,
                'status' => ! empty($data['signed_at']) ? 'signed' : 'draft',
                'notes' => $data['notes'] ?? null,
                'updated_by' => $user->id,
            ])->save();
        });
    }

    public function updateRegistration(FlatSale $sale, array $data, User $user): void
    {
        DB::transaction(function () use ($sale, $data, $user) {
            $sale = FlatSale::query()->with('registration')->lockForUpdate()->findOrFail($sale->id);
            Approval::ensureStatus($sale->status, ['approved', 'registration_pending', 'registered'], 'Registration is updated before handover.');
            $this->ensureNoPendingCancellation(null, $sale);
            if ($data['status'] === 'registered' && (empty($data['registration_no']) || empty($data['registered_at']))) {
                throw ValidationException::withMessages(['registration_no' => 'Enter the registration number and date.']);
            }
            $sale->registration->forceFill([
                'status' => $data['status'], 'registration_no' => $data['registration_no'] ?? null, 'registered_at' => $data['registered_at'] ?? null,
                'registration_cost' => isset($data['registration_cost']) && $data['registration_cost'] !== '' ? Money::amount($data['registration_cost']) : null,
                'notes' => $data['notes'] ?? null, 'updated_by' => $user->id,
            ])->save();
            $sale->forceFill(['status' => match ($data['status']) {
                'registered' => 'registered', 'in_process' => 'registration_pending', default => 'approved'
            }])->save();
        });
    }

    public function updateHandover(FlatSale $sale, array $data, User $user): void
    {
        DB::transaction(function () use ($sale, $data, $user) {
            $sale = FlatSale::query()->with('handover')->lockForUpdate()->findOrFail($sale->id);
            Approval::ensureStatus($sale->status, ['approved', 'registration_pending', 'registered', 'handover_ready'], 'This sale is not ready for handover.');
            $this->ensureNoPendingCancellation(null, $sale);
            $checklist = array_values(array_intersect(array_keys(FlatHandover::CHECKLIST), $data['checklist'] ?? []));
            if ($data['status'] === 'completed') {
                if (count($checklist) !== count(FlatHandover::CHECKLIST)) {
                    throw ValidationException::withMessages(['checklist' => 'Complete the checklist before handing over.']);
                }
                if (empty($data['completed_at'])) {
                    throw ValidationException::withMessages(['completed_at' => 'Enter the handover date.']);
                }
            }
            $sale->handover->forceFill([
                'status' => $data['status'], 'scheduled_at' => $data['scheduled_at'] ?? null, 'completed_at' => $data['status'] === 'completed' ? $data['completed_at'] : null,
                'checklist_json' => $checklist, 'notes' => $data['notes'] ?? null, 'handled_by' => $user->id,
            ])->save();
            if ($data['status'] !== 'pending') {
                $sale->forceFill(['status' => $data['status'] === 'completed' ? 'handed_over' : 'handover_ready'])->save();
            }
        });
    }

    // ----------------------------------------------------------- cancellations

    /** Money already received for a booking or sale, net of refunds paid or in progress. */
    public static function refundable(?FlatBooking $booking, ?FlatSale $sale): string
    {
        $payments = CustomerPayment::query()->where('status', 'posted')->where(function ($q) use ($booking, $sale) {
            $booking && $q->orWhere('booking_id', $booking->id);
            $sale && $q->orWhere('sale_id', $sale->id);
            $sale?->booking_id && $q->orWhere('booking_id', $sale->booking_id);
        })->sum('amount');
        $refunds = CustomerRefund::query()->whereIn('status', ['pending', 'approved', 'paid'])->where(function ($q) use ($booking, $sale) {
            $booking && $q->orWhere('booking_id', $booking->id);
            $sale && $q->orWhere('sale_id', $sale->id);
            $sale?->booking_id && $q->orWhere('booking_id', $sale->booking_id);
        })->sum('amount');

        return Money::amount(Money::sub((string) $payments, (string) $refunds));
    }

    public function requestCancellation(?FlatBooking $booking, ?FlatSale $sale, string $reason, string $refundExpected, User $user): SalesCancellation
    {
        return DB::transaction(function () use ($booking, $sale, $reason, $refundExpected, $user) {
            if ($booking) {
                $booking = FlatBooking::query()->lockForUpdate()->findOrFail($booking->id);
                Approval::ensureStatus($booking->status, 'confirmed', 'Only confirmed bookings can be cancelled this way.');
            } else {
                $sale = FlatSale::query()->lockForUpdate()->findOrFail($sale->id);
                Approval::ensureStatus($sale->status, ['approved', 'registration_pending'], 'Registered or handed-over sales cannot be cancelled here.');
            }
            $this->ensureNoPendingCancellation($booking, $sale);
            $max = self::refundable($booking, $sale);
            if (Money::cmp($refundExpected, '0') < 0 || Money::cmp($refundExpected, $max) > 0) {
                throw ValidationException::withMessages(['refund_expected' => 'Refund can be between 0 and '.$max.'.']);
            }
            $booking?->forceFill(['status' => 'cancel_requested'])->save();

            return SalesCancellation::query()->create([
                'cancellation_no' => DocumentNumber::next('CN'), 'booking_id' => $booking?->id, 'sale_id' => $sale?->id,
                'reason' => $reason, 'refund_expected' => Money::amount($refundExpected), 'status' => 'requested',
                'requested_by' => $user->id, 'requested_at' => now(),
            ]);
        });
    }

    /** Releases the flat; any refund becomes a separate request that needs its own approval and payment. */
    public function approveCancellation(SalesCancellation $cancellation, User $user): ?CustomerRefund
    {
        return DB::transaction(function () use ($cancellation, $user) {
            $cancellation = SalesCancellation::query()->lockForUpdate()->findOrFail($cancellation->id);
            Approval::ensureStatus($cancellation->status, 'requested', 'This cancellation was already decided.');
            Approval::ensureNotSelf($user, $cancellation->requested_by, 'a cancellation');
            $doc = $cancellation->booking_id ? FlatBooking::query()->lockForUpdate()->findOrFail($cancellation->booking_id) : FlatSale::query()->lockForUpdate()->findOrFail($cancellation->sale_id);
            $flat = Flat::query()->lockForUpdate()->findOrFail($doc->flat_id);
            $date = now(config('app.display_timezone'));

            $this->claims->release($flat, $doc);
            $this->flats->setSalesStatus($flat, 'available', 'Cancelled '.($doc->booking_no ?? $doc->sale_no), $user);
            $doc->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'status_reason' => $cancellation->reason])->save();
            $cancellation->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();

            if ($doc instanceof FlatSale) {
                InstallmentPlan::query()->where('sale_id', $doc->id)->whereIn('status', ['draft', 'pending', 'active'])->update(['status' => 'cancelled']);
                $this->statement->post($doc->customer, 'sale_cancelled', $doc, $date, 'Sale '.$doc->sale_no.' cancelled', '0', $doc->net_contract_value, $doc->id, $doc->booking_id);
                $this->events->record('sales', $doc, 'sale.cancelled', Money::neg($doc->net_contract_value), $date,
                    ['sale_no' => $doc->sale_no, 'reason' => $cancellation->reason], project: $doc->project, flat: $flat, customer: $doc->customer, user: $user);
            } else {
                $this->events->record('sales', $doc, 'booking.cancelled', null, $date,
                    ['booking_no' => $doc->booking_no, 'reason' => $cancellation->reason], project: $doc->project, flat: $flat, customer: $doc->customer, user: $user);
            }

            if (! Money::isPositive($cancellation->refund_expected)) {
                return null;
            }

            return CustomerRefund::query()->create([
                'refund_no' => DocumentNumber::next('RF'), 'customer_id' => $doc->customer_id, 'project_id' => $doc->project_id,
                'booking_id' => $doc instanceof FlatBooking ? $doc->id : $doc->booking_id, 'sale_id' => $doc instanceof FlatSale ? $doc->id : null,
                'cancellation_id' => $cancellation->id, 'amount' => $cancellation->refund_expected, 'reason' => 'Cancellation '.$cancellation->cancellation_no,
                'status' => 'pending', 'requested_by' => $cancellation->requested_by, 'idempotency_key' => (string) Str::uuid(),
            ]);
        });
    }

    public function rejectCancellation(SalesCancellation $cancellation, string $reason, User $user): void
    {
        DB::transaction(function () use ($cancellation, $reason, $user) {
            $cancellation = SalesCancellation::query()->lockForUpdate()->findOrFail($cancellation->id);
            Approval::ensureStatus($cancellation->status, 'requested', 'This cancellation was already decided.');
            $cancellation->forceFill(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()])->save();
            if ($cancellation->booking_id) {
                FlatBooking::query()->whereKey($cancellation->booking_id)->where('status', 'cancel_requested')->update(['status' => 'confirmed']);
            }
        });
    }

    // ------------------------------------------------------------------ guards

    private function ensureBookable(Flat $flat, FlatBooking $booking): void
    {
        $holder = $this->claims->holder($flat);
        if (($holder && ! $holder->is($booking)) || ! in_array($flat->status, ['available'], true)) {
            throw new ConflictHttpException("Unit {$flat->flat_no} is not available.");
        }
    }

    private function checkAmounts(string $price, string $bookingAmount): void
    {
        if (! Money::isPositive($price)) {
            throw ValidationException::withMessages(['agreed_price' => 'Enter the agreed price.']);
        }
        if (Money::cmp($bookingAmount, '0') < 0 || Money::cmp($bookingAmount, $price) > 0) {
            throw ValidationException::withMessages(['booking_amount_expected' => 'Booking money must be between 0 and the agreed price.']);
        }
    }

    private function ensureNoPendingCancellation(?FlatBooking $booking, ?FlatSale $sale): void
    {
        $pending = SalesCancellation::query()->where('status', 'requested')->where(function ($q) use ($booking, $sale) {
            $booking && $q->orWhere('booking_id', $booking->id);
            $sale && $q->orWhere('sale_id', $sale->id);
        })->exists();
        if ($pending) {
            throw new ConflictHttpException('A cancellation request is waiting for approval.');
        }
    }
}
