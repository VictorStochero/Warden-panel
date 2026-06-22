<?php

namespace App\Alerting;

use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Issue;
use VictorStochero\Warden\Models\Project;

/**
 * Composes the didactic, actionable message that every alert channel and the
 * Issue UI share — exception + the app frame to fix (file:line) + a deep link.
 */
class AlertComposer
{
    public function __construct(protected DashboardRepository $dashboard) {}

    /**
     * The first non-vendor stack frame ("where to fix"), e.g. app/Foo.php:42.
     * Falls back to the throw site when every frame is in vendor/.
     *
     * @param  array<int, array<string, mixed>>|null  $stack
     */
    public static function topAppFrame(?array $stack): ?string
    {
        foreach ($stack ?? [] as $frame) {
            $file = is_array($frame) ? ($frame['file'] ?? null) : null;
            if (is_string($file) && $file !== '' && ! str_starts_with($file, 'vendor/')) {
                return self::frameLabel($frame);
            }
        }

        $first = is_array($stack[0] ?? null) ? $stack[0] : null;

        return $first !== null && ! empty($first['file']) ? self::frameLabel($first) : null;
    }

    /** @param  array<string, mixed>  $frame */
    private static function frameLabel(array $frame): string
    {
        $file = (string) ($frame['file'] ?? '');
        $line = $frame['line'] ?? null;

        return $line !== null ? $file.':'.$line : $file;
    }

    /**
     * Build the didactic message for an incident.
     *
     * @return array{title: string, severity: string, project: string, where: ?string, message: string, occurrences: ?int, link: ?string, started: ?string}
     */
    public function forIncident(Incident $incident): array
    {
        $project = Project::query()->find($incident->project_id);
        $projectName = $project->name ?? ('#'.$incident->project_id);

        $issue = $this->issueFor($incident);
        $where = $issue !== null ? self::topAppFrame(is_array($issue->stack) ? $issue->stack : null) : null;

        $link = null;
        if ($project !== null && $issue !== null) {
            $link = route('project.issue', ['slug' => $project->slug, 'issueId' => $issue->id]);
        }

        return [
            'title' => $issue !== null ? $issue->class : $incident->subject,
            'severity' => $incident->severity,
            'project' => $projectName,
            'where' => $where,
            'message' => $issue !== null ? (string) $issue->message : (string) $incident->summary,
            'occurrences' => $issue?->count,
            'link' => $link,
            'started' => $incident->started_at?->toDateTimeString(),
        ];
    }

    /** Render the composed message as a plain-text/markdown block for chat/email. */
    public function text(array $msg): string
    {
        $icon = ['critical' => '🔴', 'warning' => '🟠', 'info' => '🔵'][$msg['severity']] ?? '⚪';
        $lines = ["{$icon} {$msg['title']} · {$msg['project']}"];
        if ($msg['where']) {
            $lines[] = "Where: {$msg['where']}";
        }
        if ($msg['occurrences'] !== null) {
            $lines[] = "{$msg['occurrences']} occurrences";
        }
        if ($msg['message'] !== '') {
            $lines[] = '"'.mb_substr($msg['message'], 0, 160).'"';
        }
        if ($msg['link']) {
            $lines[] = "→ {$msg['link']}";
        }

        return implode("\n", $lines);
    }

    private function issueFor(Incident $incident): ?Issue
    {
        $issueId = is_array($incident->meta) ? ($incident->meta['issue_id'] ?? null) : null;
        if ($issueId !== null) {
            return Issue::query()->find($issueId);
        }

        if (str_starts_with((string) $incident->subject, 'issue:')) {
            $fingerprint = substr((string) $incident->subject, 6);

            return Issue::query()->where('project_id', $incident->project_id)->where('fingerprint', $fingerprint)->first();
        }

        return null;
    }
}
