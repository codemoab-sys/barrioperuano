<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCategorySafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_sensitive_history_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'cashier']);
        CashRegister::create([
            'user_id' => $user->id,
            'opening_time' => now(),
            'opening_amount' => 50,
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin)->delete(route('users.destroy', $user));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bape_users', ['id' => $user->id]);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Bebidas', 'is_active' => true]);
        $category->products()->create([
            'name' => 'Jugo',
            'price' => 12,
            'cost' => 5,
            'stock' => 10,
            'is_active' => true,
            'is_saleable' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('categories.destroy', $category));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bape_categories', ['id' => $category->id]);
    }
}
