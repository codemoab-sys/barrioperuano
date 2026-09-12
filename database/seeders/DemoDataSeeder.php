<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Setting;
use App\Models\Category;
use App\Models\Product;
use App\Models\Area;
use App\Models\Table;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Reservation;
use App\Models\Expense;
use App\Models\InventoryLog;

class DemoDataSeeder extends Seeder
{
    public function run()
    {
        if (! config('demo.allow_data', false)) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        // 1. LIMPIAR BASE DE DATOS (MANTENER ADMIN Y SETTINGS)
        DB::table('bape_inventory_logs')->truncate();
        DB::table('bape_product_ingredients')->truncate();
        DB::table('bape_order_details')->truncate();
        DB::table('bape_orders')->truncate();
        DB::table('bape_reservations')->truncate();
        DB::table('bape_expenses')->truncate();
        DB::table('bape_tables')->truncate();
        DB::table('bape_areas')->truncate();
        DB::table('bape_products')->truncate();
        DB::table('bape_categories')->truncate();
        DB::table('bape_clients')->truncate();

        // Limpiar usuarios excepto el administrador (asumiendo que el admin es ID 1 o tiene rol admin)
        User::where('role', '!=', 'admin')->delete();

        Schema::enableForeignKeyConstraints();

        $adminId = User::where('role', 'admin')->first()->id ?? 1;
        $now = Carbon::now();

        // 2. CREAR USUARIOS (ROLES) - Hasta llegar a 10
        $roles = ['cashier', 'waiter', 'kitchen', 'waiter', 'cashier', 'waiter', 'kitchen', 'waiter', 'waiter'];
        foreach ($roles as $index => $role) {
            User::create([
                'name' => ucfirst($role) . ' ' . ($index + 1),
                'email' => $role . ($index + 1) . '@restaurante.com',
                'password' => bcrypt('password'),
                'role' => $role,
            ]);
        }

        // 3. CREAR CATEGORÍAS (10)
        $categoryNames = ['Entradas', 'Platos Fuertes', 'Bebidas Frías', 'Bebidas Calientes', 'Postres', 'Ensaladas', 'Sopas', 'Pizzas', 'Hamburguesas', 'Especialidades'];
        $categories = [];
        foreach ($categoryNames as $name) {
            $categories[] = Category::create([
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 4. CREAR PRODUCTOS (10)
        $productsData = [
            ['Tequeños', 1, 12.00], ['Lomo Saltado', 2, 35.00], ['Limonada Frozen', 3, 8.00],
            ['Café Americano', 4, 6.00], ['Cheesecake', 5, 15.00], ['Ensalada César', 6, 22.00],
            ['Dieta de Pollo', 7, 18.00], ['Pizza Hawaiana', 8, 30.00], ['Hamburguesa Royal', 9, 25.00],
            ['Ceviche', 10, 38.00]
        ];
        $products = [];
        foreach ($productsData as $index => $p) {
            $products[] = Product::create([
                'category_id' => $categories[$p[1] - 1]->id,
                'name' => $p[0],
                'price' => $p[2],
                'stock' => rand(20, 100),
                'is_saleable' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 5. CREAR ÁREAS Y MESAS (10 Mesas en total)
        $area1 = Area::create(['name' => 'Salón Principal']);
        $area2 = Area::create(['name' => 'Terraza']);
        $tables = [];
        for ($i = 1; $i <= 6; $i++) {
            $tables[] = Table::create(['area_id' => $area1->id, 'name' => 'Mesa ' . $i, 'seats' => 4, 'status' => 'available']);
        }
        for ($i = 1; $i <= 4; $i++) {
            $tables[] = Table::create(['area_id' => $area2->id, 'name' => 'T-' . $i, 'seats' => 2, 'status' => 'available']);
        }

        // 6. CREAR CLIENTES (10)
        $clients = [];
        for ($i = 1; $i <= 10; $i++) {
            $clients[] = Client::create([
                'name' => 'Cliente Frecuente ' . $i,
                'document_type' => 'DNI',
                'document_number' => 70000000 + $i,
                'email' => 'cliente'.$i.'@correo.com',
                'phone' => '99988877'.$i,
            ]);
        }

        // 7. CREAR GASTOS (10)
        $expenseDesc = ['Compra de verduras', 'Pago de luz', 'Compra de carnes', 'Mantenimiento', 'Artículos limpieza', 'Pago de agua', 'Publicidad', 'Compra de bebidas', 'Gas', 'Transporte'];
        foreach ($expenseDesc as $index => $desc) {
            Expense::create([
                'description' => $desc,
                'amount' => rand(50, 300),
                'user_id' => $adminId,
                'created_at' => $now->copy()->subDays(rand(1, 30)),
            ]);
        }

        // 8. CREAR RESERVAS (10 - distribuidas entre pasado y futuro)
        for ($i = 1; $i <= 10; $i++) {
            Reservation::create([
                'client_name' => 'Reserva ' . $i,
                'phone' => '90010020'.$i,
                'reservation_time' => $now->copy()->addDays(rand(-10, 10))->setHour(rand(12, 21))->setMinute(0),
                'people' => rand(2, 6),
                'table_id' => $tables[rand(0, 9)]->id,
                'status' => rand(0, 1) ? 'confirmed' : 'pending',
                'note' => 'Reserva generada automáticamente',
            ]);
        }

        // 9. CREAR ÓRDENES HISTÓRICAS PARA EL GRÁFICO (Últimos 12 meses + Este mes)
        // Generaremos unas 150 órdenes para tener una curva bonita
        for ($i = 0; $i < 150; $i++) {
            $orderDate = $now->copy()->subDays(rand(0, 360));

            $orderTotal = 0;
            $order = Order::create([
                'table_id' => $tables[rand(0, 9)]->id,
                'user_id' => $adminId,
                'client_id' => rand(0, 1) ? $clients[rand(0, 9)]->id : null,
                'client_name' => 'Público General',
                'status' => 'completed',
                'document_type' => 'Ticket',
                'payment_method' => rand(0, 2) ? 'cash' : 'card',
                'total' => 0, // Se actualiza luego
                'received_amount' => 0,
                'change_amount' => 0,
                'created_at' => $orderDate,
                'updated_at' => $orderDate,
            ]);

            // Detalles de orden (1 a 4 productos por orden)
            $numItems = rand(1, 4);
            for ($j = 0; $j < $numItems; $j++) {
                $prod = $products[rand(0, 9)];
                $qty = rand(1, 3);
                $price = $prod->price;
                $subtotal = $qty * $price;
                $orderTotal += $subtotal;

                OrderDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $prod->id,
                    'quantity' => $qty,
                    'price' => $price,
                    'status' => 'served',
                    'created_at' => $orderDate,
                    'updated_at' => $orderDate,
                ]);

                // Inventario Log
                InventoryLog::create([
                    'product_id' => $prod->id,
                    'user_id' => $adminId,
                    'type' => 'sale',
                    'quantity' => -$qty,
                    'note' => 'Venta Orden #' . $order->id,
                    'created_at' => $orderDate,
                ]);
            }

            $order->update([
                'total' => $orderTotal,
                'received_amount' => $orderTotal,
            ]);
        }

        // 10. CREAR ÓRDENES ACTIVAS (Para que se vean mesas activas en el dashboard)
        for ($i = 0; $i < 3; $i++) {
            $table = $tables[$i];
            $table->update(['status' => 'occupied']);

            $order = Order::create([
                'table_id' => $table->id,
                'user_id' => $adminId,
                'status' => 'pending',
                'client_name' => 'Público General',
                'total' => 0,
                'created_at' => clone $now,
                'updated_at' => clone $now,
            ]);

            $prod = $products[rand(0, 9)];
            OrderDetail::create([
                'order_id' => $order->id,
                'product_id' => $prod->id,
                'quantity' => 2,
                'price' => $prod->price,
                'status' => 'cooking',
                'created_at' => clone $now,
                'updated_at' => clone $now,
            ]);
            $order->update(['total' => $prod->price * 2]);
        }

        // Setear meta mensual para el dashboard
        Setting::updateOrCreate(
            ['key' => 'monthly_goal'],
            ['value' => '8000']
        );
    }
}
