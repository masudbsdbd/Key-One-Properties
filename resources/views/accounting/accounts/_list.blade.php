<div class="space-y-6">
    @foreach (App\Modules\Accounting\Support\ChartOfAccounts::CATEGORIES as $category => $label)
        @php $accounts = $groups[$category] ?? collect(); @endphp
        <x-card :title="$label" :padded="false">
            <x-slot:actions>
                <span class="text-sm font-semibold tabular-nums text-ink-900"><x-money :value="$accounts->where('depth', 0)->reduce(fn ($sum, $a) => App\Support\Money::add($sum, $a->balance), '0')" /></span>
            </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th class="w-28">Code</th><th>Account</th><th class="text-right">Balance</th><th class="w-28"></th></tr></thead>
                    <tbody>
                        @foreach ($accounts as $account)
                            <tr @class(['text-ink-400' => ! $account->is_active])>
                                <td class="tabular-nums text-ink-600">{{ $account->code }}</td>
                                <td>
                                    <div class="flex items-center gap-2" @if ($account->depth) style="padding-left: {{ min($account->depth, 4) * 20 }}px" @endif>
                                        @if ($account->is_postable && $canLedger && true)
                                            <a href="{{ route('accounting.ledger', ['account' => $account->public_id]) }}" @class(['ref-link', 'font-medium' => true])>{{ $account->name }}</a>
                                        @else
                                            <span @class(['font-semibold text-ink-900' => ! $account->is_postable, 'font-medium text-ink-900' => $account->is_postable])>{{ $account->name }}</span>
                                        @endif
                                        @unless ($account->is_active)<x-badge tone="neutral">Inactive</x-badge>@endunless
                                    </div>
                                    @if ($account->description)<p class="text-[13px] text-ink-500" @if ($account->depth) style="padding-left: {{ min($account->depth, 4) * 20 }}px" @endif>{{ $account->description }}</p>@endif
                                </td>
                                <td @class(['text-right tabular-nums', 'font-semibold text-ink-900' => ! $account->is_postable, 'text-danger-600' => App\Support\Money::cmp($account->balance, 0) < 0])><x-money :value="$account->balance" /></td>
                                <td class="text-right">
                                    @if ($canManage)
                                        <div class="flex justify-end gap-1">
                                            <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('accounting.accounts.edit', $account) }}" data-size="max-w-2xl" aria-label="Edit {{ $account->code }}"><x-icon name="edit" size="h-4 w-4" /></button>
                                            @unless ($account->is_system)
                                                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('accounting.accounts.destroy', $account) }}" data-confirm="Delete {{ $account->label() }}?" data-confirm-text="Delete" aria-label="Delete {{ $account->code }}"><x-icon name="trash" size="h-4 w-4" /></button>
                                            @endunless
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endforeach
</div>
