<?php

namespace App\Services\Shipping;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ongkir & pelacakan simulasi berbentuk respons RajaOngkir Pro (app membaca model RajaOngkir apa adanya).
 * Dipakai selama belum ada kontrak kurir/aggregator (Komerce, Biteship); tarif dihitung dari jarak antarkota.
 */
class ShippingSimulator
{
    /**
     * @var array<string, array{name: string, services: array<string, array{description: string, factor: float, etd: array{0: string, 1: string, 2: string}}>}>
     */
    public const COURIERS = [
        'jne' => ['name' => 'Jalur Nugraha Ekakurir (JNE)', 'services' => [
            'OKE' => ['description' => 'Ongkos Kirim Ekonomis', 'factor' => 0.8, 'etd' => ['2-3', '3-5', '4-6']],
            'REG' => ['description' => 'Layanan Reguler', 'factor' => 1.0, 'etd' => ['1-2', '2-3', '3-4']],
            'YES' => ['description' => 'Yakin Esok Sampai', 'factor' => 1.8, 'etd' => ['1', '1', '1-2']],
        ]],
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function subdistrict(string|int|null $id): ?array
    {
        $row = DB::table('subdistricts')
            ->join('cities', 'cities.id', '=', 'subdistricts.city_id')
            ->join('provinces', 'provinces.id', '=', 'cities.province_id')
            ->where('subdistricts.id', (int) $id)
            ->first([
                'subdistricts.id as subdistrict_id', 'subdistricts.name as subdistrict_name',
                'cities.id as city_id', 'cities.name as city', 'cities.type', 'cities.latitude', 'cities.longitude',
                'provinces.id as province_id', 'provinces.name as province',
            ]);

        return $row ? (array) $row : null;
    }

    public function distanceKm(array $origin, array $destination): float
    {
        $lat1 = deg2rad((float) $origin['latitude']);
        $lat2 = deg2rad((float) $destination['latitude']);
        $deltaLat = $lat2 - $lat1;
        $deltaLng = deg2rad((float) $destination['longitude'] - (float) $origin['longitude']);
        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Tarif per layanan untuk berat (gram). Kembalikan [] bila asal/tujuan/kurir tidak dikenal.
     *
     * @return list<array{service: string, description: string, cost: list<array{value: int, etd: string, note: string}>}>
     */
    public function costs(string|int|null $originId, string|int|null $destinationId, string $courier, int $weightGrams = 1000): array
    {
        $origin = $this->subdistrict($originId);
        $destination = $this->subdistrict($destinationId);
        $courierData = self::COURIERS[strtolower($courier)] ?? null;

        if (! $origin || ! $destination || ! $courierData) {
            return [];
        }

        $km = $this->distanceKm($origin, $destination);
        $kilograms = max(1, (int) ceil($weightGrams / 1000));
        $base = $origin['city_id'] === $destination['city_id'] ? 9000 : 11000 + $km * 24;
        $band = $km < 150 ? 0 : ($km < 600 ? 1 : 2);

        $results = [];
        foreach ($courierData['services'] as $service => $config) {
            // OKE tidak melayani pengiriman dalam kota.
            if ($service === 'OKE' && $origin['city_id'] === $destination['city_id']) {
                continue;
            }

            $price = (int) (ceil($base * $config['factor'] * $kilograms / 500) * 500);
            $results[] = [
                'service' => $service,
                'description' => $config['description'],
                'cost' => [['value' => $price, 'etd' => $config['etd'][$band], 'note' => '']],
            ];
        }

        return $results;
    }

    /**
     * Harga satu layanan (dipakai saat membuat order agar ongkir tidak bisa diatur dari app).
     */
    public function priceFor(string|int|null $originId, string|int|null $destinationId, string $courier, string $service, int $weightGrams = 1000): ?int
    {
        foreach ($this->costs($originId, $destinationId, $courier, $weightGrams) as $row) {
            if (strcasecmp($row['service'], $service) === 0) {
                return $row['cost'][0]['value'];
            }
        }

        return null;
    }

    /**
     * Riwayat perjalanan paket: manifest muncul bertahap sesuai waktu sejak resi diinput.
     *
     * @return array<string, mixed>
     */
    public function tracking(Order $order, ?Carbon $now = null): array
    {
        $now ??= now();
        $order->loadMissing('address.user', 'seller');

        $origin = $this->subdistrict($order->seller?->district);
        $destination = $this->subdistrict($order->address?->district);
        $originCity = strtoupper($origin['city'] ?? 'GUDANG PENJUAL');
        $destinationCity = strtoupper($destination['city'] ?? 'KOTA TUJUAN');
        $receiver = $order->address?->user?->name ?? $order->user?->name ?? 'Penerima';
        $shippedAt = Carbon::parse($order->shipped_at ?? $order->updated_at);
        $transitHours = $origin && $destination ? max(6, (int) round($this->distanceKm($origin, $destination) / 30)) : 24;

        $steps = [
            [0, 'Paket diterima di agen JNE', $originCity],
            [3, 'Paket diproses di gudang sortir', $originCity],
            [6, 'Paket diberangkatkan menuju kota tujuan', $originCity],
            [6 + $transitHours, 'Paket tiba di gudang kota tujuan', $destinationCity],
            [9 + $transitHours, 'Paket dibawa kurir menuju alamat penerima', $destinationCity],
            [12 + $transitHours, 'Paket diterima oleh '.strtoupper($receiver), $destinationCity],
        ];

        $manifest = [];
        foreach ($steps as $index => [$hours, $description, $city]) {
            $at = $shippedAt->copy()->addHours($hours);
            if ($at->greaterThan($now)) {
                break;
            }
            $manifest[] = [
                'manifest_code' => (string) ($index + 1),
                'manifest_description' => $description,
                'manifest_date' => $at->toDateString(),
                'manifest_time' => $at->format('H:i'),
                'city_name' => $city,
            ];
        }

        $delivered = count($manifest) === count($steps);
        $deliveredAt = $shippedAt->copy()->addHours(12 + $transitHours);
        $courier = strtolower($order->shipping_courier ?: 'jne');

        return [
            'query' => ['waybill' => $order->shipping_number, 'courier' => $courier],
            'status' => ['code' => 200, 'description' => 'OK'],
            'result' => [
                'delivered' => $delivered,
                'summary' => [
                    'courier_code' => $courier,
                    'courier_name' => self::COURIERS[$courier]['name'] ?? strtoupper($courier),
                    'waybill_number' => $order->shipping_number,
                    'service_code' => $this->serviceCode($order->shipping_service),
                    'waybill_date' => $shippedAt->toDateString(),
                    'shipper_name' => strtoupper($order->seller?->name ?? 'PENJUAL'),
                    'receiver_name' => strtoupper($receiver),
                    'origin' => $originCity,
                    'destination' => $destinationCity,
                    'status' => $delivered ? 'DELIVERED' : 'ON PROCESS',
                ],
                'details' => [
                    'waybill_number' => $order->shipping_number,
                    'waybill_date' => $shippedAt->toDateString(),
                    'waybill_time' => $shippedAt->format('H:i'),
                    'weight' => '1',
                    'origin' => $originCity,
                    'destination' => $destinationCity,
                    'shippper_name' => strtoupper($order->seller?->name ?? 'PENJUAL'),
                    'shipper_address1' => $order->seller?->address,
                    'shipper_address2' => null,
                    'shipper_address3' => null,
                    'shipper_city' => $originCity,
                    'receiver_name' => strtoupper($receiver),
                    'receiver_address1' => $order->address?->address,
                    'receiver_address2' => null,
                    'receiver_address3' => null,
                    'receiver_city' => $destinationCity,
                ],
                'delivery_status' => [
                    'status' => $delivered ? 'DELIVERED' : 'ON PROCESS',
                    'pod_receiver' => $delivered ? strtoupper($receiver) : null,
                    'pod_date' => $delivered ? $deliveredAt->toDateString() : null,
                    'pod_time' => $delivered ? $deliveredAt->format('H:i') : null,
                ],
                'manifest' => array_reverse($manifest),
            ],
        ];
    }

    private function serviceCode(?string $shippingService): string
    {
        $parts = preg_split('/\s+/', trim((string) $shippingService));

        return strtoupper(end($parts) ?: 'REG');
    }
}
