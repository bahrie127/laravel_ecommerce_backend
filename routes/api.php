<?php

use App\Http\Controllers\Api\CallbackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//register seller
Route::post('/seller/register', [App\Http\Controllers\Api\AuthController::class, 'registerSeller']);

//login
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);

//logout
Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout'])->middleware('auth:sanctum');

//register customer
Route::post('/buyer/register', [App\Http\Controllers\Api\AuthController::class, 'registerBuyer']);

//store category
Route::post('/seller/category', [App\Http\Controllers\Api\CategoryController::class, 'store'])->middleware('auth:sanctum');

//get all categories
Route::get('/seller/categories', [App\Http\Controllers\Api\CategoryController::class, 'index'])->middleware('auth:sanctum');

//product
Route::apiResource('/seller/products', App\Http\Controllers\Api\ProductController::class)->middleware('auth:sanctum');

//update product
Route::post('/seller/products/{id}', [App\Http\Controllers\Api\ProductController::class, 'update'])->middleware('auth:sanctum');

//address
Route::apiResource('/buyer/addresses', App\Http\Controllers\Api\AddressController::class)->middleware('auth:sanctum');

//order
Route::post('/buyer/orders', [App\Http\Controllers\Api\OrderController::class, 'createOrder'])->middleware('auth:sanctum');

//store
Route::get('/buyer/stores', [App\Http\Controllers\Api\StoreController::class, 'index'])->middleware('auth:sanctum');

//product by store
Route::get('/buyer/stores/{id}/products', [App\Http\Controllers\Api\StoreController::class, 'productByStore'])->middleware('auth:sanctum');

//update resi
Route::put('/seller/orders/{id}/update-resi', [App\Http\Controllers\Api\OrderController::class, 'updateShippingNumber'])->middleware('auth:sanctum');

//history order buyer
Route::get('/buyer/histories', [App\Http\Controllers\Api\OrderController::class, 'historyOrderBuyer'])->middleware('auth:sanctum');

//history order seller
Route::get('/seller/orders', [App\Http\Controllers\Api\OrderController::class, 'historyOrderSeller'])->middleware('auth:sanctum');

//get store is livestreaming
Route::get('/buyer/stores/livestreaming', [App\Http\Controllers\Api\StoreController::class, 'livestreaming'])->middleware('auth:sanctum');

//callback midtrans
Route::post('/midtrans/callback', [CallbackController::class, 'callback']);

//check order status
Route::get('/buyer/orders/{id}/status', [App\Http\Controllers\Api\OrderController::class, 'checkOrderStatus'])->middleware('auth:sanctum');

//get order by id
Route::get('/orders/{id}', [App\Http\Controllers\Api\OrderController::class, 'getOrderById'])->middleware('auth:sanctum');

//get agora token
Route::post('/agora/token', [App\Http\Controllers\Api\AgoraController::class, 'getToken'])->middleware('auth:sanctum');

//set is livestreaming
Route::post('/seller/livestreaming', [App\Http\Controllers\Api\StoreController::class, 'setLivestreaming'])->middleware('auth:sanctum');

//update fcm token
Route::put('/update-fcm-token', [App\Http\Controllers\Api\AuthController::class, 'updateFcmToken'])->middleware('auth:sanctum');

