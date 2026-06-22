<div class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">Alert settings</flux:heading>

    @if (session('settings_saved'))
        <flux:callout variant="success">Saved.</flux:callout>
    @endif
    @if (session('warden_error'))
        <flux:callout variant="danger">{{ session('warden_error') }}</flux:callout>
    @endif

    <div class="rounded-xl bg-ink-850 p-6">
        <flux:heading size="lg" class="mb-4">Email alerts</flux:heading>
        <form wire:submit="save" class="grid max-w-xl gap-4">
            <flux:checkbox wire:model="emailEnabled" label="Enable email alerts" />
            <flux:textarea wire:model="recipients" label="Recipients" description="One email per line or comma-separated." rows="3" />
            <flux:select wire:model="minSeverity" label="Minimum severity">
                @foreach ($severities as $s)<flux:select.option value="{{ $s }}">{{ ucfirst($s) }}</flux:select.option>@endforeach
            </flux:select>
            <flux:input type="number" wire:model="cooldown" label="Cooldown (seconds)" min="0" />
            <div><flux:button type="submit" variant="primary">Save</flux:button></div>
        </form>
    </div>

    <div class="rounded-xl bg-ink-850 p-6 space-y-4">
        <flux:heading size="lg">Alert rules</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Condition</flux:table.column>
                <flux:table.column>Window</flux:table.column>
                <flux:table.column>Severity</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($rules as $rule)
                    <flux:table.row wire:key="rule-{{ $rule->id }}">
                        <flux:table.cell>{{ $rule->name }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $rule->metric }} {{ $rule->op }} {{ $rule->threshold }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $rule->window }}</flux:table.cell>
                        <flux:table.cell>{{ $rule->severity }}</flux:table.cell>
                        <flux:table.cell><flux:button size="xs" variant="danger" wire:click="deleteRule({{ $rule->id }})" wire:confirm="Delete this rule?">Delete</flux:button></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell class="text-slate-400">No custom rules.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <form wire:submit="addRule" class="grid gap-3 sm:grid-cols-3">
            <flux:input wire:model="ruleName" label="Name" />
            <flux:select wire:model="ruleMetric" label="Metric">
                @foreach ($metrics as $m)<flux:select.option value="{{ $m }}">{{ $m }}</flux:select.option>@endforeach
            </flux:select>
            <flux:select wire:model="ruleOp" label="Op">
                @foreach ($ops as $o)<flux:select.option value="{{ $o }}">{{ $o }}</flux:select.option>@endforeach
            </flux:select>
            <flux:input type="number" step="any" wire:model="ruleThreshold" label="Threshold" />
            <flux:select wire:model="ruleWindow" label="Window">
                @foreach ($windows as $w)<flux:select.option value="{{ $w }}">{{ $w }}</flux:select.option>@endforeach
            </flux:select>
            <flux:select wire:model="ruleSeverity" label="Severity">
                @foreach ($severities as $s)<flux:select.option value="{{ $s }}">{{ ucfirst($s) }}</flux:select.option>@endforeach
            </flux:select>
            <div class="sm:col-span-3"><flux:button type="submit">Add rule</flux:button></div>
        </form>
    </div>
</div>
