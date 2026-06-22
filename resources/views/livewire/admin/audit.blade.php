<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">Audit log</flux:heading>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Time</flux:table.column>
                <flux:table.column>Actor</flux:table.column>
                <flux:table.column>Action</flux:table.column>
                <flux:table.column>Target</flux:table.column>
                <flux:table.column>Method</flux:table.column>
                <flux:table.column>IP</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($entries as $entry)
                    <flux:table.row wire:key="audit-{{ $entry->id }}">
                        <flux:table.cell class="font-mono text-xs text-slate-400">{{ $entry->created_at }}</flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $entry->actor }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $entry->action }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $entry->target }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $entry->method }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs text-slate-400">{{ $entry->ip }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No audit entries yet.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
