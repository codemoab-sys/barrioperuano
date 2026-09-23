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

    public function test_cashier_can_open_a_new_register_after_the_previous_one_was_closed(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        CashRegister::create([
            'user_id' => $cashier->id,
            'opening_time' => now()->subHour(),
            'closing_time' => now()->subMinute(),
            'opening_amount' => 100,
            'closing_amount' => 100,
            'expected_amount' => 100,
            'difference' => 0,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($cashier)->post(route('cash_registers.store'), [
            'opening_amount' => 50,
        ]);

        $response->assertRedirect(route('pos.index'));
        $this->assertDatabaseHas('bape_cash_registers', [
            'user_id' => $cashier->id,
            'opening_amount' => 50,
            'status' => 'open',
        ]);
    }

    public function test_waiter_can_use_the_cash_register_opened_by_a_cashier(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $waiter = User::factory()->create(['role' => 'waiter']);

        $this->actingAs($cashier)->post(route('cash_registers.store'), [
            'opening_amount' => 100,
        ]);

        $response = $this->actingAs($waiter)->get(route('pos.index'));

        $response->assertOk();
        $this->assertSame($cashier->id, CashRegister::current()->user_id);
    }

    public function test_users_cannot_open_a_second_cash_register_while_a_shared_shift_is_open(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $secondCashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->post(route('cash_registers.store'), [
            'opening_amount' => 100,
        ]);

        $response = $this->actingAs($secondCashier)->post(route('cash_registers.store'), [
            'opening_amount' => 200,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', 'Ya hay una caja abierta.');
        $this->assertDatabaseCount('bape_cash_registers', 1);
    }

    public function test_only_the_creator_or_an_admin_can_close_the_open_cash_register(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $secondCashier = User::factory()->create(['role' => 'cashier']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($cashier)->post(route('cash_registers.store'), [
            'opening_amount' => 100,
        ]);

        $this->actingAs($secondCashier)
            ->post(route('cash_registers.processClose'), ['closing_amount' => 100])
            ->assertForbidden();

        $this->assertDatabaseHas('bape_cash_registers', [
            'user_id' => $cashier->id,
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->post(route('cash_registers.processClose'), ['closing_amount' => 100])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('bape_cash_registers', [
            'user_id' => $cashier->id,
            'status' => 'closed',
        ]);
    }

    public function test_waiter_can_only_show_the_precheck_and_cannot_charge(): void
    {
        $waiter = User::factory()->create(['role' => 'waiter']);
        $area = Area::create(['name' => 'Salon']);
        $table = Table::create([
            'area_id' => $area->id,
            'name' => 'Mesa 1',
            'status' => 'available',
        ]);
        $order = Order::create([
            'table_id' => $table->id,
            'user_id' => $waiter->id,
            'status' => 'pending',
            'total' => 25,
        ]);

        $this->actingAs($waiter)
            ->get(route('pos.checkout.modal', $order))
            ->assertForbidden();

        $this->actingAs($waiter)
            ->post(route('pos.checkout', $order))
            ->assertForbidden();
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

    public function test_history_index_filters_turns_by_status_and_renders_admin_view(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Principal']);
        $cashier = User::factory()->create(['role' => 'cashier', 'name' => 'Cajero Uno']);

        CashRegister::create([
            'user_id' => $cashier->id,
            'opening_time' => now()->subDay(),
            'opening_amount' => 80,
            'status' => 'closed',
            'expected_amount' => 120,
            'closing_amount' => 120,
            'difference' => 0,
        ]);

        CashRegister::create([
            'user_id' => $cashier->id,
            'opening_time' => now(),
            'opening_amount' => 150,
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin)->get(route('cash_registers.index', ['status' => 'open']));

        $response->assertOk();
        $response->assertViewHas('registers', function ($registers) {
            return $registers->count() === 1 && $registers->first()->status === 'open';
        });
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
