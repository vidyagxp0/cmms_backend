<?php

use Illuminate\Support\Facades\Route;

/* admin controller */
use App\Http\Controllers\Auth\AuthController;

/* common routes for user/admin */
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {

    /* common profile urls */
    Route::get('/profile', [AuthController::class, 'profile'])->name('user-profile');
    Route::put('/update/profile', [AuthController::class,'updateProfile'])->name('update-profile');
    Route::put('/change-password', [AuthController::class,'changePassword'])->name('change-password');
    Route::post('/logout', [ AuthController::class,'logout'])->name('logout');

    Route::post('/logout', [AuthController::class, 'logout']);

    /* admin apis */
    Route::middleware('role:admin')->prefix('admin')->group(function () {

    });

    /* user apis */
    Route::middleware('role:user')->prefix('user')->group(function () {

    });
});