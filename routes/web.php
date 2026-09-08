<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReservationController::class, 'index'])->name('reservation.index');
Route::get('/api/availability', [ReservationController::class, 'availability'])->name('reservation.availability');
Route::post('/reservations', [ReservationController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('reservation.store');
Route::get('/reservation/{reference}', [ReservationController::class, 'confirmation'])
    ->where('reference', '[A-Z0-9]{8,12}')
    ->name('reservation.confirmation');

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('admin.login.submit');

Route::prefix('admin')->middleware('saklshi.admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    Route::patch('/reservations/{reservation}/status', [AdminController::class, 'updateStatus'])
        ->name('admin.reservations.status');

    Route::post('/menu', [AdminController::class, 'storeMenu'])->name('admin.menu.store');
    Route::put('/menu/{menuItem}', [AdminController::class, 'updateMenu'])->name('admin.menu.update');
    Route::patch('/menu/{menuItem}/toggle', [AdminController::class, 'toggleMenu'])->name('admin.menu.toggle');

    Route::delete('/menu/{menuItem}', [AdminController::class, 'destroyMenu'])->name('admin.menu.destroy');

    Route::post('/tables', [AdminController::class, 'storeTable'])->name('admin.tables.store');
    Route::put('/tables/{diningTable}', [AdminController::class, 'updateTable'])->name('admin.tables.update');
    Route::patch('/tables/{diningTable}/toggle', [AdminController::class, 'toggleTable'])->name('admin.tables.toggle');
});


if (app()->environment('testing')) {
    Route::get('/__ci/admin-dashboard', [AdminController::class, 'index']);
}
