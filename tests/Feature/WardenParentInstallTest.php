<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use VictorStochero\Warden\Dashboard\DashboardRepository;

it('has the warden parent schema installed', function () {
    expect(Schema::hasTable('wdn_events'))->toBeTrue();
    expect(Schema::hasTable('wdn_aggregates'))->toBeTrue();
    expect(Schema::hasTable('wdn_projects'))->toBeTrue();
});

it('registers the parent ingest route', function () {
    expect(Route::has('warden.ingest'))->toBeTrue();
});

it('resolves the package read layer from the container', function () {
    expect(app(DashboardRepository::class))->toBeInstanceOf(DashboardRepository::class);
});

it('does not expose the package dashboard routes', function () {
    expect(Route::has('warden.overview'))->toBeFalse();
});
