<?php

use App\Models\User;

test('login page is available', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('Sistem Evaluasi Kinerja Vendor');
});

test('dashboard renders seeded vendor monitoring data for the logged in role', function () {
    $this->seed();
    $user = User::query()->where('work_email', 'ayu@chakra.com')->firstOrFail();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard Monitoring');
    $response->assertSee('PT Suryatama');
    $response->assertSee('Vendor Management Specialist');
    $response->assertDontSee('Lihat sebagai');
});
