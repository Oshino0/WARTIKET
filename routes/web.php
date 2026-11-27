<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Customer;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Officer;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public catalog
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/explore', [ProductController::class, 'index'])->name('explore');
Route::get('/tickets/{product}', [ProductController::class, 'show'])->name('products.show');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::get('/admin', [AuthController::class, 'showAdminLogin'])->name('admin.login');
Route::post('/admin', [AuthController::class, 'adminLogin'])->middleware('throttle:10,1')->name('admin.authenticate');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Payment gateway callback (server-to-server, no session or CSRF token)
|--------------------------------------------------------------------------
*/
Route::post('/payment/ipaymu/callback', [PaymentController::class, 'callback'])
    ->name('payment.callback')
    ->withoutMiddleware([ValidateCsrfToken::class]);

/*
|--------------------------------------------------------------------------
| Customer (buying is customer-only, per the access matrix)
|--------------------------------------------------------------------------
*/
Route::get('/checkout', [CheckoutController::class, 'review'])->name('checkout.review');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:6,1')->name('checkout.store');
Route::get('/checkout/confirmation/{orderNumber}', [CheckoutController::class, 'confirmation'])
    ->name('checkout.confirmation');

Route::middleware(['auth', EnsureRole::class.':customer'])->group(function () {
    Route::get('/orders', [Customer\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{orderNumber}', [Customer\OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{orderNumber}/cancel', [Customer\OrderController::class, 'cancel'])->name('orders.cancel');

    Route::post('/orders/{orderNumber}/pay', [PaymentController::class, 'pay'])->name('orders.pay');
    Route::get('/payment/success', [PaymentController::class, 'returned'])->name('payment.return');
    Route::get('/payment/cancel', [PaymentController::class, 'cancelled'])->name('payment.cancel');

    Route::get('/profile', [Customer\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [Customer\ProfileController::class, 'update'])->name('profile.update');
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureRole::class.':admin'])->group(function () {
    Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::resource('categories', Admin\CategoryController::class)->except('show');
    Route::resource('products', Admin\ProductController::class)->except('show');

    Route::post('products/{product}/ticket-types', [Admin\TicketTypeController::class, 'store'])->name('ticket-types.store');
    Route::get('ticket-types/{ticketType}/edit', [Admin\TicketTypeController::class, 'edit'])->name('ticket-types.edit');
    Route::put('ticket-types/{ticketType}', [Admin\TicketTypeController::class, 'update'])->name('ticket-types.update');
    Route::delete('ticket-types/{ticketType}', [Admin\TicketTypeController::class, 'destroy'])->name('ticket-types.destroy');

    Route::get('banners', [Admin\BannerController::class, 'index'])->name('banners.index');
    Route::post('banners', [Admin\BannerController::class, 'store'])->name('banners.store');
    Route::get('banners/{banner}/edit', [Admin\BannerController::class, 'edit'])->name('banners.edit');
    Route::put('banners/{banner}', [Admin\BannerController::class, 'update'])->name('banners.update');
    Route::patch('banners/{banner}/toggle', [Admin\BannerController::class, 'toggle'])->name('banners.toggle');
    Route::delete('banners/{banner}', [Admin\BannerController::class, 'destroy'])->name('banners.destroy');

    Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/verify', [Admin\OrderController::class, 'verify'])->name('orders.verify');
    Route::post('orders/{order}/resend', [Admin\OrderController::class, 'resend'])->name('orders.resend');
    Route::post('orders/{order}/reject', [Admin\OrderController::class, 'reject'])->name('orders.reject');

    Route::get('products/{product}/email', [Admin\ProductEmailController::class, 'edit'])->name('products.email.edit');
    Route::put('products/{product}/email', [Admin\ProductEmailController::class, 'update'])->name('products.email.update');
    Route::post('products/{product}/email/reset', [Admin\ProductEmailController::class, 'reset'])->name('products.email.reset');
    Route::get('products/{product}/email/preview', [Admin\ProductEmailController::class, 'preview'])->name('products.email.preview');

    Route::get('email', [Admin\EmailSettingController::class, 'edit'])->name('email.edit');
    Route::put('email', [Admin\EmailSettingController::class, 'update'])->name('email.update');
    Route::post('email/reset', [Admin\EmailSettingController::class, 'reset'])->name('email.reset');
    Route::get('email/preview', [Admin\EmailSettingController::class, 'preview'])->name('email.preview');
    Route::get('payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
});

/*
|--------------------------------------------------------------------------
| Officer (scanner arrives in Phase 5)
|--------------------------------------------------------------------------
*/
Route::prefix('officer')->name('officer.')->middleware(['auth', EnsureRole::class.':officer,admin'])->group(function () {
    Route::get('/', [Officer\DashboardController::class, 'index'])->name('dashboard');
});
