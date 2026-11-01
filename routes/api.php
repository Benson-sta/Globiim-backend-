<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CommerceController;
use App\Http\Controllers\Api\V1\MessagingController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\VerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
        Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
        Route::post('/verification/request', [VerificationController::class, 'request'])->middleware('throttle:3,1');
        Route::post('/verification/confirm', [VerificationController::class, 'confirm'])->middleware('throttle:5,1');
    });

    Route::get('/commerce/products', [CommerceController::class, 'products']);
    Route::get('/commerce/products/{product}', [CommerceController::class, 'product']);
    Route::get('/commerce/stores', [CommerceController::class, 'stores']);
    Route::get('/commerce/business-requests', [CommerceController::class, 'businessRequests']);
    Route::get('/commerce/stores/{slug}', [CommerceController::class, 'seller']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);

        Route::get('/messaging/conversations', [MessagingController::class, 'conversations']);
        Route::post('/messaging/conversations', [MessagingController::class, 'createConversation']);
        Route::get('/messaging/conversations/{conversation}/messages', [MessagingController::class, 'messages']);
        Route::post('/messaging/conversations/{conversation}/messages', [MessagingController::class, 'sendMessage'])->middleware('throttle:60,1');
        Route::post('/messaging/conversations/{conversation}/read', [MessagingController::class, 'markRead']);

        Route::prefix('commerce')->group(function (): void {
            Route::post('/seller/apply', [CommerceController::class, 'applyAsSeller']);
            Route::get('/seller/me', [CommerceController::class, 'myStore']);
            Route::post('/products', [CommerceController::class, 'createProduct']);
            Route::post('/products/{product}/publish', [CommerceController::class, 'publishProduct']);
            Route::get('/cart', [CommerceController::class, 'cart']);
            Route::post('/cart/items', [CommerceController::class, 'addToCart'])->middleware('throttle:30,1');
            Route::patch('/cart/items/{item}', [CommerceController::class, 'updateCartItem']);
            Route::post('/checkout', [CommerceController::class, 'checkout'])->middleware('throttle:10,1');
            Route::get('/orders', [CommerceController::class, 'orders']);
            Route::post('/orders/{order}/cancel', [CommerceController::class, 'cancelOrder']);
            Route::post('/quotes', [CommerceController::class, 'requestQuote'])->middleware('throttle:10,1');
            Route::post('/business-requests', [CommerceController::class, 'createBusinessRequest'])->middleware('throttle:10,1');
            Route::get('/business-requests/mine', [CommerceController::class, 'myBusinessRequests']);
            Route::get('/saved', [CommerceController::class, 'savedProducts']);
            Route::post('/saved/{product}', [CommerceController::class, 'saveProduct']);
            Route::delete('/saved/{product}', [CommerceController::class, 'unsaveProduct']);
            Route::post('/reviews', [CommerceController::class, 'review'])->middleware('throttle:10,1');
        });
    });
});
