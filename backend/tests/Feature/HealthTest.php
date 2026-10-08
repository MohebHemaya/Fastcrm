<?php

use Illuminate\Support\Facades\DB;

test('health endpoint returns ok and database status when db is reachable', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'db' => 'ok',
            'data' => [
                'status' => 'ok',
                'db' => 'ok',
            ],
        ])
        ->assertJsonStructure([
            'status',
            'time',
            'db',
            'data' => ['status', 'time', 'db'],
            'meta',
            'message',
        ]);
});

test('health endpoint returns 503 and fail when db connection fails', function () {
    $originalPort = config('database.connections.mysql.port');

    try {
        config(['database.connections.mysql.port' => 3307]);
        DB::purge();

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'fail',
                'db' => 'fail',
                'data' => [
                    'status' => 'fail',
                    'db' => 'fail',
                ],
            ]);
    } finally {
        config(['database.connections.mysql.port' => $originalPort]);
        DB::purge();
    }
});
