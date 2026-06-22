<?php

namespace App\Console\Commands;

use App\Alerting\PanelDidacticChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Models\Project;

/**
 * Sends a didactic digest of the slowest queries per active project over the
 * panel alert channel (webhook + e-mail). Schedule it (e.g. daily) for a
 * recurring "what's slow and where to look" summary.
 */
class SlowQueryDigest extends Command
{
    protected $signature = 'panel:slow-query-digest {--range=24h} {--limit=5}';

    protected $description = 'Send a didactic digest of the slowest queries per project.';

    public function handle(DashboardRepository $dashboard, PanelDidacticChannel $channel): int
    {
        $range = (string) $this->option('range');
        $limit = max(1, (int) $this->option('limit'));
        $sent = 0;

        foreach (Project::query()->where('active', true)->get() as $project) {
            $slow = $dashboard->slowQueries($project->id, $range, $limit);
            if ($slow->isEmpty()) {
                continue;
            }

            $lines = ['🐌 '.__('panel.alert.top_slow_queries')." · {$project->name}"];
            foreach ($slow as $q) {
                $lines[] = "{$q['avg']}ms · {$q['count']}× · ".Str::limit((string) $q['sql'], 90);
            }
            $lines[] = '→ '.route('project.database', $project->slug);

            $channel->deliver(implode("\n", $lines), "[Warden] slow queries · {$project->name}");
            $sent++;
        }

        $this->info("Slow-query digest sent for {$sent} project(s).");

        return self::SUCCESS;
    }
}
