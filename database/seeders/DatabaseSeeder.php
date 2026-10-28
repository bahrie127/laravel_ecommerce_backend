<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Shipping\ShippingSimulator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Demo marketplace: 4 toko, 4 pembeli, 24 produk berfoto, ±45 order 30 hari terakhir (relatif hari ini).
 * Semua akun bersandi `password`. Foto dari Unsplash (lisensi Unsplash) di `database/seeders/images`.
 */
class DatabaseSeeder extends Seeder
{
    private const STORES = [
        [
            'email' => 'batiklestari@pasarlive.test', 'name' => 'Batik Lestari Solo', 'phone' => '081227380011',
            'address' => 'Jl. Dr. Rajiman No. 214, Laweyan', 'city' => 445, 'district' => 'Laweyan', 'photo' => 'stores/toko-fesyen.jpg',
            'categories' => [
                'Pakaian' => [['Kemeja Batik Lengan Pendek', 189000, 24, 'kemeja-batik', 'Kemeja batik katun primisima, motif kombinasi khas Solo. Tersedia ukuran M, L, XL.']],
                'Kain' => [
                    ['Kain Tenun Ikat 2 Meter', 325000, 8, 'dress-tenun', 'Kain tenun ikat pewarna alami, panjang 2 m lebar 1,1 m. Cocok untuk bawahan atau dijahit menjadi outer.'],
                    ['Kain Batik Tulis Motif Parang', 450000, 6, 'kain-batik', 'Batik tulis tangan pengrajin Laweyan, katun prima, 2,25 m. Setiap lembar dibuat satu per satu.'],
                ],
                'Aksesoris' => [
                    ['Tas Anyaman Rotan Bulat', 159000, 15, 'tas-anyaman', 'Tas rotan anyaman tangan diameter 20 cm, tali kulit sintetis, bagian dalam berlapis kain.'],
                    ['Sandal Kulit Pria', 175000, 20, 'sandal-kulit', 'Sandal kulit sapi asli dengan sol karet anti selip. Ukuran 39–44.'],
                    ['Topi Pantai Lebar', 79000, 30, 'topi-bucket', 'Topi lebar bahan anyaman ringan, nyaman untuk jalan-jalan dan ke pantai.'],
                ],
            ],
        ],
        [
            'email' => 'gadgetnusantara@pasarlive.test', 'name' => 'Gadget Nusantara', 'phone' => '081310045522',
            'address' => 'Ruko Taman Palem Blok C2 No. 9', 'city' => 151, 'district' => 'Grogol Petamburan', 'photo' => 'products/keyboard-mekanik.jpg',
            'live' => true,
            'categories' => [
                'Audio' => [
                    ['Earbuds TWS Bluetooth 5.3', 299000, 40, 'earbuds-tws', 'Earbuds nirkabel dengan ENC untuk telepon, baterai total 30 jam, casing USB-C.'],
                    ['Speaker Bluetooth Portabel', 349000, 18, 'speaker-bluetooth', 'Speaker 10 W tahan percikan air (IPX5), baterai hingga 12 jam.'],
                ],
                'Wearable' => [['Smartwatch Layar AMOLED', 749000, 12, 'smartwatch', 'Layar AMOLED 1,4", pemantau detak jantung & tidur, notifikasi WhatsApp, baterai 7 hari.']],
                'Aksesoris Komputer' => [
                    ['Keyboard Mekanik 75% Hot-swap', 549000, 10, 'keyboard-mekanik', 'Keyboard mekanik 75%, switch hot-swap, koneksi kabel & Bluetooth, lampu latar putih.'],
                    ['Mouse Wireless Senyap', 129000, 50, 'mouse-wireless', 'Mouse 2,4 GHz dengan klik senyap, DPI 800–1600, baterai AA tahan hingga 12 bulan.'],
                ],
                'Daya' => [['Powerbank 10.000 mAh 20W', 189000, 35, 'powerbank', 'Powerbank 10.000 mAh fast charging 20 W, dua port (USB-C & USB-A).']],
            ],
        ],
        [
            'email' => 'dapurrumah@pasarlive.test', 'name' => 'Dapur Rumah Kita', 'phone' => '082118876543',
            'address' => 'Jl. Dago No. 88', 'city' => 23, 'district' => 'Coblong', 'photo' => 'stores/toko-dapur.jpg',
            'categories' => [
                'Peralatan Minum' => [
                    ['Mug Keramik Handmade', 69000, 40, 'mug-keramik', 'Mug keramik buatan tangan 300 ml, aman untuk microwave dan mesin cuci piring.'],
                    ['French Press 600 ml', 149000, 15, 'french-press', 'French press kaca borosilikat 600 ml dengan saringan stainless tiga lapis.'],
                    ['Tumbler Stainless 500 ml', 119000, 30, 'tumbler', 'Tumbler dinding ganda, minuman dingin tahan 24 jam dan panas 12 jam.'],
                ],
                'Peralatan Masak' => [
                    ['Panci Enamel 20 cm', 289000, 9, 'panci-enamel', 'Panci enamel tebal 2,5 L, cocok untuk kompor gas, listrik, dan induksi.'],
                    ['Talenan Kayu Jati', 99000, 22, 'talenan-kayu', 'Talenan kayu jati solid 35 x 25 cm, dilapisi minyak food grade.'],
                ],
                'Dekorasi' => [['Lampu Meja Kayu', 219000, 7, 'lampu-meja', 'Lampu meja kaki kayu dengan kap kain, fitting E27 (bohlam tidak termasuk).']],
            ],
        ],
        [
            'email' => 'kopikintamani@pasarlive.test', 'name' => 'Kopi Kebun Kintamani', 'phone' => '081338907711',
            'address' => 'Br. Belantih, Desa Belantih', 'city' => 32, 'district' => 'Kintamani', 'photo' => 'stores/toko-kopi.jpg',
            'categories' => [
                'Kopi' => [
                    ['Biji Kopi Arabika Kintamani 250 g', 95000, 60, 'biji-kopi', 'Arabika Kintamani proses natural, sangrai medium. Catatan rasa jeruk, gula aren, dan cokelat.'],
                    ['Kopi Drip Bag isi 10', 65000, 80, 'kopi-drip', 'Sepuluh kantong drip bag 12 g, tinggal seduh dengan 150 ml air panas.'],
                ],
                'Teh & Madu' => [
                    ['Madu Hutan 500 ml', 125000, 25, 'madu-hutan', 'Madu hutan murni dari lebah liar, tanpa campuran gula.'],
                    ['Teh Hijau Daun 100 g', 55000, 40, 'teh-hijau', 'Daun teh hijau utuh dari kebun dataran tinggi, diseduh 2–3 kali.'],
                ],
                'Camilan' => [
                    ['Cokelat Batang 70% Bali', 45000, 50, 'cokelat-batang', 'Cokelat hitam 70% dari kakao Bali, 80 g, tanpa pengawet.'],
                    ['Keripik Singkong Pedas', 25000, 45, 'keripik', 'Keripik singkong renyah bumbu pedas manis, kemasan 200 g.'],
                ],
            ],
        ],
    ];

    private const BUYERS = [
        ['andi@pasarlive.test', 'Andi Saputra', '081298765432', 'Jl. Tebet Barat Dalam II No. 17', 153, 'Tebet', '12810'],
        ['sinta@pasarlive.test', 'Sinta Maharani', '081322114455', 'Jl. Sukajadi No. 140', 23, 'Sukajadi', '40162'],
        ['rizky@pasarlive.test', 'Rizky Pratama', '085733219900', 'Jl. Raya Gubeng No. 51', 444, 'Gubeng', '60281'],
        ['dewi@pasarlive.test', 'Dewi Lestari', '087839001122', 'Jl. Kaliurang Km 5 No. 12', 419, 'Depok', '55281'],
    ];

    public function run(): void
    {
        mt_srand(2024);
        $this->call(RegionSeeder::class);
        $this->copyImages();

        $stores = collect(self::STORES)->map(fn (array $store) => $this->createStore($store));
        $buyers = collect(self::BUYERS)->map(fn (array $buyer) => $this->createBuyer($buyer));

        $this->createOrders($stores->all(), $buyers->all());
    }

    private function copyImages(): void
    {
        foreach (['products' => 'assets/product', 'stores' => 'assets/user'] as $source => $target) {
            Storage::disk('public')->makeDirectory($target);
            foreach (File::files(database_path("seeders/images/{$source}")) as $file) {
                Storage::disk('public')->put("{$target}/{$file->getFilename()}", File::get($file->getPathname()));
            }
        }
    }

    private function createStore(array $store): User
    {
        $city = $this->city($store['city']);
        $photo = str_starts_with($store['photo'], 'stores/')
            ? 'assets/user/'.basename($store['photo'])
            : 'assets/product/'.basename($store['photo']);

        $seller = User::create([
            'name' => $store['name'],
            'email' => $store['email'],
            'password' => Hash::make('password'),
            'phone' => $store['phone'],
            'address' => $store['address'],
            'country' => 'Indonesia',
            'province' => $city['province_id'],
            'city' => $store['city'],
            'district' => RegionSeeder::subdistrictId($store['city'], $store['district']),
            'postal_code' => $city['postal_code'],
            'roles' => User::SELLER,
            'photo' => $photo,
            'is_livestreaming' => $store['live'] ?? false,
        ]);

        foreach ($store['categories'] as $categoryName => $products) {
            $category = Category::create(['seller_id' => $seller->id, 'name' => $categoryName, 'description' => '']);
            foreach ($products as [$name, $price, $stock, $image, $description]) {
                Product::create([
                    'seller_id' => $seller->id,
                    'category_id' => $category->id,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'stock' => $stock,
                    'image' => "assets/product/{$image}.jpg",
                ]);
            }
        }

        return $seller;
    }

    private function createBuyer(array $buyer): User
    {
        [$email, $name, $phone, $street, $cityId, $district, $postalCode] = $buyer;
        $city = $this->city($cityId);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'phone' => $phone,
            'roles' => User::BUYER,
        ]);

        Address::create([
            'user_id' => $user->id,
            'address' => "{$street} (HP {$phone})",
            'country' => 'Indonesia',
            'province' => $city['province_id'],
            'city' => $cityId,
            'district' => RegionSeeder::subdistrictId($cityId, $district),
            'postal_code' => $postalCode,
            'is_default' => true,
        ]);

        return $user;
    }

    /**
     * @param  list<User>  $stores
     * @param  list<User>  $buyers
     */
    private function createOrders(array $stores, array $buyers): void
    {
        $shipping = app(ShippingSimulator::class);
        $banks = ['bca', 'bri', 'bni', 'cimb'];
        $sequence = 0;

        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            $ordersToday = $daysAgo === 0 ? 3 : mt_rand(1, 2);
            for ($n = 0; $n < $ordersToday; $n++) {
                $buyer = $buyers[mt_rand(0, count($buyers) - 1)];
                $seller = $stores[mt_rand(0, count($stores) - 1)];
                $address = $buyer->addresses()->first();
                $createdAt = Carbon::today()->subDays($daysAgo)->setTime(mt_rand(8, 21), mt_rand(0, 59));
                if ($createdAt->isFuture()) {
                    $createdAt = now()->subMinutes(mt_rand(20, 90));
                }

                $status = match (true) {
                    $daysAgo === 0 => [Order::PENDING, Order::PAID, Order::PAID][$n % 3],
                    $daysAgo === 1 => Order::PAID,
                    mt_rand(1, 10) === 1 => Order::EXPIRED,
                    mt_rand(1, 14) === 1 => Order::CANCELLED,
                    default => Order::SHIPPING,
                };

                $service = ['REG', 'REG', 'YES', 'OKE'][mt_rand(0, 3)];
                $shippingPrice = $shipping->priceFor($seller->district, $address->district, 'jne', $service)
                    ?? $shipping->priceFor($seller->district, $address->district, 'jne', 'REG');
                $products = Product::where('seller_id', $seller->id)->inRandomOrder()->limit(mt_rand(1, 3))->get();
                $total = 0;
                $lines = [];
                foreach ($products as $product) {
                    $quantity = mt_rand(1, 2);
                    $total += (int) $product->price * $quantity;
                    $lines[] = [$product, $quantity];
                }

                $sequence++;
                $paidAt = in_array($status, [Order::PAID, Order::SHIPPING], true) ? $createdAt->copy()->addMinutes(mt_rand(5, 120)) : null;
                $shippedAt = $status === Order::SHIPPING ? $paidAt->copy()->addHours(mt_rand(3, 20)) : null;

                $order = new Order([
                    'user_id' => $buyer->id,
                    'address_id' => $address->id,
                    'seller_id' => $seller->id,
                    'total_price' => $total,
                    'shipping_price' => $shippingPrice,
                    'grand_total' => $total + $shippingPrice,
                    'status' => $status,
                    'payment_va_name' => $banks[$sequence % 4],
                    'payment_va_number' => '8808'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT).mt_rand(1000, 9999),
                    'shipping_service' => "JNE {$service}",
                    'shipping_courier' => 'jne',
                    'shipping_number' => $shippedAt ? 'JNE'.$createdAt->format('ymd').str_pad((string) mt_rand(100000, 999999), 6, '0') : null,
                    'transaction_number' => 'TRX-'.$createdAt->format('ymdHis').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                    'paid_at' => $paidAt,
                    'shipped_at' => $shippedAt,
                ]);
                $order->created_at = $createdAt;
                $order->updated_at = $shippedAt ?? $paidAt ?? $createdAt;
                $order->save();

                foreach ($lines as [$product, $quantity]) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'price' => $product->price,
                        'quantity' => $quantity,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
            }
        }
    }

    /**
     * @return array{province_id: int, postal_code: string}
     */
    private function city(int $cityId): array
    {
        foreach (RegionSeeder::REGIONS as $provinceId => $province) {
            if (isset($province['cities'][$cityId])) {
                return ['province_id' => $provinceId, 'postal_code' => $province['cities'][$cityId][2]];
            }
        }

        throw new \InvalidArgumentException("Kota {$cityId} tidak ada.");
    }
}
