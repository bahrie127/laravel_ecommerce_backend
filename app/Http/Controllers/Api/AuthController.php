<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Daftar penjual. Dulu email boleh dobel (login jadi ambigu), sandi tanpa panjang minimum, dan
     * wilayah toko bebas teks sehingga ongkir tidak bisa dihitung.
     */
    public function registerSeller(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'country' => 'required|string|max:60',
            'province' => 'required|integer|exists:provinces,id',
            'city' => 'required|integer|exists:cities,id',
            'district' => 'required|integer|exists:subdistricts,id',
            'postal_code' => 'required|string|max:10',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], ['email.unique' => 'Email sudah terdaftar.']);

        $user = User::create([
            ...collect($data)->except('photo', 'password')->all(),
            'password' => Hash::make($data['password']),
            'roles' => User::SELLER,
            'photo' => $request->file('photo')?->store('assets/user', 'public'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User registered',
            'data' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Email atau kata sandi salah.',
            ], 401);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'User logged in',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Token revoked',
        ], 200);
    }

    public function registerBuyer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8',
        ], ['email.unique' => 'Email sudah terdaftar.']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'roles' => User::BUYER,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User registered',
            'data' => $user,
        ], 201);
    }

    public function updateFcmToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => 'required|string|max:500',
        ]);

        $request->user()->update(['fcm_token' => $data['fcm_token']]);

        return response()->json([
            'status' => 'success',
            'message' => 'FCM token updated',
        ], 200);
    }
}
