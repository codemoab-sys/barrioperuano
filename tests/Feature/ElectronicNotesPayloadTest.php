<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use App\Services\Sunat\NubeFactCreditNoteBuilder;
use App\Services\Sunat\NubeFactDebitNoteBuilder;
use App\Services\Sunat\SunatConfig;
use Tests\TestCase;

class ElectronicNotesPayloadTest extends TestCase
{
    public function test_credit_note_item_return_payload_uses_selected_quantity(): void
    {
        [$order, $firstDetail, $secondDetail] = $this->createOrderWithDetails();
        $note = CreditNote::create([
            'order_id' => $order->id,
            'serie' => 'BC01',
            'correlativo' => 1,
            'reason_code' => '07',
            'reason_description' => 'Devolución de producto',
            'items' => [(string) $firstDetail->id => 1],
            'sunat_status' => 'PENDING',
        ]);

        $payload = (new NubeFactCreditNoteBuilder($this->config()))->build($note);

        $this->assertCount(1, $payload['items']);
        $this->assertSame(1.0, $payload['items'][0]['cantidad']);
        $this->assertSame((string) $firstDetail->product_id, $payload['items'][0]['codigo']);
        $this->assertNotSame((string) $secondDetail->product_id, $payload['items'][0]['codigo']);
    }

    public function test_debit_note_payload_uses_additional_amount(): void
    {
        [$order] = $this->createOrderWithDetails();
        $note = DebitNote::create([
            'order_id' => $order->id,
            'serie' => 'FC01',
            'correlativo' => 1,
            'reason_code' => '2',
            'reason_description' => 'Aumento de valor',
            'amount' => 10.00,
            'sunat_status' => 'PENDING',
        ]);

        $payload = (new NubeFactDebitNoteBuilder($this->config()))->build($note);

        $this->assertCount(1, $payload['items']);
        $this->assertSame(10.00, $payload['items'][0]['precio_unitario']);
        $this->assertSame(10.00, $payload['total']);
    }

    private function config(): SunatConfig
    {
        return new SunatConfig([
            'sunat_ruc' => '20123456789',
            'sunat_razon_social' => 'Restaurante de Prueba',
            'sunat_direccion_fiscal' => 'Av. Prueba 123',
            'sunat_ubigeo' => '150101',
            'sunat_igv_rate' => '18',
        ]);
    }

    private function createOrderWithDetails(): array
    {
        $user = User::factory()->create(['role' => 'admin']);
        $area = Area::create(['name' => 'Salón']);
        $table = Table::create([
            'area_id' => $area->id,
            'name' => 'Mesa 1',
            'seats' => 4,
            'status' => 'available',
        ]);
        $category = \App\Models\Category::create(['name' => 'Prueba', 'is_active' => true]);
        $firstProduct = Product::create([
            'name' => 'Producto A', 'category_id' => $category->id, 'price' => 10,
            'cost' => 5, 'stock' => 10, 'is_active' => true, 'is_saleable' => true,
        ]);
        $secondProduct = Product::create([
            'name' => 'Producto B', 'category_id' => $category->id, 'price' => 20,
            'cost' => 10, 'stock' => 10, 'is_active' => true, 'is_saleable' => true,
        ]);
        $client = Client::create([
            'name' => 'Cliente de prueba',
            'document_number' => '20123456789',
            'address' => 'Av. Prueba 123',
        ]);
        $order = Order::create([
            'table_id' => $table->id, 'user_id' => $user->id, 'client_id' => $client->id, 'status' => 'completed',
            'total' => 30, 'document_type' => 'Factura', 'client_name' => 'Cliente de prueba',
            'client_document' => '20123456789', 'payment_method' => 'cash',
            'serie' => 'F001', 'correlativo' => 1, 'sunat_status' => 'ACCEPTED',
        ]);
        $firstDetail = $order->details()->create(['product_id' => $firstProduct->id, 'quantity' => 2, 'price' => 10]);
        $secondDetail = $order->details()->create(['product_id' => $secondProduct->id, 'quantity' => 1, 'price' => 20]);

        return [$order, $firstDetail, $secondDetail];
    }
}
