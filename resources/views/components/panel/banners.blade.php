@props(['project' => null])
@php($versionNotice = app(\VictorStochero\Warden\Updates\VersionCheck::class)->notice())
@if ($versionNotice)
    <div x-data="{ show: localStorage.getItem('wdn_dismissed_version') !== @js($versionNotice['latest']) }" x-show="show" x-cloak>
        <flux:callout variant="primary" class="mb-2">
            <span>Warden <span class="font-mono">{{ $versionNotice['latest'] }}</span> is available — you have <span class="font-mono">{{ $versionNotice['current'] }}</span>.</span>
            <a href="https://packagist.org/packages/victorstochero/warden" target="_blank" rel="noopener" class="ml-2 underline">Release notes</a>
            <button type="button" class="ml-2 text-xs text-slate-400 hover:text-slate-200"
                @click="localStorage.setItem('wdn_dismissed_version', @js($versionNotice['latest'])); show = false">Dismiss</button>
        </flux:callout>
    </div>
@endif
@cannot('panel.manage')
    <flux:callout variant="warning" class="mb-2">You have read-only access — management actions are hidden.</flux:callout>
@endcannot
@if ($project && in_array($project->capture_profile, ['lean', 'custom'], true))
    <flux:callout variant="secondary" class="mb-2">Capture is reduced ({{ $project->capture_profile }}) — some metrics may be sparse.</flux:callout>
@endif
