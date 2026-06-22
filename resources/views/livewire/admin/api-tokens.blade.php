<div class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">API tokens</flux:heading>
    <flux:subheading>Read-only tokens for the JSON fleet API. The plaintext is shown once.</flux:subheading>

    <flux:callout variant="secondary">
        Use a token with <span class="font-mono">Authorization: Bearer wdn_…</span> against
        <span class="font-mono">/api/v1/overview</span>, <span class="font-mono">/api/v1/projects/{slug}</span>,
        <span class="font-mono">/api/v1/projects/{slug}/events/{type}</span>.
    </flux:callout>

    <form wire:submit="createToken" class="flex items-end gap-3">
        <flux:input wire:model="name" label="Token name" class="max-w-sm" />
        <flux:button type="submit" variant="primary">Create token</flux:button>
    </form>

    @if (session('warden_new_token'))
        <flux:callout variant="warning">
            <flux:heading>Copy this now — the token is shown only once.</flux:heading>
            <pre class="font-mono text-sm whitespace-pre-wrap">{{ session('warden_new_token') }}</pre>
        </flux:callout>
    @endif

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Prefix</flux:table.column>
                <flux:table.column>Last used</flux:table.column>
                <flux:table.column>Created</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($tokens as $token)
                    <flux:table.row wire:key="token-{{ $token->id }}">
                        <flux:table.cell>{{ $token->name }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $token->prefix }}…</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs text-slate-400">{{ $token->last_used_at ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs text-slate-400">{{ $token->created_at }}</flux:table.cell>
                        <flux:table.cell><flux:button size="xs" variant="danger" wire:click="revoke({{ $token->id }})" wire:confirm="Revoke this token?">Revoke</flux:button></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No API tokens.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
