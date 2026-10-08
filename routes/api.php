<?php

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\RestaurantController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
| These routes are accessible without authentication.
| Used for registering, logging in, requesting OTP, verifying OTP,
| and resetting passwords.
|--------------------------------------------------------------------------
*/

// Register as a Customer new account
Route::post('/register', [AuthenticationController::class, 'register'])
    ->middleware('throttle:5,1');

// Login with rate limiting (max 5 attempts per minute)
Route::post('/login', [AuthenticationController::class, 'login'])
    ->middleware('throttle:5,1');

// Request OTP for email verification or password reset
Route::post('/request-otp', [AuthenticationController::class, 'sendOtp']);

// Verify OTP code 
Route::post('/verify-otp-email', [AuthenticationController::class, 'verifyOtp']);


// Reset password (only after email is verified)
Route::post('/set-password', [AuthenticationController::class, 'setPassword'])
    ->middleware('throttle:5,1');


/*
|--------------------------------------------------------------------------
| Protected Routes (Require Authentication)
|--------------------------------------------------------------------------
| These routes require a valid Sanctum token.
| Users must be logged in to access them.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // Logout and revoke active token
    Route::post('/logout', [AuthenticationController::class, 'logout']);
    // Get the authenticated user's information
    Route::get('/me', [AuthenticationController::class, 'user']);

    // Refresh an expired/expiring token (if applicable in your logic)
    Route::post('/token/refresh', [AuthenticationController::class, 'refreshToken']);

    //chage phone number
    Route::post('/phone', [AuthenticationController::class, 'setPhone']);

    //change password
    Route::post('/changePassword', [ProfileController::class, 'changePassword']);

    //change user name
    Route::post('/changeName', [ProfileController::class, 'changeName']);
    
    // To be tenat 
    Route::post('/restaurant-request', [RequestController::class, 'restaurantRequest']);

    //carts
    Route::post('/cart', [CartController::class, 'add']);   // add item to cart
    Route::delete('/cart', [CartController::class, 'clear']); // clear the carts
    Route::get('/cart', [CartController::class, 'index']); // get carts


    Route::delete('/cart/{restaurant_id}', [CartController::class, 'destroy']); // remove cart

    Route::get('/cart/restaurant/{restaurant_id}', [CartController::class, 'cartByRestaurant']); // get restaurant cart item
    Route::delete('/cart/restaurant/{item_id}', [CartController::class, 'removeItem']); //remove Item From Cart
    Route::get('/cart/restaurant/item/{cart_id}', [CartController::class, 'getItemForEdit']); // return current selections for edit UI
    Route::post('/cart/restaurant/edite_item/{cart_id}', [CartController::class, 'editItem']); //edit Item (variant , modifiers) in Cart
    Route::post('/cart/restaurant/edite_item_quantity', [CartController::class, 'editeItemQuantity']); // edite items quantity
});

Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']); //get my orders
});

// get home data : restaurant + restaurant-categories
Route::get('/home', [HomeController::class, 'getHomeData']);

//get restaurant by restaurant
Route::get('/categories/{category}/restaurants', [HomeController::class, 'restaurantByCategory']);

//get restaurant menu items
Route::get('restaurants/{restaurant}/menu', [RestaurantController::class, 'menu']);

//get meal analysis
Route::get('restaurants/{restaurant}/menu-items/{menuItem}/analysis', [RestaurantController::class, 'showMealAnalysis'])
            ->whereNumber(['restaurant', 'menuItem']);

//get all restaurant-categories
Route::get('restaurant-categories', [HomeController::class, 'categories']);

//get item details (cached 5 minutes)
Route::get('/restaurants/{restaurant}/menu/{menu_item}', [RestaurantController::class, 'getItem']);

Broadcast::routes(['middleware' => ['auth:sanctum']]);