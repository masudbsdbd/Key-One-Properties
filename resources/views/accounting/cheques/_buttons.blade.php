@if (in_array($cheque->status, App\Models\Cheque::OPEN, true))
    <div class="flex flex-wrap justify-end gap-1.5">
        @if ($cheque->status === 'in_hand')
            <x-button size="sm" variant="secondary" data-remote-modal="{{ route('accounting.cheques.form', [$cheque, 'deposit']) }}" data-size="max-w-md">Deposit</x-button>
        @endif
        <x-button size="sm" variant="secondary" data-remote-modal="{{ route('accounting.cheques.form', [$cheque, 'clear']) }}" data-size="max-w-md">Cleared</x-button>
        <x-button size="sm" variant="ghost" class="text-danger-600" data-remote-modal="{{ route('accounting.cheques.form', [$cheque, 'bounce']) }}" data-size="max-w-md">Bounced</x-button>
    </div>
@endif
