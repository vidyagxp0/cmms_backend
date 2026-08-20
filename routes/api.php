<?php

use Illuminate\Support\Facades\Route;

/* common controller */
use App\Http\Controllers\Auth\AuthController;

/* admin controller */
use App\Http\Controllers\Admin\RoleController;


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
    Route::middleware('role:Admin')->prefix('admin')->group(function () {

        /* roles routes */
        Route::get('/roles-listing', [RoleController::class, 'index'])->name('user-profile');
        Route::post('/store-role', [RoleController::class, 'store'])->name('user-profile');
        Route::get('/role-detail/{id}', [RoleController::class, 'show'])->name('user-profile');
        Route::put('/update-role/{id}', [RoleController::class, 'update'])->name('user-profile');
        Route::delete('/delete-role/{id}', [RoleController::class, 'destroy'])->name('user-profile');
        Route::patch('/toggle-role/{id}', [RoleController::class, 'toggleActive'])->name('user-profile');
    });

    /* user apis */
    Route::middleware('role:User')->prefix('user')->group(function () {

    });
});