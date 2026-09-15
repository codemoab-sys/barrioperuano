<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_open_one_cash_register(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($user)->post(route('cash_registers.store'), [
            'opening_amount' => 100,
        ]);

        $response->assertRedirect(route('pos.index'));
        $this->assertDatabaseHas('bape_cash_registers', [
            'user_id' => $user->id,
            'opening_amount' => 100,
            'status' => 'open',
        ]);
    }

    public function test_closing_uses_only_cash_sales_and_expenses(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);
        $register = CashRegister::create([
            'user_id' => $user->id,
            'opening_time' => now(),
            'opening_amount' => 100,
            'status' => 'open',
        ]);
        $area = Area::create(['name' => 'Salón']);
        $table = Table::create([
            'area_id' => $area->id,
            'name' => 'Mesa 1',
            'seats' => 4,
            'status' => 'available',
        ]);
        Order::create([
            'table_id' => $table->id,
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'status' => 'completed',
            'total' => 50,
            'payment_method' => 'cash',
        ]);
        Order::create([
            'table_id' => $table->id,
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'status' => 'completed',
            'total' => 200,
            'payment_method' => 'card',
        ]);
        Expense::create([
            'description' => 'Compra de insumos',
            'amount' => 10,
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
        ]);

        $response = $this->actingAs($user)->post(route('cash_registers.processClose'), [
            'closing_amount' => 140,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('bape_cash_registers', [
            'id' => $register->id,
            'expected_amount' => 140,
            'difference' => 0,
            'status' => 'closed',
        ]);
    }

    public function test_expense_requires_an_open_cash_register(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($user)->post(route('expenses.store'), [
            'description' => 'Gasto sin turno',
            'amount' => 10,
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('bape_expenses', 0);
    }
}
