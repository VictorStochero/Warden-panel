<div class="space-y-6">
    <flux:heading size="xl">Projects</flux:heading>

    <form wire:submit="createProject" class="flex items-end gap-3">
        <flux:input wire:model="name" label="New project name" class="max-w-sm" />
        <flux:button type="submit" variant="primary">Create + mint credentials</flux:button>
    </form>

    @if ($snippet)
        <flux:callout variant="warning">
            <flux:heading>Copy this now — the secret is shown only once.</flux:heading>
            <pre class="font-mono text-sm whitespace-pre-wrap">{{ $snippet }}</pre>
        </flux:callout>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Slug</flux:table.column>
            <flux:table.column>Active</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($projects as $project)
                <flux:table.row wire:key="proj-{{ $project->id }}">
                    <flux:table.cell>{{ $project->name }}</flux:table.cell>
                    <flux:table.cell class="font-mono">{{ $project->slug }}</flux:table.cell>
                    <flux:table.cell>{{ $project->active ? 'yes' : 'no' }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
