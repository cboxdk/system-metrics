<?php

use PHPeek\SystemMetrics\Config\SystemMetricsConfig;

/*
|--------------------------------------------------------------------------
| Test Case Setup
|--------------------------------------------------------------------------
|
| Reset SystemMetricsConfig before each test to ensure test isolation.
| This prevents tests from affecting each other through shared static state.
|
*/

beforeEach(function () {
    SystemMetricsConfig::reset();
});
