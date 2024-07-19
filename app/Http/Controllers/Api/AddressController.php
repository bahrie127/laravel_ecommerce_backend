<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    //index
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses;

        return response()->json([
            'status' => 'success',
            'message' => 'Addresses',
            'data' => $addresses,
        ]);
    }

    //store
    public function store(Request $request)
    {
        $request->validate([

            'address' => 'required|string',
            'country' => 'required|string',
            'province' => 'required|string',
            'city' => 'required|string',
            'district' => 'required|string',
            'postal_code' => 'required|string',
        ]);

        $address = Address::create([
            'user_id' => $request->user()->id,
            'address' => $request->address,
            'country' => $request->country,
            'province' => $request->province,
            'city' => $request->city,
            'district' => $request->district,
            'postal_code' => $request->postal_code,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Address created',
            'data' => $address,
        ], 201);
    }

    //update
    public function update(Request $request, $id)
    {
        $request->validate([
            'address' => 'required|string',
            'country' => 'required|string',
            'province' => 'required|string',
            'city' => 'required|string',
            'district' => 'required|string',
            'postal_code' => 'required|string',
        ]);

        $address = Address::find($id);
        $address->update([
            'address' => $request->address,
            'country' => $request->country,
            'province' => $request->province,
            'city' => $request->city,
            'district' => $request->district,
            'postal_code' => $request->postal_code,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Address updated',
            'data' => $address,
        ]);
    }

    //delete
    public function destroy($id)
    {
        $address = Address::find($id);
        $address->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Address deleted',
        ]);
    }
}
