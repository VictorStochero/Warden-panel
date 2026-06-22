<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Logs</flux:heading>
        <flux:select wire:model.live="range" class="max-w-32">
            @foreach ($ranges as $r)<flux:select.option value="{{ $r }}">{{ $r }}</flux:select.option>@endforeach
        </flux:select>
    </div>

    <div class="rounded-xl bg-ink-850 p-4 space-y-1">
        @php($levelColor = ['error' => 'text-rose-400', 'critical' => 'text-rose-400', 'warning' => 'text-amber-400', 'info' => 'text-brand-400', 'debug' => 'text-slate-400'])
        @forelse ($logs as $log)
            @php($level = is_array($log->payload) ? ($log->payload['level'] ?? 'info') : 'info')
            <div class="flex gap-3 text-xs font-mono border-b border-ink-800 py-1">
                <span class="w-40 shrink-0 text-slate-500">{{ $log->occurred_at }}</span>
                <span class="w-16 shrink-0 {{ $levelColor[$level] ?? 'text-slate-400' }}">{{ strtoupper($level) }}</span>
                <span class="text-slate-300 truncate">{{ is_array($log->payload) ? ($log->payload['message'] ?? '') : '' }}</span>
            </div>
        @empty
            <div class="text-slate-400 text-sm">No logs in this window.</div>
        @endforelse
    </div>
</div>
