<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Shipping\ShippingSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pengganti RajaOngkir Pro yang dulu dipanggil langsung dari app dengan API key tertanam. Bentuk respons
 * sama (`{"rajaongkir": {...}}`) supaya model di app tidak perlu diubah.
 */
class ShippingController extends Controller
{
    public function __construct(private ShippingSimulator $shipping) {}

    public function provinces(): JsonResponse
    {
        $results = DB::table('provinces')->orderBy('name')->get()
            ->map(fn ($row) => ['province_id' => (string) $row->id, 'province' => $row->name]);

        return $this->respond([], $results->all());
    }

    public function cities(Request $request): JsonResponse
    {
        $provinceId = (int) $request->query('province');
        $results = DB::table('cities')->join('provinces', 'provinces.id', '=', 'cities.province_id')
            ->when($provinceId, fn ($query) => $query->where('province_id', $provinceId))
            ->orderBy('cities.name')
            ->get(['cities.*', 'provinces.name as province'])
            ->map(fn ($row) => [
                'city_id' => (string) $row->id,
                'province_id' => (string) $row->province_id,
                'province' => $row->province,
                'type' => $row->type,
                'city_name' => $row->name,
                'postal_code' => $row->postal_code,
            ]);

        return $this->respond(['province' => (string) $provinceId], $results->all());
    }

    public function subdistricts(Request $request): JsonResponse
    {
        $cityId = (int) $request->query('city');
        $results = DB::table('subdistricts')
            ->join('cities', 'cities.id', '=', 'subdistricts.city_id')
            ->join('provinces', 'provinces.id', '=', 'cities.province_id')
            ->where('subdistricts.city_id', $cityId)
            ->orderBy('subdistricts.name')
            ->get(['subdistricts.*', 'cities.name as city', 'cities.type', 'provinces.id as province_id', 'provinces.name as province'])
            ->map(fn ($row) => [
                'subdistrict_id' => (string) $row->id,
                'province_id' => (string) $row->province_id,
                'province' => $row->province,
                'city_id' => (string) $row->city_id,
                'city' => $row->city,
                'type' => $row->type,
                'subdistrict_name' => $row->name,
            ]);

        return $this->respond(['city' => (string) $cityId], $results->all());
    }

    public function cost(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin' => 'required',
            'destination' => 'required',
            'courier' => 'required|string',
            'weight' => 'nullable|integer|min:1|max:30000',
        ]);

        $courier = strtolower($data['courier']);
        $costs = $this->shipping->costs($data['origin'], $data['destination'], $courier, (int) ($data['weight'] ?? 1000));
        if ($costs === []) {
            return response()->json(['rajaongkir' => ['status' => ['code' => 400, 'description' => 'Asal atau tujuan pengiriman tidak dikenali.']]], 422);
        }

        return response()->json(['rajaongkir' => [
            'query' => [
                'origin' => (string) $data['origin'], 'originType' => 'subdistrict',
                'destination' => (string) $data['destination'], 'destinationType' => 'subdistrict',
                'weight' => (int) ($data['weight'] ?? 1000), 'courier' => $courier,
            ],
            'status' => ['code' => 200, 'description' => 'OK'],
            'results' => [[
                'code' => $courier,
                'name' => ShippingSimulator::COURIERS[$courier]['name'],
                'costs' => $costs,
            ]],
        ]]);
    }

    /**
     * Lacak resi. Hanya pembeli atau penjual pesanan itu yang bisa melihat riwayatnya.
     */
    public function waybill(Request $request): JsonResponse
    {
        $data = $request->validate(['waybill' => 'required|string|max:30', 'courier' => 'nullable|string']);

        $order = Order::where('shipping_number', strtoupper($data['waybill']))->first();
        if (! $order || ! $order->isParticipant($request->user())) {
            return response()->json(['rajaongkir' => ['status' => ['code' => 400, 'description' => 'Nomor resi tidak ditemukan.']]], 404);
        }

        return response()->json(['rajaongkir' => $this->shipping->tracking($order)]);
    }

    /**
     * @param  array<string, string>  $query
     * @param  list<array<string, mixed>>  $results
     */
    private function respond(array $query, array $results): JsonResponse
    {
        return response()->json(['rajaongkir' => [
            // Selalu objek: array kosong ter-encode `[]` dan membuat parser app (Map) gagal.
            'query' => (object) $query,
            'status' => ['code' => 200, 'description' => 'OK'],
            'results' => $results,
        ]]);
    }
}
