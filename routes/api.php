<?php

use App\Http\Controllers\Api\ReadController;
use Illuminate\Support\Facades\Route;
use VictorStochero\Warden\Http\Middleware\AuthorizeApiToken;

/*
 * Read-only fleet API (§5.7). Authenticated by an API token minted in
 * Admin → API Tokens, sent as `Authorization: Bearer wdn_…`.
 */
Route::middleware(AuthorizeApiToken::class)->prefix('v1')->group(function () {
    Route::get('/overview', [ReadController::class, 'overview']);
    Route::get('/projects/{slug}', [ReadController::class, 'project']);
    Route::get('/projects/{slug}/events/{type}', [ReadController::class, 'events']);
});
