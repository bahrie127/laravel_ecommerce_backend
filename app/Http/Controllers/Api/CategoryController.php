<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $category = Category::create([
            'seller_id' => $request->user()->id,
            'name' => $data['name'],
            // Kolom lama NOT NULL; deskripsi opsional di app.
            'description' => $data['description'] ?? '',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Category created',
            'data' => $category,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $categories = Category::where('seller_id', $request->user()->id)->orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Categories',
            'data' => $categories,
        ]);
    }
}
