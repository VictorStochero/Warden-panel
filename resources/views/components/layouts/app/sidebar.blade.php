<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo class="size-8" href="#"></x-app-logo>
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group heading="Warden" class="grid">
                    <flux:navlist.item icon="server" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>Fleet</flux:navlist.item>
                    @can('panel.manage')
                        <flux:navlist.item icon="folder" :href="route('admin.projects')" :current="request()->routeIs('admin.projects') || request()->routeIs('admin.project')" wire:navigate>Projects</flux:navlist.item>
                        <flux:navlist.item icon="clipboard-document-list" :href="route('admin.audit')" :current="request()->routeIs('admin.audit')" wire:navigate>Audit</flux:navlist.item>
                        <flux:navlist.item icon="wrench-screwdriver" :href="route('admin.maintenance')" :current="request()->routeIs('admin.maintenance')" wire:navigate>Maintenance</flux:navlist.item>
                        <flux:navlist.item icon="bell-alert" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate>Settings</flux:navlist.item>
                    @endcan
                </flux:navlist.group>
                @php($slug = request()->route('slug'))
                @if ($slug)
                    <flux:navlist.group heading="Overview" class="grid">
                        <flux:navlist.item :href="route('project.show', $slug)" :current="request()->routeIs('project.show')" wire:navigate>Overview</flux:navlist.item>
                    </flux:navlist.group>
                    <flux:navlist.group heading="Performance" class="grid">
                        <flux:navlist.item :href="route('project.requests', $slug)" :current="request()->routeIs('project.requests')" wire:navigate>Requests</flux:navlist.item>
                        <flux:navlist.item :href="route('project.database', $slug)" :current="request()->routeIs('project.database')" wire:navigate>Database</flux:navlist.item>
                        <flux:navlist.item :href="route('project.jobs', $slug)" :current="request()->routeIs('project.jobs')" wire:navigate>Jobs</flux:navlist.item>
                        <flux:navlist.item :href="route('project.http', $slug)" :current="request()->routeIs('project.http')" wire:navigate>HTTP</flux:navlist.item>
                        <flux:navlist.item :href="route('project.schedule', $slug)" :current="request()->routeIs('project.schedule')" wire:navigate>Schedule</flux:navlist.item>
                    </flux:navlist.group>
                    <flux:navlist.group heading="Reliability" class="grid">
                        <flux:navlist.item :href="route('project.errors', $slug)" :current="request()->routeIs('project.errors')" wire:navigate>Errors</flux:navlist.item>
                        <flux:navlist.item :href="route('project.issues', $slug)" :current="request()->routeIs('project.issues') || request()->routeIs('project.issue')" wire:navigate>Issues</flux:navlist.item>
                        <flux:navlist.item :href="route('project.incidents', $slug)" :current="request()->routeIs('project.incidents') || request()->routeIs('project.incident')" wire:navigate>Incidents</flux:navlist.item>
                        <flux:navlist.item :href="route('project.uptime', $slug)" :current="request()->routeIs('project.uptime')" wire:navigate>Uptime</flux:navlist.item>
                    </flux:navlist.group>
                    <flux:navlist.group heading="Diagnostics" class="grid">
                        <flux:navlist.item :href="route('project.traces', $slug)" :current="request()->routeIs('project.traces') || request()->routeIs('project.trace')" wire:navigate>Traces</flux:navlist.item>
                        <flux:navlist.item :href="route('project.logs', $slug)" :current="request()->routeIs('project.logs')" wire:navigate>Logs</flux:navlist.item>
                        <flux:navlist.item :href="route('project.events', $slug)" :current="request()->routeIs('project.events') || request()->routeIs('project.event')" wire:navigate>Events</flux:navlist.item>
                    </flux:navlist.group>
                    <flux:navlist.group heading="System" class="grid">
                        <flux:navlist.item :href="route('project.host', $slug)" :current="request()->routeIs('project.host')" wire:navigate>Host</flux:navlist.item>
                        <flux:navlist.item :href="route('project.mail', $slug)" :current="request()->routeIs('project.mail')" wire:navigate>Mail</flux:navlist.item>
                        <flux:navlist.item :href="route('project.security', $slug)" :current="request()->routeIs('project.security')" wire:navigate>Security</flux:navlist.item>
                        <flux:navlist.item :href="route('project.delivery', $slug)" :current="request()->routeIs('project.delivery')" wire:navigate>Delivery</flux:navlist.item>
                    </flux:navlist.group>
                @endif
            </flux:navlist>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Settings</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Settings</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
