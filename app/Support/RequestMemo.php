<?php

namespace App\Support;

use Closure;

/**
 * Remembers a value for the length of one request (or one test). The cache is shared and lives for ever, but with the
 * database as cache every read is a query: a page that asks for the same setting 25 times would do 25 queries.
 * Registered as a "scoped" service in AppServiceProvider, so every request starts empty.
 */
class RequestMemo
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function remember(string $key, Closure $callback): mixed
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $this->values[$key] = $callback();
    }

    public function flush(): void
    {
        $this->values = [];
    }
}
