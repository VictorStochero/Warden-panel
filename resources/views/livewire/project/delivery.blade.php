<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name . ' · Delivery'" :showRanges="false" />

    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @php($cards = [
            ['Batches', number_format($delivery['batches'])],
            ['Events', number_format($delivery['events'])],
            ['Cadence', $delivery['cadence'] !== null ? $delivery['cadence'].'s' : '—'],
            ['Last received', $delivery['last'] ?? '—'],
        ])
        @foreach ($cards as [$label, $value])
            <div class="rounded-xl bg-ink-850 p-4">
                <div class="text-slate-400 text-sm">{{ $label }}</div>
                <div class="font-mono text-lg text-white">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <div class="mb-2 text-[11px] uppercase tracking-wider text-slate-500">Batches / minute (last {{ $delivery['window'] }}m)</div>
        <x-panel.bars :values="$delivery['series']" color="#5B97FF" :height="56" />
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:heading size="lg" class="mb-3">Recent batches (last {{ $delivery['window'] }}m)</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Received</flux:table.column>
                <flux:table.column>Batches</flux:table.column>
                <flux:table.column>Events</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($delivery['recent'] as $r)
                    <flux:table.row wire:key="batch-{{ $loop->index }}">
                        <flux:table.cell class="font-mono text-xs text-slate-400">{{ $r->received_at }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($r->batches) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($r->events) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No batches received recently.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
