<?php

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;

it('stops a test that forgot to fake a request, instead of talking to the internet', function () {
    expect(fn () => Http::get('https://opendata.rdw.nl/resource/m9d7-ebf2.json'))->toThrow(StrayRequestException::class);
});

it('lets a request through that was faked', function () {
    Http::fake(['opendata.rdw.nl/*' => Http::response([['kenteken' => 'AB123C']])]);

    expect(Http::get('https://opendata.rdw.nl/resource/m9d7-ebf2.json')->json())->toBe([['kenteken' => 'AB123C']]);
});
