<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Tests\TestCase;

class ExpenseSafetyTest extends TestCase
{
    public function test_non_admin_cannot_delete_a_registered_expense(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $expense = Expense::create([
            'description' => 'Compra de insumos',
            'amount' => 50,
            'user_id' => $cashier->id,
        ]);

        $response = $this->actingAs($cashier)
            ->from(route('sales.index'))
            ->delete(route('expenses.destroy', $expense));

        $response->assertRedirect(route('sales.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bape_expenses', ['id' => $expense->id]);
    }
}