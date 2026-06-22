@props(['project' => null])
@cannot('panel.manage')
    <flux:callout variant="warning" class="mb-2">You have read-only access — management actions are hidden.</flux:callout>
@endcannot
@if ($project && in_array($project->capture_profile, ['lean', 'custom'], true))
    <flux:callout variant="secondary" class="mb-2">Capture is reduced ({{ $project->capture_profile }}) — some metrics may be sparse.</flux:callout>
@endif
