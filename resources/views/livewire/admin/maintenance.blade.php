<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">Maintenance</flux:heading>

    @if (session('warden_status'))
        <flux:callout variant="success">{{ session('warden_status') }}</flux:callout>
    @endif
    @if (session('warden_error'))
        <flux:callout variant="danger">{{ session('warden_error') }}</flux:callout>
    @endif

    @php($statusColor = ['ok' => 'lime', 'failed' => 'rose', 'running' => 'amber', 'queued' => 'zinc'])

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($commands as $command)
            @php($run = $latest[$command] ?? null)
            <div class="rounded-xl bg-ink-850 p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg" class="font-mono">warden:{{ $command }}</flux:heading>
                    @if ($command === 'prune')
                        <flux:button size="sm" variant="danger" wire:click="run('{{ $command }}')" wire:confirm="Run warden:prune? This deletes data past the retention window.">Run</flux:button>
                    @else
                        <flux:button size="sm" wire:click="run('{{ $command }}')">Run</flux:button>
                    @endif
                </div>
                <p class="text-sm text-slate-400">{{ $descriptions[$command] ?? '' }}</p>
                @if ($run)
                    <div class="flex items-center gap-2 text-xs">
                        <flux:badge :color="$statusColor[$run->status] ?? 'zinc'" size="sm">{{ $run->status }}</flux:badge>
                        <span class="font-mono text-slate-500">{{ $run->finished_at ?? $run->queued_at }}@if ($run->duration_ms) · {{ $run->duration_ms }}ms @endif</span>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:heading size="lg" class="mb-3">Recent runs</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Command</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Finished</flux:table.column>
                <flux:table.column>Message</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($recent as $r)
                    <flux:table.row wire:key="run-{{ $r->id }}">
                        <flux:table.cell class="font-mono text-xs">warden:{{ $r->command }}</flux:table.cell>
                        <flux:table.cell><flux:badge :color="$statusColor[$r->status] ?? 'zinc'" size="sm">{{ $r->status }}</flux:badge></flux:table.cell>
                        <flux:table.cell class="font-mono text-xs text-slate-400">{{ $r->finished_at ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs truncate max-w-md">{{ $r->message }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No runs yet.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
