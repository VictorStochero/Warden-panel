<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.page-header :title="$project->name . ' · ' . __('panel.nav.issues')" :showRanges="false" />

    <div class="flex justify-end">
        <flux:select wire:model.live="status" class="max-w-40">
            <flux:select.option value="open">Open</flux:select.option>
            <flux:select.option value="resolved">Resolved</flux:select.option>
            <flux:select.option value="ignored">Ignored</flux:select.option>
            <flux:select.option value="">All</flux:select.option>
        </flux:select>
    </div>

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Issue</flux:table.column>
                <flux:table.column>{{ __('panel.common.status') }}</flux:table.column>
                <flux:table.column>{{ __('panel.common.count') }}</flux:table.column>
                <flux:table.column>Last seen</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($issues as $issue)
                    <flux:table.row wire:key="issue-{{ $issue->id }}">
                        <flux:table.cell>
                            <a class="text-brand-400 font-mono text-sm" href="{{ route('project.issue', ['slug' => $project->slug, 'issueId' => $issue->id]) }}" wire:navigate>
                                {{ $issue->class }}
                            </a>
                            <div class="text-slate-400 text-xs truncate max-w-md">{{ $issue->message }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $issue->status }}</flux:table.cell>
                        <flux:table.cell class="font-mono">{{ number_format($issue->count) }}</flux:table.cell>
                        <flux:table.cell class="text-slate-400 text-sm">{{ $issue->last_seen_at }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No issues for this filter.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
