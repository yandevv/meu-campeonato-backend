<?php

namespace Tests;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

abstract class FeatureTestCase extends TestCase
{
    use LazilyRefreshDatabase;
}
