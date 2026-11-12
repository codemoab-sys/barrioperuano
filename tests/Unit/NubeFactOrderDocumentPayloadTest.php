<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Services\Sunat\NubeFactInvoiceBuilder;
use App\Services\Sunat\SunatConfig;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class NubeFactOrderDocumentPayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        Model::setConnectionResolver($capsule->getDatabaseManager());
    }

    public function test_it_builds_nubefact_boleta_for_various_client(): void
    {
        $payload = $this->buildPayload('Boleta', null, 'CLIENTE VARIOS');

        $this->assertSame(2, $payload['tipo_de_comprobante']);
        $this->assertSame('-', $payload['cliente_tipo_de_documento']);
        $this->assertSame('-', $payload['cliente_numero_de_documento']);
    }

    public function test_it_builds_nubefact_boleta_for_dni_client(): void
    {
        $payload = $this->buildPayload('Boleta', '12345678', 'Ana');

        $this->assertSame(1, $payload['cliente_tipo_de_documento']);
        $this->assertSame('12345678', $payload['cliente_numero_de_documento']);
    }

    public function test_it_builds_nubefact_boleta_for_ruc_client(): void
    {
        $payload = $this->buildPayload('Boleta', '20123456789', 'Empresa');

        $this->assertSame(6, $payload['cliente_tipo_de_documento']);
        $this->assertSame('20123456789', $payload['cliente_numero_de_documento']);
    }

    public function test_it_builds_nubefact_factura_with_sunat_document_code(): void
    {
        $payload = $this->buildPayload('Factura', '20123456789', 'Empresa');

        $this->assertSame(1, $payload['tipo_de_comprobante']);
        $this->assertSame(6, $payload['cliente_tipo_de_documento']);
        $this->assertSame('01-F001-7', $payload['codigo_unico']);
    }

    private function buildPayload(string $type, ?string $document, string $name): array
    {
        $config = new SunatConfig([
            'sunat_igv_rate' => 18.0,
            'sunat_direccion_fiscal' => 'Lima',
        ]);
        $builder = new NubeFactInvoiceBuilder($config);

        $client = new Client();
        $client->forceFill([
            'name' => $name,
            'document_number' => $document,
            'address' => 'Lima',
            'email' => '',
        ]);

        $category = new Category();
        $category->forceFill(['name' => 'Comida']);

        $product = new Product();
        $product->forceFill(['id' => 1, 'name' => 'Plato']);
        $product->setRelation('category', $category);

        $detail = new OrderDetail();
        $detail->forceFill(['price' => 11.80, 'quantity' => 1]);
        $detail->setRelation('product', $product);

        $order = new Order();
        $order->forceFill([
            'document_type' => $type,
            'client_document' => $document,
            'client_name' => $name,
            'serie' => $type === 'Factura' ? 'F001' : 'B001',
            'correlativo' => 7,
            'payment_method' => 'cash',
            'discount' => 0,
        ]);
        $order->setRelation('client', $client);
        $order->setRelation('delivery', null);
        $order->setRelation('details', collect([$detail]));

        return $builder->build($order);
    }
}
