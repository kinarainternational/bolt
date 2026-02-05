<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\OrdersController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('orders', [OrdersController::class, 'index'])->name('orders.index');
Route::get('orders/{orderId}', [OrdersController::class, 'show'])->name('orders.show');
Route::post('orders/export', [ExportController::class, 'export'])->name('orders.export');

require __DIR__.'/settings.php';
