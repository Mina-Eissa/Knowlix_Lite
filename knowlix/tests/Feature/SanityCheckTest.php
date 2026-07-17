<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SanityCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_connection_and_migrations_work(): void
    {
        $this->assertDatabaseCount('workspaces', 0);
        $this->assertNotNull(DB::connection()->getPdo());
    }
}
