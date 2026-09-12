<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class DefaultAdminCredentialsTest extends TestCase
{
    public function test_database_seeder_does_not_create_demo_data(): void
    {
        $this->seed();

        $this->assertDatabaseCount('bape_users', 0);
        $this->assertDatabaseCount('bape_categories', 0);
        $this->assertDatabaseCount('bape_products', 0);
    }
}
