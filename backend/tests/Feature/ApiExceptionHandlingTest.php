<?php

test('unauthenticated request to api returns 401 with standard json envelope and arabic message', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401)
        ->assertJson([
            'data' => null,
            'meta' => null,
            'message' => __('api.unauthorized'),
        ]);
});

test('request to missing api route returns 404 with standard json envelope and arabic message', function () {
    $response = $this->getJson('/api/v1/non-existent-route-xyz');

    $response->assertStatus(404)
        ->assertJson([
            'data' => null,
            'meta' => null,
            'message' => __('api.not_found'),
        ]);
});
