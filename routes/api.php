<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AgoraController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CallbackController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\StoreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Publik
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/seller/register', [AuthController::class, 'registerSeller']);
    Route::post('/buyer/register', [AuthController::class, 'registerBuyer']);
});
Route::post('/midtrans/callback', [CallbackController::class, 'callback']);

// Wilayah dipakai form daftar penjual sebelum login.
Route::prefix('shipping')->middleware('throttle:60,1')->group(function () {
    Route::get('/province', [ShippingController::class, 'provinces']);
    Route::get('/city', [ShippingController::class, 'cities']);
    Route::get('/subdistrict', [ShippingController::class, 'subdistricts']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/update-fcm-token', [AuthController::class, 'updateFcmToken']);

    Route::post('/shipping/cost', [ShippingController::class, 'cost']);
    Route::post('/shipping/waybill', [ShippingController::class, 'waybill']);
    Route::get('/orders/{order}', [OrderController::class, 'getOrderById']);
    Route::post('/agora/token', [AgoraController::class, 'getToken']);

    // Penjual
    Route::prefix('seller')->middleware('role:seller')->group(function () {
        Route::post('/category', [CategoryController::class, 'store']);
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::apiResource('/products', ProductController::class)->except('show');
        Route::post('/products/{id}', [ProductController::class, 'update']);
        Route::get('/orders', [OrderController::class, 'historyOrderSeller']);
        Route::put('/orders/{order}/update-resi', [OrderController::class, 'updateShippingNumber']);
        Route::post('/livestreaming', [StoreController::class, 'setLivestreaming']);
        Route::get('/summary', [StoreController::class, 'summary']);
    });

    // Pembeli
    Route::prefix('buyer')->middleware('role:user')->group(function () {
        Route::apiResource('/addresses', AddressController::class)->except('show');
        Route::post('/orders', [OrderController::class, 'createOrder']);
        Route::get('/orders/{order}/status', [OrderController::class, 'checkOrderStatus']);
        Route::post('/orders/{order}/simulate-payment', [OrderController::class, 'simulatePayment']);
        Route::get('/histories', [OrderController::class, 'historyOrderBuyer']);
        Route::get('/stores', [StoreController::class, 'index']);
        Route::get('/stores/livestreaming', [StoreController::class, 'livestreaming']);
        Route::get('/stores/{id}/products', [StoreController::class, 'productByStore'])->whereNumber('id');
    });
});
