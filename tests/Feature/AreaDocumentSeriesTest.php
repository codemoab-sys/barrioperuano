<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\DocumentSeries;
use App\Models\Table;
use Tests\TestCase;

class AreaDocumentSeriesTest extends TestCase
{
    public function test_each_area_can_reserve_an_independent_series_number(): void
    {
        $firstArea = Area::create(['name' => 'Local 1']);
        $secondArea = Area::create(['name' => 'Local 2']);

        DocumentSeries::create([
            'area_id' => $firstArea->id,
            'document_code' => '03',
            'document_type' => 'boleta',
            'serie' => 'B001',
            'last_number' => 0,
            'is_active' => true,
        ]);
        DocumentSeries::create([
            'area_id' => $secondArea->id,
            'document_code' => '03',
            'document_type' => 'boleta',
            'serie' => 'B002',
            'last_number' => 4,
            'is_active' => true,
        ]);

        $first = DocumentSeries::next('boleta', $firstArea->id);
        $second = DocumentSeries::next('boleta', $secondArea->id);

        $this->assertSame(['serie' => 'B001', 'correlativo' => 1], $first);
        $this->assertSame(['serie' => 'B002', 'correlativo' => 5], $second);
    }

    public function test_existing_order_area_can_be_used_to_select_its_series(): void
    {
        $area = Area::create(['name' => 'Local principal']);
        $table = Table::create([
            'area_id' => $area->id,
            'name' => 'Mesa 1',
            'status' => 'available',
        ]);

        DocumentSeries::create([
            'area_id' => $area->id,
            'document_code' => '01',
            'document_type' => 'factura',
            'serie' => 'F001',
            'last_number' => 0,
            'is_active' => true,
        ]);

        $this->assertSame($area->id, $table->area()->value('id'));
        $this->assertSame('F001', DocumentSeries::next('factura', $table->area_id)['serie']);
    }
}