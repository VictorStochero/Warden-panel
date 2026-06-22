<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl" class="font-wordmark">{{ $project->name }} · Manage</flux:heading>
        <flux:button size="sm" :href="route('admin.projects')" wire:navigate>← Projects</flux:button>
    </div>

    @if (session('admin_project_saved'))
        <flux:callout variant="success">Saved.</flux:callout>
    @endif

    <div class="rounded-xl bg-ink-850 p-6">
        <flux:heading size="lg" class="mb-4">Settings</flux:heading>
        <form wire:submit="save" class="grid max-w-xl gap-4">
            <flux:input wire:model="name" label="Name" />
            <flux:input wire:model="client" label="Client" />
            <flux:input wire:model="contact" label="Contact" />
            <flux:input wire:model="group" label="Group" description="Resolved/created by name; empty clears." />
            <flux:input wire:model="tags" label="Tags" description="Comma-separated; empty clears." />
            <div><flux:button type="submit" variant="primary">Save</flux:button></div>
        </form>
    </div>

    <div class="rounded-xl border border-rose-500/40 bg-ink-850 p-6 space-y-4">
        <flux:heading size="lg" class="text-rose-400">Danger zone</flux:heading>

        <div class="flex flex-wrap items-end gap-3">
            <flux:button wire:click="resetMetrics" wire:confirm="Delete all metrics for this project? Issues/incidents go too.">Reset metrics</flux:button>

            <div class="flex items-end gap-2">
                <flux:select wire:model="purgeTypeChoice" class="max-w-40">
                    @foreach ($purgeTypes as $t)<flux:select.option value="{{ $t }}">{{ ucfirst($t) }}</flux:select.option>@endforeach
                </flux:select>
                <flux:button wire:click="purge" wire:confirm="Purge all stored events of this type?">Purge type</flux:button>
            </div>
        </div>

        <flux:separator />

        <div class="space-y-2">
            <flux:subheading>Delete this project and all its data. Type <span class="font-mono">{{ $project->slug }}</span> to confirm.</flux:subheading>
            <div class="flex items-end gap-2">
                <flux:input wire:model="confirmSlug" class="max-w-xs" />
                <flux:button variant="danger" wire:click="deleteProject">Delete project</flux:button>
            </div>
            <flux:error name="confirmSlug" />
        </div>
    </div>
</div>
