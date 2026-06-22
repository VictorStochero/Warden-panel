<div class="space-y-6">
    <flux:heading size="xl" class="font-wordmark">Projects</flux:heading>

    <form wire:submit="createProject" class="flex items-end gap-3">
        <flux:input wire:model="name" label="New project name" class="max-w-sm" />
        <flux:button type="submit" variant="primary">Create + mint credentials</flux:button>
    </form>

    @if (session('warden_new_credentials'))
        <flux:callout variant="warning">
            <flux:heading>Copy this now — the secret is shown only once.</flux:heading>
            <pre class="font-mono text-sm whitespace-pre-wrap">{{ session('warden_new_credentials')['snippet'] }}</pre>
        </flux:callout>
    @endif

    <div class="rounded-xl bg-ink-850 p-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Slug</flux:table.column>
                <flux:table.column>Active</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($projects as $project)
                    <flux:table.row wire:key="proj-{{ $project->id }}">
                        <flux:table.cell>{{ $project->name }}</flux:table.cell>
                        <flux:table.cell class="font-mono">{{ $project->slug }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$project->active ? 'lime' : 'zinc'" size="sm">{{ $project->active ? 'active' : 'inactive' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-2">
                                <flux:button size="xs" wire:click="rotateToken('{{ $project->slug }}')">Rotate</flux:button>
                                <flux:button size="xs" wire:click="toggleActive('{{ $project->slug }}')">{{ $project->active ? 'Deactivate' : 'Activate' }}</flux:button>
                                <flux:button size="xs" variant="primary" :href="route('admin.project', $project->slug)" wire:navigate>Manage</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>
