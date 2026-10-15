<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosPendingOrderMutationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Table $table;

    private Table $freeTable;

    private Table $occupiedTable;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->user = User::factory()->create(['role' => 'cashier']);

        $area = Area::create(['name' => 'Salón']);
        $this->table = Table::create(['area_id' => $area->id, 'name' => 'Mesa 1']);
        $this->freeTable = Table::create(['area_id' => $area->id, 'name' => 'Mesa 2']);
        $this->occupiedTable = Table::create(['area_id' => $area->id, 'name' => 'Mesa 3']);

        CashRegister::create([
            'user_id' => $this->user->id,
            'opening_time' => now(),
            'opening_amount' => 0,
            'status' => 'open',
        ]);

        $category = Category::create(['name' => 'Platos']);
        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Papa a la Huancaína',
            'price' => 15,
            'stock' => 10,
            'is_active' => true,
        ]);

        $occupiedOrder = Order::create([
            'table_id' => $this->occupiedTable->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
            'total' => 15,
        ]);
        $occupiedOrder->details()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 15,
            'status' => 'pending',
        ]);
    }

    // --- removeItem ---

    public function test_remove_item_works_on_pending_order(): void
    {
        $order = $this->pendingOrder([1, 1]);
        $details = $order->details()->orderBy('id')->get();
        $first = $details->first();

        $this->actingAs($this->user)
            ->deleteJson(route('pos.remove', $first))
            ->assertStatus(200);

        $this->assertDatabaseMissing('bape_order_details', ['id' => $first->id]);
        $this->assertSame(1, $order->fresh()->details()->count());
        $this->assertSame(15.0, (float) $order->fresh()->total);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_remove_item_is_rejected_on_completed_order_without_partial_changes(): void
    {
        $order = $this->pendingOrder([1, 1]);
        $this->checkout($order);
        $detail = $order->details()->orderBy('id')->firstOrFail();
        $totalBefore = (float) $order->fresh()->total;

        $response = $this->actingAs($this->user)
            ->deleteJson(route('pos.remove', $detail));
        $response->assertStatus(403);

        $this->assertDatabaseHas('bape_order_details', ['id' => $detail->id]);
        $this->assertSame(2, $order->fresh()->details()->count());
        $this->assertSame($totalBefore, (float) $order->fresh()->total);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertDatabaseCount('bape_inventory_logs', 2);
    }

    public function test_remove_item_never_deletes_a_completed_order_via_cleanup(): void
    {
        $order = $this->pendingOrder([1]);
        $this->checkout($order);
        $detail = $order->details()->firstOrFail();

        $this->actingAs($this->user)
            ->deleteJson(route('pos.remove', $detail))
            ->assertStatus(403);

        $this->assertDatabaseHas('bape_orders', ['id' => $order->id, 'status' => 'completed']);
        $this->assertDatabaseHas('bape_order_details', ['id' => $detail->id]);
        $this->assertSame(9.0, (float) $this->product->fresh()->stock);
        $this->assertDatabaseCount('bape_inventory_logs', 1);
    }

    // --- applyDiscount ---

    public function test_apply_discount_updates_pending_order_total(): void
    {
        $order = $this->pendingOrder([1]);

        $this->actingAs($this->user)
            ->post(route('pos.discount', $order), ['discount' => 5])
            ->assertStatus(200);

        $order->refresh();
        $this->assertSame(5.0, (float) $order->discount);
        $this->assertSame(10.0, (float) $order->total);
        $this->assertSame('pending', $order->status);
    }

    public function test_apply_discount_still_rejects_discount_above_subtotal_without_changes(): void
    {
        $order = $this->pendingOrder([1]);

        $this->actingAs($this->user)
            ->from(route('pos.order', $this->table))
            ->post(route('pos.discount', $order), ['discount' => 100])
            ->assertStatus(302)
            ->assertSessionHas('error', 'El descuento no puede superar el subtotal.');

        $order->refresh();
        $this->assertSame(0.0, (float) $order->discount);
        $this->assertSame(15.0, (float) $order->total);
    }

    public function test_apply_discount_is_rejected_on_completed_order_without_changes(): void
    {
        $order = $this->pendingOrder([1]);
        $this->checkout($order);
        $totalBefore = (float) $order->fresh()->total;

        $response = $this->actingAs($this->user)
            ->postJson(route('pos.discount', $order), ['discount' => 5]);
        $response->assertStatus(403);

        $order->refresh();
        $this->assertSame(0.0, (float) $order->discount);
        $this->assertSame($totalBefore, (float) $order->total);
        $this->assertSame('completed', $order->status);
        $this->assertDatabaseCount('bape_inventory_logs', 1);
    }

    // --- updatePrice ---

    public function test_update_price_works_for_servicio_lines_on_pending_order(): void
    {
        $detail = $this->addServicioLine();
        $order = $detail->order;

        $this->actingAs($this->user)
            ->postJson(route('pos.price', $detail), ['price' => 8])
            ->assertStatus(200);

        $this->assertSame(8.0, (float) $detail->fresh()->price);
        $this->assertSame(23.0, (float) $order->fresh()->total);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_update_price_keeps_servicio_only_rule(): void
    {
        $order = $this->pendingOrder([1]);
        $detail = $order->details()->firstOrFail();

        $response = $this->actingAs($this->user)
            ->postJson(route('pos.price', $detail), ['price' => 8]);
        $response->assertStatus(422);
        $this->assertStringContainsString('Servicio', (string) $response->json('error'));

        $this->assertSame(15.0, (float) $detail->fresh()->price);
    }

    public function test_update_price_is_rejected_on_completed_order_without_changes(): void
    {
        $detail = $this->addServicioLine();
        $order = $detail->order;
        $this->checkout($order);
        $totalBefore = (float) $order->fresh()->total;

        $response = $this->actingAs($this->user)
            ->postJson(route('pos.price', $detail), ['price' => 2]);
        $response->assertStatus(403);

        $this->assertSame(5.0, (float) $detail->fresh()->price);
        $this->assertSame($totalBefore, (float) $order->fresh()->total);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertDatabaseCount('bape_inventory_logs', 1);
    }

    // --- moveTable ---

    public function test_move_table_relocates_pending_order(): void
    {
        $order = $this->pendingOrder([1]);

        $this->actingAs($this->user)
            ->post(route('pos.move', $order), ['target_table_id' => $this->freeTable->id])
            ->assertRedirect(route('pos.order', $this->freeTable->id));

        $this->assertSame($this->freeTable->id, (int) $order->fresh()->table_id);
    }

    public function test_move_table_rejects_occupied_target_without_changes(): void
    {
        $order = $this->pendingOrder([1]);

        $this->actingAs($this->user)
            ->from(route('pos.order', $this->table))
            ->post(route('pos.move', $order), ['target_table_id' => $this->occupiedTable->id])
            ->assertStatus(302)
            ->assertSessionHas('error', 'Ocupada.');

        $this->assertSame($this->table->id, (int) $order->fresh()->table_id);
    }

    public function test_move_table_is_rejected_on_completed_order_without_changes(): void
    {
        $order = $this->pendingOrder([1]);
        $this->checkout($order);

        $this->actingAs($this->user)
            ->post(route('pos.move', $order), ['target_table_id' => $this->freeTable->id])
            ->assertStatus(403);

        $order->refresh();
        $this->assertSame($this->table->id, (int) $order->table_id);
        $this->assertSame('completed', $order->status);
    }

    // --- updateNote ---

    public function test_update_note_works_on_pending_order(): void
    {
        $order = $this->pendingOrder([1]);
        $detail = $order->details()->firstOrFail();

        $this->actingAs($this->user)
            ->postJson(route('pos.note', $detail), ['note' => 'Sin cebolla'])
            ->assertStatus(200);

        $this->assertSame('Sin cebolla', $detail->fresh()->note);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_update_note_is_rejected_on_completed_order_without_changes(): void
    {
        $order = $this->pendingOrder([1]);
        $detail = $order->details()->firstOrFail();
        $this->checkout($order);

        $response = $this->actingAs($this->user)
            ->postJson(route('pos.note', $detail), ['note' => 'Sin cebolla']);
        $response->assertStatus(403);

        $this->assertNull($detail->fresh()->note);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertDatabaseCount('bape_inventory_logs', 1);
    }

    private function pendingOrder(array $quantities): Order
    {
        $total = 15 * array_sum($quantities);
        $order = Order::create([
            'table_id' => $this->table->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
            'total' => $total,
        ]);

        foreach ($quantities as $quantity) {
            $order->details()->create([
                'product_id' => $this->product->id,
                'quantity' => $quantity,
                'price' => 15,
                'status' => 'pending',
            ]);
        }

        return $order;
    }

    private function addServicioLine(): OrderDetail
    {
        $serviceCategory = Category::create(['name' => 'Servicio']);
        $service = Product::create([
            'category_id' => $serviceCategory->id,
            'name' => 'Servicio de mesa',
            'price' => 5,
            'stock' => null,
            'is_active' => true,
        ]);

        $order = Order::create([
            'table_id' => $this->table->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
            'total' => 20,
        ]);

        $order->details()->create([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 15,
            'status' => 'pending',
        ]);

        return $order->details()->create([
            'product_id' => $service->id,
            'quantity' => 1,
            'price' => 5,
            'status' => 'pending',
        ]);
    }

    private function checkout(Order $order): void
    {
        $this->actingAs($this->user)->postJson(route('pos.checkout', $order), [
            'payment_method' => 'cash',
            'received_amount' => (float) $order->fresh()->total,
            'document_type' => 'Ticket',
        ])->assertStatus(200);
    }
}
