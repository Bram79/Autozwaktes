<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests never talk to the internet (RDW, Wikidata, Anthropic): a request that was not faked with Http::fake() fails the test
        Http::preventStrayRequests();
    }
}
