<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CommerceController;
use App\Http\Controllers\Api\V1\CommerceExpansionController;
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
    Route::get('/commerce/local/businesses', [CommerceExpansionController::class, 'localBusinesses']);
    Route::get('/commerce/watch-shop', [CommerceExpansionController::class, 'watchAndShop']);
    Route::get('/commerce/listings', [CommerceExpansionController::class, 'listings']);
    Route::get('/commerce/listings/{slug}', [CommerceExpansionController::class, 'listing']);
    Route::get('/commerce/partner-network', [CommerceExpansionController::class, 'partnerDirectory']);

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

        Route::prefix('go')->group(function (): void {
            Route::get('/dashboard', [GlobiimGoController::class, 'dashboard']);
            Route::post('/partners/apply', [GlobiimGoController::class, 'applyPartner'])->middleware('throttle:10,1');
            Route::post('/drivers/apply', [GlobiimGoController::class, 'applyDriver'])->middleware('throttle:5,1');
            Route::post('/vehicles', [GlobiimGoController::class, 'createVehicle'])->middleware('throttle:10,1');
            Route::post('/fleet/management-requests', [GlobiimGoController::class, 'requestFleetManagement']);
            Route::post('/rides', [GlobiimGoController::class, 'requestRide'])->middleware('throttle:10,1');
            Route::get('/rides', [GlobiimGoController::class, 'rides']);
            Route::get('/trips', [GlobiimGoController::class, 'trips']);
            Route::post('/trips', [GlobiimGoController::class, 'createTrip']);
            Route::post('/trips/{trip}/publish', [GlobiimGoController::class, 'publishTrip']);
            Route::post('/trips/{trip}/book', [GlobiimGoController::class, 'bookTrip'])->middleware('throttle:10,1');
            Route::post('/deliveries', [GlobiimGoController::class, 'createDelivery']);
            Route::post('/vehicle-transports', [GlobiimGoController::class, 'createVehicleTransport']);
            Route::post('/corporate/accounts', [GlobiimGoController::class, 'createCorporateAccount']);
            Route::post('/safety/incidents', [GlobiimGoController::class, 'reportIncident'])->middleware('throttle:5,1');
        });

        Route::prefix('commerce')->group(function (): void {
            Route::post('/seller/apply', [CommerceController::class, 'applyAsSeller']);
            Route::get('/seller/me', [CommerceController::class, 'myStore']);
            Route::post('/products', [CommerceController::class, 'createProduct']);
            Route::post('/products/{product}/publish', [CommerceController::class, 'publishProduct']);
            Route::post('/products/{product}/videos', [CommerceExpansionController::class, 'createProductVideo']);
            Route::get('/cart', [CommerceController::class, 'cart']);
            Route::post('/cart/items', [CommerceController::class, 'addToCart'])->middleware('throttle:30,1');
            Route::patch('/cart/items/{item}', [CommerceController::class, 'updateCartItem']);
            Route::post('/checkout', [CommerceController::class, 'checkout'])->middleware('throttle:10,1');
            Route::get('/orders', [CommerceController::class, 'orders']);
            Route::post('/orders/{order}/cancel', [CommerceController::class, 'cancelOrder']);
            Route::get('/orders/{order}/shipments', [CommerceExpansionController::class, 'myShipments']);
            Route::post('/quotes', [CommerceController::class, 'requestQuote'])->middleware('throttle:10,1');
            Route::post('/business-requests', [CommerceController::class, 'createBusinessRequest'])->middleware('throttle:10,1');
            Route::get('/business-requests/mine', [CommerceController::class, 'myBusinessRequests']);
            Route::get('/saved', [CommerceController::class, 'savedProducts']);
            Route::post('/saved/{product}', [CommerceController::class, 'saveProduct']);
            Route::delete('/saved/{product}', [CommerceController::class, 'unsaveProduct']);
            Route::post('/reviews', [CommerceController::class, 'review'])->middleware('throttle:10,1');

            Route::post('/listings', [CommerceExpansionController::class, 'createListing']);
            Route::post('/listings/{listing}/publish', [CommerceExpansionController::class, 'publishListing']);
            Route::post('/listings/{listing}/bookings', [CommerceExpansionController::class, 'requestListingBooking']);
            Route::get('/bookings/mine', [CommerceExpansionController::class, 'myBookings']);
            Route::post('/partner-network/{partner}/applications', [CommerceExpansionController::class, 'applyToPartner']);
        });
    });
});
