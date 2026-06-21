<?php

use App\Models\User;

it('disables public registration', function () {
    $this->get('/register')->assertNotFound();
});

it('grants panel.manage only to admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $viewer = User::factory()->create(['is_admin' => false]);

    expect($admin->can('panel.manage'))->toBeTrue();
    expect($viewer->can('panel.manage'))->toBeFalse();
});
