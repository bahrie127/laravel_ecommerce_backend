<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** API marketplace: peran, kepemilikan data, order, pembayaran, resi, dan ongkir simulasi. */
class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $otherSeller;

    private User $buyer;

    private User $otherBuyer;

    private Address $address;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegionSeeder::class);
        config(['midtrans.server_key' => null, 'laravel-agora.agora.app_id' => null, 'laravel-agora.agora.app_certificate' => null]);

        $this->seller = $this->makeSeller('Batik Lestari', 445, 'Laweyan');
        $this->otherSeller = $this->makeSeller('Toko Lain', 23, 'Coblong');
        $this->buyer = User::factory()->create(['roles' => 'user']);
        $this->otherBuyer = User::factory()->create(['roles' => 'user']);
        $this->address = Address::create([
            'user_id' => $this->buyer->id, 'address' => 'Jl. Tebet 17', 'country' => 'Indonesia', 'province' => 6,
            'city' => 153, 'district' => RegionSeeder::subdistrictId(153, 'Tebet'), 'postal_code' => '12810', 'is_default' => true,
        ]);
        $category = Category::create(['seller_id' => $this->seller->id, 'name' => 'Pakaian', 'description' => '']);
        $this->product = Product::create([
            'seller_id' => $this->seller->id, 'category_id' => $category->id, 'name' => 'Kemeja Batik',
            'price' => 189000, 'stock' => 5, 'image' => 'assets/product/x.jpg',
        ]);
    }

    public function test_daftar_pembeli_selalu_peran_user_dan_email_unik(): void
    {
        $this->postJson('/api/buyer/register', ['name' => 'Coba', 'email' => 'coba@mail.test', 'password' => 'rahasia123', 'roles' => 'seller'])
            ->assertCreated();
        $this->assertSame('user', User::where('email', 'coba@mail.test')->value('roles'));

        $this->postJson('/api/buyer/register', ['name' => 'Coba', 'email' => 'coba@mail.test', 'password' => 'rahasia123'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/seller/register', ['name' => 'Toko', 'email' => 'coba@mail.test', 'password' => 'rahasia123'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'province', 'district']);
    }

    public function test_peran_dibatasi_per_rute(): void
    {
        Sanctum::actingAs($this->buyer);
        $this->getJson('/api/seller/products')->assertForbidden();
        $this->postJson('/api/seller/category', ['name' => 'Iseng'])->assertForbidden();

        Sanctum::actingAs($this->seller);
        $this->getJson('/api/buyer/stores')->assertForbidden();
        $this->postJson('/api/buyer/orders', [])->assertForbidden();
    }

    public function test_penjual_tidak_bisa_mengubah_produk_toko_lain_dan_produk_terjual_hanya_dinonaktifkan(): void
    {
        Sanctum::actingAs($this->otherSeller);
        $this->postJson("/api/seller/products/{$this->product->id}", ['name' => 'Diubah'])->assertNotFound();
        $this->deleteJson("/api/seller/products/{$this->product->id}")->assertNotFound();

        $order = $this->makeOrder(Order::SHIPPING);
        Sanctum::actingAs($this->seller);
        $this->deleteJson("/api/seller/products/{$this->product->id}")->assertOk();
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'is_active' => 0]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id]);

        Sanctum::actingAs($this->buyer);
        $this->getJson("/api/buyer/stores/{$this->seller->id}/products")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_tambah_produk_memakai_kategori_milik_sendiri(): void
    {
        Storage::fake('public');
        $otherCategory = Category::create(['seller_id' => $this->otherSeller->id, 'name' => 'Lain', 'description' => '']);
        Sanctum::actingAs($this->seller);

        $payload = ['name' => 'Tas Rotan', 'price' => 159000, 'stock' => 3, 'image' => UploadedFile::fake()->image('tas.jpg')];
        $this->postJson('/api/seller/products', [...$payload, 'category_id' => $otherCategory->id])->assertUnprocessable();
        $this->postJson('/api/seller/products', [...$payload, 'category_id' => $this->product->category_id, 'image' => UploadedFile::fake()->image('tas.jpg')])
            ->assertCreated();
    }

    public function test_alamat_hanya_bisa_diubah_pemiliknya_dan_hanya_satu_alamat_utama(): void
    {
        $payload = [
            'address' => 'Jl. Baru 1', 'country' => 'Indonesia', 'province' => 9, 'city' => 23,
            'district' => RegionSeeder::subdistrictId(23, 'Coblong'), 'postal_code' => '40132', 'is_default' => true,
        ];

        Sanctum::actingAs($this->otherBuyer);
        $this->putJson("/api/buyer/addresses/{$this->address->id}", $payload)->assertNotFound();
        $this->deleteJson("/api/buyer/addresses/{$this->address->id}")->assertNotFound();

        Sanctum::actingAs($this->buyer);
        $this->postJson('/api/buyer/addresses', $payload)->assertCreated()->assertJsonPath('data.city_name', 'Kota Bandung');
        $this->assertSame(1, Address::where('user_id', $this->buyer->id)->where('is_default', true)->count());
        $this->assertFalse((bool) $this->address->fresh()->is_default);
    }

    public function test_order_menghitung_harga_dan_ongkir_di_server_serta_mengurangi_stok(): void
    {
        Sanctum::actingAs($this->buyer);

        $response = $this->postJson('/api/buyer/orders', $this->orderPayload(quantity: 2) + ['shipping_cost' => 1])
            ->assertCreated()
            ->assertJsonPath('data.payment_mode', 'sandbox')
            ->assertJsonPath('data.status', 'pending');

        // App membaca total & ongkir respons ini sebagai int.
        $this->assertIsInt($response->json('data.grand_total'));
        $this->assertIsInt($response->json('data.shipping_price'));

        $order = Order::findOrFail($response->json('data.id'));
        $this->assertSame(378000, (int) $order->total_price);
        $this->assertGreaterThan(9000, (int) $order->shipping_price);
        $this->assertSame((int) $order->total_price + (int) $order->shipping_price, (int) $order->grand_total);
        $this->assertSame('JNE REG', $order->shipping_service);
        $this->assertNotEmpty($order->payment_va_number);
        $this->assertSame(3, $this->product->fresh()->stock);
    }

    public function test_order_menolak_stok_kurang_produk_toko_lain_dan_alamat_orang_lain(): void
    {
        Sanctum::actingAs($this->buyer);
        $this->postJson('/api/buyer/orders', $this->orderPayload(quantity: 6))->assertUnprocessable();
        $this->postJson('/api/buyer/orders', ['seller_id' => $this->otherSeller->id] + $this->orderPayload())->assertUnprocessable();

        Sanctum::actingAs($this->otherBuyer);
        $this->postJson('/api/buyer/orders', $this->orderPayload())->assertUnprocessable()->assertJsonValidationErrors('address_id');
        $this->assertSame(5, $this->product->fresh()->stock);
    }

    public function test_simulasi_bayar_hanya_pemilik_order_dan_hanya_mode_uji(): void
    {
        $order = $this->makeOrder(Order::PENDING);

        Sanctum::actingAs($this->otherBuyer);
        $this->postJson("/api/buyer/orders/{$order->id}/simulate-payment")->assertNotFound();
        $this->getJson("/api/orders/{$order->id}")->assertNotFound();
        $this->getJson("/api/buyer/orders/{$order->id}/status")->assertNotFound();

        Sanctum::actingAs($this->buyer);
        $this->postJson("/api/buyer/orders/{$order->id}/simulate-payment")->assertOk()->assertJsonPath('status', 'paid');
        $this->assertNotNull($order->fresh()->paid_at);

        config(['midtrans.server_key' => 'SB-Mid-server-test']);
        $this->postJson("/api/buyer/orders/{$order->id}/simulate-payment")->assertForbidden();
    }

    public function test_callback_midtrans_wajib_signature_dan_nominal_cocok(): void
    {
        config(['midtrans.server_key' => 'SB-Mid-server-test']);
        $order = $this->makeOrder(Order::PENDING);
        $gross = number_format((int) $order->grand_total, 2, '.', '');

        $this->postJson('/api/midtrans/callback', $this->callbackPayload($order, 'settlement', $gross, 'salah'))->assertForbidden();
        $this->postJson('/api/midtrans/callback', $this->callbackPayload($order, 'settlement', '1000.00'))->assertStatus(422);
        $this->assertSame(Order::PENDING, $order->fresh()->status);

        $this->postJson('/api/midtrans/callback', $this->callbackPayload($order, 'settlement', $gross))->assertOk();
        $this->assertSame(Order::PAID, $order->fresh()->status);

        // Callback kedaluwarsa yang datang belakangan tidak membatalkan order yang sudah lunas.
        $this->postJson('/api/midtrans/callback', $this->callbackPayload($order, 'expire', $gross, statusCode: '407'))->assertOk();
        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    public function test_order_kedaluwarsa_mengembalikan_stok(): void
    {
        $order = $this->makeOrder(Order::PENDING, quantity: 2);
        $this->product->decrement('stock', 2);
        $order->forceFill(['created_at' => now()->subHours(25)])->save();

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(Order::EXPIRED, $order->fresh()->status);
        $this->assertSame(5, $this->product->fresh()->stock);
    }

    public function test_resi_hanya_oleh_penjual_pemilik_untuk_order_lunas_lalu_bisa_dilacak(): void
    {
        $pending = $this->makeOrder(Order::PENDING);
        $paid = $this->makeOrder(Order::PAID);

        Sanctum::actingAs($this->otherSeller);
        $this->putJson("/api/seller/orders/{$paid->id}/update-resi", ['shipping_number' => 'JNE12345678'])->assertNotFound();

        Sanctum::actingAs($this->seller);
        $this->putJson("/api/seller/orders/{$pending->id}/update-resi", ['shipping_number' => 'JNE12345678'])->assertUnprocessable();
        $this->putJson("/api/seller/orders/{$paid->id}/update-resi", ['shipping_number' => 'jne12345678'])
            ->assertOk()->assertJsonPath('data.status', 'shipping');

        Sanctum::actingAs($this->buyer);
        $this->postJson('/api/shipping/waybill', ['waybill' => 'JNE12345678', 'courier' => 'jne'])
            ->assertOk()
            ->assertJsonPath('rajaongkir.result.delivered', false)
            ->assertJsonPath('rajaongkir.result.manifest.0.manifest_description', 'Paket diterima di agen JNE');

        $this->travel(3)->days();
        $this->postJson('/api/shipping/waybill', ['waybill' => 'JNE12345678'])->assertJsonPath('rajaongkir.result.delivered', true);

        Sanctum::actingAs($this->otherBuyer);
        $this->postJson('/api/shipping/waybill', ['waybill' => 'JNE12345678'])->assertNotFound();
    }

    public function test_wilayah_publik_dan_ongkir_berbentuk_rajaongkir(): void
    {
        $this->getJson('/api/shipping/province')->assertOk()->assertJsonPath('rajaongkir.status.code', 200);
        // App mem-parse `query` sebagai objek; `[]` dulu membuat daftar provinsi tidak pernah tampil.
        $this->assertStringContainsString('"query":{}', $this->getJson('/api/shipping/province')->getContent());
        $this->getJson('/api/shipping/city?province=10')->assertOk()->assertJsonFragment(['city_name' => 'Surakarta (Solo)']);
        $this->postJson('/api/shipping/cost', [])->assertUnauthorized();

        Sanctum::actingAs($this->buyer);
        $costs = $this->postJson('/api/shipping/cost', [
            'origin' => $this->seller->district, 'destination' => $this->address->district, 'courier' => 'jne', 'weight' => 1000,
        ])->assertOk()->json('rajaongkir.results.0.costs');

        $this->assertSame(['OKE', 'REG', 'YES'], array_column($costs, 'service'));
        $this->assertLessThan($costs[1]['cost'][0]['value'], $costs[0]['cost'][0]['value']);
    }

    public function test_token_live_butuh_konfigurasi_dan_publisher_hanya_toko_sendiri(): void
    {
        Sanctum::actingAs($this->seller);
        $this->postJson('/api/agora/token', ['channelName' => 'BatikLestari', 'uid' => 1, 'role' => 'RolePublisher'])->assertStatus(503);

        config(['laravel-agora.agora.app_id' => str_repeat('a', 32), 'laravel-agora.agora.app_certificate' => str_repeat('b', 32)]);
        $this->postJson('/api/agora/token', ['channelName' => 'BatikLestari', 'uid' => 1, 'role' => 'RolePublisher'])->assertOk();
        $this->postJson('/api/agora/token', ['channelName' => 'TokoLain', 'uid' => 1, 'role' => 'RolePublisher'])->assertForbidden();

        Sanctum::actingAs($this->buyer);
        $this->postJson('/api/agora/token', ['channelName' => 'BatikLestari', 'uid' => 7, 'role' => 'RoleSubscriber'])->assertOk();
    }

    public function test_ringkasan_toko_menghitung_order_lunas_dan_dikirim_saja(): void
    {
        $this->makeOrder(Order::PAID, quantity: 2);
        $this->makeOrder(Order::SHIPPING);
        $this->makeOrder(Order::PENDING);
        $this->makeOrder(Order::EXPIRED);

        Sanctum::actingAs($this->seller);
        $this->getJson('/api/seller/summary')->assertOk()
            ->assertJsonPath('data.total_products', 1)
            ->assertJsonPath('data.total_orders', 2)
            ->assertJsonPath('data.items_sold', 3)
            ->assertJsonPath('data.revenue', 189000 * 3)
            ->assertJsonPath('data.to_ship', 1)
            ->assertJsonPath('data.awaiting_payment', 1);
    }

    public function test_galat_api_tetap_json_walau_tanpa_header_accept(): void
    {
        $this->get('/api/buyer/stores')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
        $this->post('/api/login', [])->assertUnprocessable()->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_daftar_toko_tidak_membocorkan_token_fcm(): void
    {
        $this->seller->update(['fcm_token' => 'rahasia-fcm']);
        Sanctum::actingAs($this->buyer);

        $this->getJson('/api/buyer/stores')->assertOk()->assertDontSee('rahasia-fcm')->assertJsonFragment(['city_name' => 'Kota Surakarta (Solo)']);
    }

    private function makeSeller(string $name, int $cityId, string $district): User
    {
        return User::factory()->create([
            'name' => $name, 'roles' => 'seller', 'city' => $cityId, 'country' => 'Indonesia',
            'district' => RegionSeeder::subdistrictId($cityId, $district),
        ]);
    }

    private function orderPayload(int $quantity = 1): array
    {
        return [
            'address_id' => $this->address->id,
            'seller_id' => $this->seller->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => $quantity]],
            'shipping_service' => 'JNE REG',
            'bank_va_name' => 'bca',
        ];
    }

    private function makeOrder(string $status, int $quantity = 1): Order
    {
        $order = Order::create([
            'user_id' => $this->buyer->id, 'address_id' => $this->address->id, 'seller_id' => $this->seller->id,
            'total_price' => 189000 * $quantity, 'shipping_price' => 20000, 'grand_total' => 189000 * $quantity + 20000,
            'status' => $status, 'payment_va_name' => 'bca', 'shipping_service' => 'JNE REG', 'shipping_courier' => 'jne',
            'transaction_number' => 'TRX-TEST-'.uniqid(),
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $this->product->id, 'price' => 189000, 'quantity' => $quantity]);

        return $order;
    }

    private function callbackPayload(Order $order, string $status, string $gross, ?string $signature = null, string $statusCode = '200'): array
    {
        return [
            'order_id' => $order->transaction_number,
            'status_code' => $statusCode,
            'gross_amount' => $gross,
            'transaction_status' => $status,
            'signature_key' => $signature ?? hash('sha512', $order->transaction_number.$statusCode.$gross.'SB-Mid-server-test'),
        ];
    }
}
