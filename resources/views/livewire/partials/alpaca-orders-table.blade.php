<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-950/50 text-xs uppercase tracking-wider text-slate-500">
            <tr>
                <th class="px-4 py-3 text-left">Symbol</th>
                <th class="px-4 py-3 text-left">Side</th>
                <th class="px-4 py-3 text-right">Qty</th>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-right">Submitted</th>
                @if($showCancel)
                    <th class="px-4 py-3 text-right">Action</th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-800">
            @forelse($orders as $order)
                @php
                    $side = strtolower((string)($order['side'] ?? ''));
                    $status = strtolower((string)($order['status'] ?? ''));
                @endphp
                <tr class="hover:bg-slate-800/30">
                    <td class="px-4 py-3 font-bold text-white">{{ $order['symbol'] ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded text-xs font-bold {{ $side === 'buy' ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/25' : 'bg-rose-500/10 text-rose-300 border border-rose-500/25' }}">
                            {{ strtoupper($side ?: '—') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-mono text-slate-300">{{ $order['qty'] ?? $order['notional'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-300">{{ strtoupper((string)($order['type'] ?? '—')) }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                            {{ strtoupper($status ?: '—') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-mono text-xs text-slate-400">
                        {{ isset($order['submitted_at']) ? \Carbon\Carbon::parse($order['submitted_at'])->setTimezone('America/New_York')->format('m/d H:i:s') : '—' }}
                    </td>
                    @if($showCancel)
                        <td class="px-4 py-3 text-right">
                            <button wire:click="cancelOrder('{{ $order['id'] ?? '' }}')"
                                class="px-3 py-1.5 rounded bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/25 text-xs font-semibold">
                                Cancel
                            </button>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showCancel ? 7 : 6 }}" class="px-4 py-10 text-center text-slate-500">No orders.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
