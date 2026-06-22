<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-ink-950 text-slate-200">
        <flux:sidebar sticky stashable class="border-r border-ink-800 bg-ink-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo class="size-8" href="#"></x-app-logo>
            </a>

            <style>[x-cloak]{display:none!important}</style>
            <div class="mb-3"><livewire:search /></div>

            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('panel.groups.warden')" class="grid">
                    <flux:navlist.item icon="server" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>{{ __('panel.nav.fleet') }}</flux:navlist.item>
                    @can('panel.manage')
                        <flux:navlist.item icon="folder" :href="route('admin.projects')" :current="request()->routeIs('admin.projects') || request()->routeIs('admin.project')" wire:navigate>{{ __('panel.nav.projects') }}</flux:navlist.item>
                        <flux:navlist.item icon="clipboard-document-list" :href="route('admin.audit')" :current="request()->routeIs('admin.audit')" wire:navigate>{{ __('panel.nav.audit') }}</flux:navlist.item>
                        <flux:navlist.item icon="wrench-screwdriver" :href="route('admin.maintenance')" :current="request()->routeIs('admin.maintenance')" wire:navigate>{{ __('panel.nav.maintenance') }}</flux:navlist.item>
                        <flux:navlist.item icon="bell-alert" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate>{{ __('panel.nav.settings') }}</flux:navlist.item>
                        <flux:navlist.item icon="key" :href="route('admin.api-tokens')" :current="request()->routeIs('admin.api-tokens')" wire:navigate>{{ __('panel.nav.api_tokens') }}</flux:navlist.item>
                    @endcan
                </flux:navlist.group>
                @php($slug = request()->route('slug'))
                @if ($slug)
                    <flux:navlist.group :heading="__('panel.groups.overview')" class="grid">
                        <flux:navlist.item :href="route('project.show', $slug)" :current="request()->routeIs('project.show')" wire:navigate>{{ __('panel.nav.overview') }}</flux:navlist.item>
                    </flux:navlist.group>
                    <flux:navlist.group :heading="__('panel.groups.performance')" class="grid">
                        <flux:navlist.item :href="route('project.requests', $slug)" :current="request()->routeIs('project.requests')" wire:navigate>{{ __('panel.nav.requests') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('project.database', $slug)" :current="request()->routeIs('project.database')" wire:navigate>{{ __('panel.nav.database') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('project.jobs', $slug)" :current="request()->routeIs('project.jobs')" wire:navigate>{{ __('panel.nav.jobs') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('project.http', $slug)" :current="request()->routeIs('project.http')" wire:navigate>{{ __('panel.nav.http') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('project.schedule', $slug)" :current="request()->routeIs('project.schedule')" wire:navigate>{{ __('panel.nav.schedule') }}</flux:navlist.item>
                    </flux:navlist.group>
                    <flux:navlist.group :heading="__('panel.groups.reliability')" class="grid">
                        <flux:navlist.item :href="route('project.errors', $slug)" :current="request()->routeIs('project.errors')" wire:navigate>{{ __('panel.nav.errors') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('project.issues', $slug)" :current="request()->routeIs('project.issues') || request()->routeIs('project.issue')" wire:navigate>{{ __('panel.nav.issues') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('project.incidents', $slug)" :current="request()->routeIs('project.incidents') || request()->routeIs('project.incident')" wire:navigate>{{ __('panel.nav.incidents') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('project.uptime', $slug)" :current="request()->routeIs('project.uptime')" wire:navigate>{{ __('panel.nav.uptime') }}</flux:navlist.item>
                    </flux:navlist.group>
                    <flux:navlist.group :heading="__('panel.groups.diagnostics')" class="grid">
                        <flux:navlist.item :href="route('project.traces', $slug)" :current="request()->routeIs('project.traces') || request()->routeIs('project.trace')" wire:navigate>{{ __('panel.nav.traces') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('project.logs', $slug)" :current="request()->routeIs('project.logs')" wire:navigate>{{ __('panel.nav.logs') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('project.events', $slug)" :current="request()->routeIs('project.events') || request()->routeIs('project.event')" wire:navigate>{{ __('panel.nav.events') }}</flux:navlist.item>
                    </flux:navlist.group>
                    <flux:navlist.group :heading="__('panel.groups.system')" class="grid">
                        <flux:navlist.item :href="route('project.host', $slug)" :current="request()->routeIs('project.host')" wire:navigate>{{ __('panel.nav.host') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('project.mail', $slug)" :current="request()->routeIs('project.mail')" wire:navigate>{{ __('panel.nav.mail') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('project.security', $slug)" :current="request()->routeIs('project.security')" wire:navigate>{{ __('panel.nav.security') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('project.delivery', $slug)" :current="request()->routeIs('project.delivery')" wire:navigate>{{ __('panel.nav.delivery') }}</flux:navlist.item>
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
                        <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>{{ __('panel.nav.settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <div class="flex items-center gap-1 px-2 py-1.5">
                        @foreach (['en' => 'EN', 'pt' => 'PT', 'es' => 'ES'] as $code => $label)
                            <form method="POST" action="{{ route('locale.set') }}">
                                @csrf
                                <input type="hidden" name="locale" value="{{ $code }}" />
                                <button type="submit" @class(['rounded px-2 py-0.5 text-xs', 'bg-brand-600 text-white' => app()->getLocale() === $code, 'text-slate-400 hover:text-slate-200' => app()->getLocale() !== $code])>{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>

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
                        <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>{{ __('panel.nav.settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <div class="flex items-center gap-1 px-2 py-1.5">
                        @foreach (['en' => 'EN', 'pt' => 'PT', 'es' => 'ES'] as $code => $label)
                            <form method="POST" action="{{ route('locale.set') }}">
                                @csrf
                                <input type="hidden" name="locale" value="{{ $code }}" />
                                <button type="submit" @class(['rounded px-2 py-0.5 text-xs', 'bg-brand-600 text-white' => app()->getLocale() === $code, 'text-slate-400 hover:text-slate-200' => app()->getLocale() !== $code])>{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>

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
