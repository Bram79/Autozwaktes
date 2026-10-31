<?php

namespace App\Support;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * Laravel's database sessions save the visitor's IP address and browser for every session.
 * We don't need that and don't want to keep it (see the privacy statement), so this handler leaves it out.
 */
class AnonymousSessionHandler extends DatabaseSessionHandler
{
    protected function addRequestInformation(&$payload)
    {
        return $this;
    }
}
