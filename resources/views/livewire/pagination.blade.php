@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-2">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="px-3 py-1.5 text-xs text-slate-500 cursor-not-allowed">← Prev</span>
        @else
            <button wire:click="previousPage" class="px-3 py-1.5 text-xs text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">← Prev</button>
        @endif

        {{-- Numbers --}}
        <div class="flex items-center gap-1">
            @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $url => $page)
                @if ($page == $paginator->currentPage())
                    <span class="px-3 py-1.5 text-xs text-slate-100 bg-blue-600 rounded-lg font-semibold">{{ $page }}</span>
                @else
                    <button wire:click="gotoPage({{ $page }})" class="px-3 py-1.5 text-xs text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">{{ $page }}</button>
                @endif
            @endforeach
        </div>

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <button wire:click="nextPage" class="px-3 py-1.5 text-xs text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">Next →</button>
        @else
            <span class="px-3 py-1.5 text-xs text-slate-500 cursor-not-allowed">Next →</span>
        @endif
    </nav>
@endif
