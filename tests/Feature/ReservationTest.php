<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function table(int $seats = 4): Table
    {
        $area = Area::create(['name' => 'Salón']);

        return Table::create([
            'area_id' => $area->id,
            'name' => 'Mesa 1',
            'seats' => $seats,
            'status' => 'available',
        ]);
    }

    public function test_rejects_reservation_in_the_past(): void
    {
        $response = $this->actingAs($this->authenticatedUser())->post(route('reservations.store'), [
            'client_name' => 'Cliente de prueba',
            'reservation_time' => Carbon::now()->subHour()->format('Y-m-d H:i'),
            'people' => 2,
        ]);

        $response->assertSessionHasErrors('reservation_time');
        $this->assertDatabaseCount('bape_reservations', 0);
    }

    public function test_rejects_more_people_than_table_capacity(): void
    {
        $table = $this->table(2);

        $response = $this->actingAs($this->authenticatedUser())->from(route('reservations.index'))
            ->post(route('reservations.store'), [
                'client_name' => 'Cliente de prueba',
                'reservation_time' => Carbon::now()->addDay()->setTime(20, 0)->format('Y-m-d H:i'),
                'people' => 3,
                'table_id' => $table->id,
            ]);

        $response->assertRedirect(route('reservations.index'))
            ->assertSessionHas('error');
        $this->assertDatabaseCount('bape_reservations', 0);
    }

    public function test_rejects_overlapping_active_reservation_for_table(): void
    {
        $table = $this->table();
        $time = Carbon::now()->addDay()->setTime(20, 0);

        Reservation::create([
            'client_name' => 'Cliente existente',
            'reservation_time' => $time,
            'people' => 2,
            'table_id' => $table->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->authenticatedUser())->from(route('reservations.index'))
            ->post(route('reservations.store'), [
                'client_name' => 'Cliente nuevo',
                'reservation_time' => $time->copy()->addHour()->format('Y-m-d H:i'),
                'people' => 2,
                'table_id' => $table->id,
            ]);

        $response->assertRedirect(route('reservations.index'))
            ->assertSessionHas('error');
        $this->assertDatabaseCount('bape_reservations', 1);
    }
}
