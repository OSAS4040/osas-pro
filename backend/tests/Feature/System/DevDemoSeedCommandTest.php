<?php

namespace Tests\Feature\System;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DevDemoSeedCommandTest extends TestCase
{
    #[Test]
    public function dev_demo_seed_skips_when_not_local(): void
    {
        $this->assertSame('testing', app()->environment());

        $this->artisan('dev:demo-seed')
            ->expectsOutputToContain('dev:demo-seed skipped')
            ->assertExitCode(0);
    }
}
