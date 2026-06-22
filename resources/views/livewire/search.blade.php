<div
    x-data
    @keydown.window.cmd.k.prevent="$wire.set('open', true)"
    @keydown.window.ctrl.k.prevent="$wire.set('open', true)"
    @keydown.window.escape="$wire.set('open', false)"
>
    <button type="button" wire:click="$set('open', true)"
        class="flex w-full items-center gap-2 rounded-lg bg-ink-850 px-3 py-2 text-sm text-slate-400 transition hover:text-slate-200">
        <flux:icon name="magnifying-glass" class="size-4" />
        <span>Search</span>
        <span class="ml-auto font-mono text-xs text-slate-500">⌘K</span>
    </button>

    <div x-show="$wire.open" x-cloak
        class="fixed inset-0 z-50 flex items-start justify-center bg-black/60 p-4 pt-24"
        @click.self="$wire.set('open', false)">
        <div class="w-full max-w-xl overflow-hidden rounded-xl bg-ink-900 ring-1 ring-ink-700 shadow-2xl"
            x-init="$nextTick(() => $refs.searchInput && $refs.searchInput.focus())">
            <div class="border-b border-ink-700 p-3">
                <input x-ref="searchInput" wire:model.live.debounce.300ms="q" type="text"
                    placeholder="Search projects, routes, issues, traces…"
                    class="w-full bg-transparent text-sm text-slate-100 placeholder-slate-500 focus:outline-none" />
            </div>

            <div class="max-h-96 overflow-y-auto p-2 text-sm">
                @php($groups = [
                    ['Projects', $results['projects']],
                    ['Routes', $results['routes']],
                    ['Issues', $results['issues']],
                    ['Traces', $results['traces']],
                ])
                @php($any = collect($results)->flatten(1)->isNotEmpty())

                @forelse ($groups as [$heading, $items])
                    @if (count($items) > 0)
                        <div class="px-2 py-1 text-[11px] uppercase tracking-wider text-slate-500">{{ $heading }}</div>
                        @foreach ($items as $item)
                            @php($href = match ($heading) {
                                'Projects' => route('project.show', $item['slug']),
                                'Routes' => $slug ? route('project.requests', $slug) : '#',
                                'Issues' => $slug ? route('project.issue', ['slug' => $slug, 'issueId' => $item['id']]) : '#',
                                'Traces' => $slug ? route('project.trace', ['slug' => $slug, 'traceId' => $item['trace_id']]) : '#',
                                default => '#',
                            })
                            <a href="{{ $href }}" wire:navigate @click="$wire.set('open', false)"
                                class="block rounded-md px-2 py-1.5 hover:bg-ink-850">
                                <div class="text-slate-200">{{ $item['name'] ?? $item['route'] ?? $item['class'] ?? $item['label'] ?? '' }}</div>
                                <div class="font-mono text-xs text-slate-500">{{ $item['slug'] ?? $item['message'] ?? $item['trace_id'] ?? '' }}</div>
                            </a>
                        @endforeach
                    @endif
                @empty
                @endforelse

                @unless ($any)
                    <div class="px-2 py-6 text-center text-slate-500">
                        {{ strlen(trim($q)) >= 2 ? 'No matches.' : 'Type to search…' }}
                    </div>
                @endunless
            </div>
        </div>
    </div>
</div>
