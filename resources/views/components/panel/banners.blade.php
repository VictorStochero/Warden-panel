@props(['project' => null])
@php($versionNotice = app(\VictorStochero\Warden\Updates\VersionCheck::class)->notice())
@if ($versionNotice)
    <div x-data="{ show: localStorage.getItem('wdn_dismissed_version') !== @js($versionNotice['latest']) }" x-show="show" x-cloak>
        <flux:callout variant="primary" class="mb-2">
            <span>{{ __('panel.shell.version_available', ['latest' => $versionNotice['latest'], 'current' => $versionNotice['current']]) }}</span>
            <a href="https://packagist.org/packages/victorstochero/warden" target="_blank" rel="noopener" class="ml-2 underline">{{ __('panel.shell.release_notes') }}</a>
            <button type="button" class="ml-2 text-xs text-slate-400 hover:text-slate-200"
                @click="localStorage.setItem('wdn_dismissed_version', @js($versionNotice['latest'])); show = false">{{ __('panel.shell.dismiss') }}</button>
        </flux:callout>
    </div>
@endif
@cannot('panel.manage')
    <flux:callout variant="warning" class="mb-2">{{ __('panel.shell.read_only') }}</flux:callout>
@endcannot
@if ($project && in_array($project->capture_profile, ['lean', 'custom'], true))
    <flux:callout variant="secondary" class="mb-2">{{ __('panel.shell.capture_reduced', ['profile' => $project->capture_profile]) }}</flux:callout>
@endif
