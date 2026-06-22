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
</div>
