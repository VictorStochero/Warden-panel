<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name . ' · Errors'" :showRanges="false" />

    <flux:callout variant="secondary">
        Failed requests (HTTP ≥ 500). Grouped exceptions live under
        <a class="text-brand-400" href="{{ route('project.issues', $project->slug) }}" wire:navigate>Issues</a>;
        alerts under <a class="text-brand-400" href="{{ route('project.incidents', $project->slug) }}" wire:navigate>Incidents</a>.
    </flux:callout>

    @if ($releases->isNotEmpty())
        <div class="flex flex-wrap gap-1">
            <button type="button" wire:click="$set('release', '')"
                @class(['rounded-md px-2.5 py-1 text-xs font-mono', 'bg-brand-600 text-white' => $release === '', 'bg-ink-850 text-slate-400' => $release !== ''])>All</button>
            @foreach ($releases as $r)
                <button type="button" wire:click="$set('release', '{{ $r }}')"
                    @class(['rounded-md px-2.5 py-1 text-xs font-mono', 'bg-brand-600 text-white' => $release === $r, 'bg-ink-850 text-slate-400' => $release !== $r])>{{ \Illuminate\Support\Str::limit($r, 12) }}</button>
            @endforeach
        </div>
    @endif

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Time</flux:table.column>
                <flux:table.column>Request</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Release</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($errors as $e)
                    <flux:table.row wire:key="err-{{ $e->id }}">
                        <flux:table.cell class="font-mono text-xs text-slate-400">{{ $e->occurred_at }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ is_array($e->payload) ? (($e->payload['method'] ?? '').' '.($e->payload['path'] ?? '')) : '' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs text-rose-400">{{ is_array($e->payload) ? ($e->payload['status'] ?? '') : '' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs text-slate-400">{{ $e->release ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No failed requests in the recent window.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
