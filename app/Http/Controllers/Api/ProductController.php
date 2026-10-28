<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::where('seller_id', $request->user()->id)
            ->where('is_active', true)
            ->with('category:id,name')
            ->latest()->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Products',
            'data' => $products,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request, imageRequired: true));

        $product = Product::create([
            ...collect($data)->except('image')->all(),
            'seller_id' => $request->user()->id,
            'image' => $request->file('image')->store('assets/product', 'public'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Product created',
            'data' => $product,
        ], 201);
    }

    /**
     * Dulu penjual mana pun bisa mengubah atau menghapus produk toko lain (tanpa cek pemilik).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = $this->ownedProduct($request, $id);
        $data = $request->validate($this->rules($request, imageRequired: false));

        $product->fill(collect($data)->except('image')->all());
        if ($request->hasFile('image')) {
            $product->image = $request->file('image')->store('assets/product', 'public');
        }
        $product->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Product updated',
            'data' => $product,
        ]);
    }

    /**
     * Produk yang sudah pernah dipesan hanya dinonaktifkan: menghapusnya ikut menghapus item di riwayat
     * order (FK cascade).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $product = $this->ownedProduct($request, $id);

        if ($product->orderItems()->exists()) {
            $product->update(['is_active' => false]);
        } else {
            $product->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Product deleted',
        ]);
    }

    private function ownedProduct(Request $request, int $id): Product
    {
        $product = Product::where('seller_id', $request->user()->id)->find($id);
        abort_unless($product, 404, 'Produk tidak ditemukan.');

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, bool $imageRequired): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('seller_id', $request->user()->id)],
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
            'price' => 'required|integer|min:100|max:1000000000',
            'stock' => 'required|integer|min:0|max:100000',
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
