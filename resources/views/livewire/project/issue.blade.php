<div class="space-y-6">
    <a href="{{ route('project.issues', $project->slug) }}" class="text-brand-400 text-sm" wire:navigate>← Issues</a>

    <div class="rounded-xl bg-ink-850 p-4 space-y-2">
        <flux:heading size="lg" class="font-mono">{{ $issue->class }}</flux:heading>
        <p class="text-slate-300">{{ $issue->message }}</p>
        <div class="flex gap-4 text-sm font-mono text-slate-400">
            <span>status: <span class="text-brand-400">{{ $issue->status }}</span></span>
            <span>count: {{ number_format($issue->count) }}</span>
            <span>assignee: {{ $issue->assignee ?? '—' }}</span>
        </div>
    </div>

    @can('panel.manage')
        <div class="flex flex-wrap gap-2">
            <flux:button wire:click="resolve" size="sm" variant="primary">Resolve</flux:button>
            <flux:button wire:click="ignore" size="sm">Ignore</flux:button>
            <flux:button wire:click="reopen" size="sm">Reopen</flux:button>
            <flux:button wire:click="assignToMe" size="sm">Assign to me</flux:button>
            <flux:button wire:click="unassign" size="sm">Unassign</flux:button>
            <flux:button wire:click="snooze" size="sm">Snooze 1d</flux:button>
        </div>
    @endcan

    @if (is_array($issue->stack) && count($issue->stack))
        <div class="rounded-xl bg-ink-850 p-4">
            <flux:heading size="lg" class="mb-2">Stack</flux:heading>
            <pre class="font-mono text-xs text-slate-400 overflow-x-auto">{{ json_encode($issue->stack, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif

    <div class="rounded-xl bg-ink-850 p-4 space-y-3">
        <flux:heading size="lg">Comments</flux:heading>
        @forelse ($comments as $c)
            <div class="border-l-2 border-ink-700 pl-3">
                <div class="text-xs text-slate-500 font-mono">{{ $c->author }} · {{ $c->created_at }}</div>
                <div class="text-slate-300 text-sm">{{ $c->body }}</div>
            </div>
        @empty
            <div class="text-slate-400 text-sm">No comments yet.</div>
        @endforelse

        @can('panel.manage')
            <form wire:submit="addComment" class="flex gap-2 pt-2">
                <flux:input wire:model="newComment" placeholder="Add a comment…" class="flex-1" />
                <flux:button type="submit" variant="primary" size="sm">Comment</flux:button>
            </form>
        @endcan
    </div>
</div>
