<?php

use Illuminate\Support\Facades\Route;

/* common controller */
use App\Http\Controllers\Auth\AuthController;

/* admin controller */
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\AuditController;

/* users controller */
use App\Http\Controllers\User\ProcessConfigController;

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

        /* user activity logs */
        Route::get('/user-activity-logs', [ AuthController::class,'getUserActivities'])->name('user-activity-logs');

        /* departments routes */
        Route::get('/departments-listing', [DepartmentController::class, 'index'])->name('departments-listing');
        Route::post('/store-department', [DepartmentController::class, 'store'])->name('store-department');
        Route::get('/department-detail/{id}', [DepartmentController::class, 'show'])->name('department-details');
        Route::put('/update-department/{id}', [DepartmentController::class, 'update'])->name('update-department');
        Route::delete('/delete-department/{id}', [DepartmentController::class, 'destroy'])->name('delete-department');
        Route::patch('/toggle-department/{id}', [DepartmentController::class, 'toggleActive'])->name('toggle-department');

        /* roles routes */
        Route::get('/roles-listing', [RoleController::class, 'index'])->name('roles-listing');
        Route::post('/store-role', [RoleController::class, 'store'])->name('store-role');
        Route::get('/role-detail/{id}', [RoleController::class, 'show'])->name('role-details');
        Route::put('/update-role/{id}', [RoleController::class, 'update'])->name('update-role');
        Route::delete('/delete-role/{id}', [RoleController::class, 'destroy'])->name('delete-role');
        Route::patch('/toggle-role/{id}', [RoleController::class, 'toggleActive'])->name('toggle-role');

        /* user routes */
        Route::get('/users-pid', [UserController::class, 'getUserPID'])->name('users-pid');
        Route::get('/users-listing', [UserController::class, 'index'])->name('users-listing');
        Route::post('/store-user', [UserController::class, 'store'])->name('store-user');
        Route::get('/user-detail/{id}', [UserController::class, 'show'])->name('user-details');
        Route::put('/update-user/{id}', [UserController::class, 'update'])->name('update-user');
        Route::delete('/delete-user/{id}', [UserController::class, 'destroy'])->name('delete-user');
        Route::patch('/toggle-user/{id}', [UserController::class, 'toggleActive'])->name('toggle-user');

        /* admin audit route */
        Route::get('/audit-listing', [AuditController::class, 'index'])->name('audit-listing');
    });

    /* user apis */ 
    Route::middleware('users')->prefix('user')->group(function () {

        /* process/stages/activities routes */
        Route::get('/process-list', [ProcessConfigController::class, 'getProcesses'])->name('process-listing');
        Route::get('/stages-list/{processId}', [ProcessConfigController::class, 'getProcessStages'])->name('stages-list');
        Route::get('/activities-list/{stageId}', [ProcessConfigController::class, 'getStageActivities'])->name('activities-list');
    });
});