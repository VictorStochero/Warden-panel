<?php

namespace App\Livewire\Admin;

use App\Support\WritesAudit;
use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Config\CaptureProfiles;
use VictorStochero\Warden\Config\ProjectConfig;
use VictorStochero\Warden\Dashboard\CaptureStatus;
use VictorStochero\Warden\Models\Project as ProjectModel;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Support\Cast;

#[Layout('components.layouts.app')]
class Project extends Component
{
    use WritesAudit;

    public string $slug;
    public string $name = '';
    public string $client = '';
    public string $contact = '';
    public string $group = '';
    public string $tags = '';
    public string $purgeTypeChoice = 'cache';
    public string $confirmSlug = '';

    public string $captureProfile = 'full';

    /** @var array<string, bool> type => enabled */
    public array $typeGates = [];

    /** @return list<string> */
    public static function purgeTypes(): array
    {
        return ['query', 'exception', 'log', 'job', 'mail', 'notification', 'cache', 'command', 'schedule', 'http', 'request'];
    }

    /** Recorder types whose capture can be toggled in a custom profile. */
    public static function captureTypes(): array
    {
        return ['query', 'exception', 'log', 'job', 'mail', 'notification', 'cache', 'command', 'schedule', 'http'];
    }

    public function mount(string $slug): void
    {
        $this->authorize('panel.manage');
        $this->slug = $slug;

        $project = $this->project();
        $this->name = $project->name;
        $this->client = (string) $project->client;
        $this->contact = (string) $project->contact;
        $this->group = (string) ($project->group?->name ?? '');
        $this->tags = $project->tags->pluck('name')->implode(', ');

        $this->captureProfile = $project->capture_profile ?? 'full';
        $gate = Cast::arr(Cast::arr($project->config['sample'] ?? null)['type_gate'] ?? null);
        foreach (self::captureTypes() as $type) {
            // A type is enabled unless the gate explicitly disables it (false).
            $this->typeGates[$type] = ! (array_key_exists($type, $gate) && Cast::bool($gate[$type]) === false);
        }
    }

    private function project(): ProjectModel
    {
        return ProjectModel::where('slug', $this->slug)->firstOrFail();
    }

    public function save(ProjectManager $projects): void
    {
        $this->authorize('panel.manage');
        $this->validate(['name' => 'required|string|max:120']);

        $projects->updateDetails($this->project(), [
            'name' => $this->name,
            'client' => $this->client,
            'contact' => $this->contact,
            'group' => $this->group,
            'tags' => $this->tags,
        ]);
        $this->audit('panel.project.update', $this->slug);

        session()->flash('admin_project_saved', true);
    }

    public function saveCapture(): void
    {
        $this->authorize('panel.manage');
        $project = $this->project();

        if (! in_array($this->captureProfile, [CaptureProfiles::LEAN, CaptureProfiles::FULL, CaptureProfiles::CUSTOM], true)) {
            $this->captureProfile = CaptureProfiles::FULL;
        }

        if ($this->captureProfile === CaptureProfiles::LEAN) {
            CaptureStatus::migrateToLean($project);
        } elseif ($this->captureProfile === CaptureProfiles::FULL) {
            $project->forceFill([
                'capture_profile' => CaptureProfiles::FULL,
                'config' => null,
                'config_version' => Cast::int($project->config_version, 0) + 1,
            ])->save();
        } else {
            // Custom: store the sparse type-gate (only disabled types are kept).
            $gate = [];
            foreach (self::captureTypes() as $type) {
                if (($this->typeGates[$type] ?? true) === false) {
                    $gate[$type] = false;
                }
            }
            $document = $gate !== [] ? ['sample' => ['type_gate' => $gate]] : [];
            $sanitized = ProjectConfig::sanitize($document);
            $project->forceFill([
                'config' => $sanitized === [] ? null : $sanitized,
                'config_version' => Cast::int($project->config_version, 0) + 1,
                'capture_profile' => $sanitized === [] ? CaptureProfiles::FULL : CaptureProfiles::CUSTOM,
            ])->save();
            $this->captureProfile = $project->fresh()->capture_profile ?? 'full';
        }

        $this->audit('panel.project.capture', $this->slug, ['profile' => $this->captureProfile]);
        session()->flash('admin_project_saved', true);
    }

    public function resetMetrics(ProjectManager $projects): void
    {
        $this->authorize('panel.manage');
        $projects->resetMetrics($this->project());
        $this->audit('panel.project.reset', $this->slug);
        session()->flash('admin_project_saved', true);
    }

    public function purge(ProjectManager $projects): void
    {
        $this->authorize('panel.manage');
        if (! in_array($this->purgeTypeChoice, self::purgeTypes(), true)) {
            $this->purgeTypeChoice = 'cache';
        }
        $projects->purgeType($this->project(), $this->purgeTypeChoice);
        $this->audit('panel.project.purge', $this->slug, ['type' => $this->purgeTypeChoice]);
        session()->flash('admin_project_saved', true);
    }

    public function deleteProject(ProjectManager $projects)
    {
        $this->authorize('panel.manage');
        if ($this->confirmSlug !== $this->slug) {
            $this->addError('confirmSlug', 'Type the exact slug to confirm deletion.');

            return null;
        }
        $projects->delete($this->project());
        $this->audit('panel.project.delete', $this->slug);

        return $this->redirect(route('admin.projects'), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.project', [
            'project' => $this->project(),
            'purgeTypes' => self::purgeTypes(),
            'captureTypes' => self::captureTypes(),
            'captureProfiles' => [CaptureProfiles::LEAN, CaptureProfiles::FULL, CaptureProfiles::CUSTOM],
        ]);
    }
}
