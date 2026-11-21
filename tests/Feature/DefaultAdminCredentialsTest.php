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
        $categoriesBefore = Category::count();
        $productsBefore = Product::count();

        $this->seed();

        $this->assertDatabaseCount('bape_users', 0);
        $this->assertSame($categoriesBefore, Category::count());
        $this->assertSame($productsBefore, Product::count());
    }
}
