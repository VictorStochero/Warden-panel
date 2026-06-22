<div wire:poll.{{ config('panel.poll_seconds') }}s class="space-y-6">
    <x-panel.banners :project="$project" />
    <x-panel.page-header :title="$project->name . ' · ' . __('panel.nav.mail')" :range="$range" :ranges="$ranges" />
    <x-panel.kpi-strip :project="$project" :kpis="$kpis" />

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl bg-ink-850 p-4">
            <flux:heading size="lg" class="mb-3">Mailers</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Mailer</flux:table.column>
                    <flux:table.column>Sent</flux:table.column>
                    <flux:table.column>{{ __('panel.common.avg') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($mailers as $m)
                        <flux:table.row wire:key="mailer-{{ $loop->index }}">
                            <flux:table.cell class="font-mono text-xs">{{ $m['key'] }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($m['count']) }}</flux:table.cell>
                            <flux:table.cell>{{ $m['avg'] }}ms</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell class="text-slate-400">No mail in this window.</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
        <div class="rounded-xl bg-ink-850 p-4">
            <flux:heading size="lg" class="mb-3">Notifications</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Notification</flux:table.column>
                    <flux:table.column>Sent</flux:table.column>
                    <flux:table.column>{{ __('panel.common.avg') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($notifications as $n)
                        <flux:table.row wire:key="notif-{{ $loop->index }}">
                            <flux:table.cell class="font-mono text-xs">{{ $n['key'] }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($n['count']) }}</flux:table.cell>
                            <flux:table.cell>{{ $n['avg'] }}ms</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell class="text-slate-400">No notifications in this window.</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
</div>
