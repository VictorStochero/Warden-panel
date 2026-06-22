<?php

namespace App\Http\Controllers\Api;

use App\Support\Ranges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use VictorStochero\Warden\Dashboard\DashboardRepository;

/**
 * Read-only JSON API for the fleet, authenticated by an API token
 * (VictorStochero\Warden\Http\Middleware\AuthorizeApiToken). All reads go
 * through DashboardRepository — the same read layer the dashboard uses.
 */
class ReadController
{
    /** Recorder types exposed by the events endpoint. */
    private const TYPES = ['query', 'exception', 'log', 'job', 'mail', 'notification', 'cache', 'command', 'schedule', 'http', 'request'];

    public function overview(DashboardRepository $dashboard): JsonResponse
    {
        $overview = $dashboard->overview();

        return response()->json([
            'projects' => collect($overview['projects'])->map(fn ($p): array => [
                'name' => $p->name,
                'slug' => $p->slug,
                'throughput' => $p->throughput ?? 0,
                'error_rate' => $p->error_rate ?? 0,
                'p95_ms' => $p->p95_ms ?? null,
                'health' => $p->health ?? null,
            ])->values(),
            'open_issues' => $overview['open_issues'],
            'open_incidents' => $overview['open_incidents'],
            'throughput' => $overview['throughput'],
        ]);
    }

    public function project(string $slug, Request $request, DashboardRepository $dashboard): JsonResponse
    {
        $project = $this->resolve($slug, $dashboard);
        $range = Ranges::sanitize($request->query('range'));

        return response()->json([
            'project' => ['name' => $project->name, 'slug' => $project->slug],
            'range' => $range,
            'kpis' => $dashboard->kpis($project->id, $range),
        ]);
    }

    public function events(string $slug, string $type, Request $request, DashboardRepository $dashboard): JsonResponse
    {
        if (! in_array($type, self::TYPES, true)) {
            abort(422, 'Unknown event type.');
        }
        $project = $this->resolve($slug, $dashboard);
        $range = Ranges::sanitize($request->query('range'));
        $limit = min(200, max(1, (int) $request->query('limit', 50)));

        return response()->json([
            'type' => $type,
            'range' => $range,
            'events' => $dashboard->recentEvents($project->id, $type, $limit, $range)->values(),
        ]);
    }

    private function resolve(string $slug, DashboardRepository $dashboard): object
    {
        try {
            return $dashboard->project($slug);
        } catch (\Throwable) {
            abort(404, 'Unknown project.');
        }
    }
}
