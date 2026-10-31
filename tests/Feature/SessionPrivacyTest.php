<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
});

it('does not store an IP address or browser details in the sessions table', function () {
    config(['session.driver' => 'database']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->withHeader('User-Agent', 'TestBrowser/1.0')
        ->get('/privacy')
        ->assertOk();

    $rows = DB::table('sessions')->get();

    expect($rows)->not->toBeEmpty();
    foreach ($rows as $row) {
        expect($row->ip_address)->toBeNull();
        expect($row->user_agent)->toBeNull();
    }
});
