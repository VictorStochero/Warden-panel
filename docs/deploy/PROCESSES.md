# Warden Panel — required processes

The panel needs two long-running concerns on the VPS:

1. **Scheduler** (drives the Warden parent pipeline — aggregate/evaluate/partition/prune):
   `* * * * * cd /path/to/warden-panel && php artisan schedule:run >> /dev/null 2>&1`
2. **Queue worker** (alerts + maintenance jobs): `php artisan queue:work --tries=3`

KPI freshness tracks the `warden:aggregate` cadence; the package schedules it frequently. No Node/Vite is needed at runtime (assets are prebuilt with `npm run build`).
