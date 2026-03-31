<?php

namespace Tests;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

abstract class IntegrationTestCase extends TestCase
{
    use LazilyRefreshDatabase;
}
