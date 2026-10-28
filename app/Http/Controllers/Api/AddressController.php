<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->latest()->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Addresses',
            'data' => $addresses,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $user = $request->user();

        $address = DB::transaction(function () use ($data, $user) {
            // Alamat pertama otomatis jadi utama; hanya satu alamat utama per pembeli.
            $isDefault = $data['is_default'] || ! $user->addresses()->exists();
            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return Address::create([...$data, 'user_id' => $user->id, 'is_default' => $isDefault]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Address created',
            'data' => $address->fresh(),
        ], 201);
    }

    /**
     * Dulu alamat siapa pun bisa diubah/dihapus lewat ID (tanpa cek pemilik), dan ID yang tidak ada membuat 500.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $address = $this->ownedAddress($request, $id);
        $data = $request->validate($this->rules());

        DB::transaction(function () use ($request, $address, $data) {
            if ($data['is_default']) {
                $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }
            $address->update($data);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Address updated',
            'data' => $address->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $address = $this->ownedAddress($request, $id);

        if ($address->orders()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Alamat sudah dipakai di pesanan dan tidak bisa dihapus.',
            ], 409);
        }

        $address->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Address deleted',
        ]);
    }

    private function ownedAddress(Request $request, int $id): Address
    {
        $address = $request->user()->addresses()->find($id);
        abort_unless($address, 404, 'Alamat tidak ditemukan.');

        return $address;
    }

    /**
     * @return array<string, string>
     */
    private function rules(): array
    {
        return [
            'address' => 'required|string|max:500',
            'country' => 'required|string|max:60',
            'province' => 'required|integer|exists:provinces,id',
            'city' => 'required|integer|exists:cities,id',
            'district' => 'required|integer|exists:subdistricts,id',
            'postal_code' => 'required|string|max:10',
            'is_default' => 'required|boolean',
        ];
    }
}
