<?php

namespace App\Livewire\Admin;

use App\Support\WritesAudit;
use Livewire\Attributes\Layout;
use Livewire\Component;
use VictorStochero\Warden\Models\AlertRule;
use VictorStochero\Warden\Models\AlertSetting;

#[Layout('components.layouts.app')]
class Settings extends Component
{
    use WritesAudit;

    public bool $emailEnabled = false;
    public string $recipients = '';
    public string $minSeverity = 'warning';
    public int $cooldown = 300;

    // New-rule form.
    public string $ruleName = '';
    public string $ruleMetric = 'error_rate';
    public string $ruleOp = '>';
    public float $ruleThreshold = 0.0;
    public string $ruleWindow = '1h';
    public string $ruleSeverity = 'warning';

    public const SEVERITIES = ['info', 'warning', 'critical'];
    public const METRICS = ['error_rate', 'p95', 'throughput', 'errors', 'slow', 'failed_jobs', 'cache_hit_rate'];
    public const OPS = ['>', '>=', '<', '<=', 'anomaly'];
    public const WINDOWS = ['15m', '1h', '6h', '24h', '7d'];

    public function mount(): void
    {
        $this->authorize('panel.manage');

        $settings = AlertSetting::current();
        $this->emailEnabled = (bool) $settings->email_enabled;
        $this->recipients = implode("\n", $settings->recipients ?? []);
        $this->minSeverity = $settings->min_severity;
        $this->cooldown = (int) $settings->cooldown;
    }

    public function save(): void
    {
        $this->authorize('panel.manage');

        if (! in_array($this->minSeverity, self::SEVERITIES, true)) {
            $this->minSeverity = 'warning';
        }

        $recipients = collect(preg_split('/[\s,]+/', $this->recipients))
            ->map(fn (string $r): string => trim($r))
            ->filter(fn (string $r): bool => filter_var($r, FILTER_VALIDATE_EMAIL) !== false)
            ->values()->all();

        $settings = AlertSetting::current();
        $settings->email_enabled = $this->emailEnabled;
        $settings->recipients = $recipients;
        $settings->min_severity = $this->minSeverity;
        $settings->cooldown = max(0, $this->cooldown);
        $settings->save();

        $this->audit('panel.settings.update', 'alert-settings');
        session()->flash('settings_saved', true);
    }

    public function addRule(): void
    {
        $this->authorize('panel.manage');

        $name = trim($this->ruleName);
        if ($name === ''
            || ! in_array($this->ruleMetric, self::METRICS, true)
            || ! in_array($this->ruleOp, self::OPS, true)
            || ! in_array($this->ruleWindow, self::WINDOWS, true)
            || ! in_array($this->ruleSeverity, self::SEVERITIES, true)) {
            session()->flash('warden_error', 'Invalid alert rule.');

            return;
        }

        AlertRule::updateOrCreate(['name' => $name], [
            'metric' => $this->ruleMetric,
            'op' => $this->ruleOp,
            'threshold' => $this->ruleThreshold,
            'window' => $this->ruleWindow,
            'severity' => $this->ruleSeverity,
            'enabled' => true,
        ]);

        $this->audit('panel.settings.rule.add', $name);
        $this->ruleName = '';
        $this->ruleThreshold = 0.0;
    }

    public function deleteRule(int $ruleId): void
    {
        $this->authorize('panel.manage');
        AlertRule::query()->whereKey($ruleId)->delete();
        $this->audit('panel.settings.rule.delete', (string) $ruleId);
    }

    public function render()
    {
        return view('livewire.admin.settings', [
            'rules' => AlertRule::query()->orderBy('name')->get(),
            'severities' => self::SEVERITIES,
            'metrics' => self::METRICS,
            'ops' => self::OPS,
            'windows' => self::WINDOWS,
        ]);
    }
}
