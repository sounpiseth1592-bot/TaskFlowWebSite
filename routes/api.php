<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaskFlowController;
use App\Http\Resources\AccountResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/assets', AssetController::class);
Route::get('/users', [UserController::class, 'index']);
Route::get('/admins', [UserController::class, 'adminProfile'])->name('api.admins.show');
Route::get('/auth/google', [TaskFlowController::class, 'redirectToGoogleApi'])->name('api.google.redirect');
Route::post('/register', [TokenController::class, 'register']);
Route::post('/login', [TokenController::class, 'login']);

Route::middleware(['auth:sanctum', 'can:access-admin'])
    ->prefix('admin')
    ->name('api.admin.')
    ->group(function (): void {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
        Route::post('/users/{user}/token', [AdminController::class, 'createToken'])->name('users.token');
        Route::patch('/projects/{project}', [AdminController::class, 'updateProject'])->name('projects.update');
        Route::delete('/projects/{project}', [AdminController::class, 'destroyProject'])->name('projects.destroy');
        Route::patch('/tasks/{task}', [AdminController::class, 'updateTask'])->name('tasks.update');
        Route::delete('/tasks/{task}', [AdminController::class, 'destroyTask'])->name('tasks.destroy');
    });

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', fn (Request $request) => response()->json([
        'data' => AccountResource::make($request->user())->resolve($request),
    ]));
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::post('/logout', [ProfileController::class, 'revokeApiToken']);

    Route::get('/workspace', [TaskFlowController::class, 'workspace']);
    Route::get('/projects', [TaskFlowController::class, 'projects']);
    Route::post('/projects', [TaskFlowController::class, 'storeProject']);
    Route::get('/projects/{project}', [TaskFlowController::class, 'showProject']);
    Route::patch('/projects/{project}', [TaskFlowController::class, 'updateProject']);
    Route::delete('/projects/{project}', [TaskFlowController::class, 'destroyProject']);
    Route::get('/projects/{project}/tasks', [TaskFlowController::class, 'tasks']);
    Route::post('/projects/{project}/tasks', [TaskFlowController::class, 'storeTask']);
    Route::get('/projects/{project}/tasks/{task}', [TaskFlowController::class, 'showTask']);
    Route::patch('/projects/{project}/tasks/{task}', [TaskFlowController::class, 'updateTask']);
    Route::delete('/projects/{project}/tasks/{task}', [TaskFlowController::class, 'destroyTask']);
    Route::get('/tasks', [TaskFlowController::class, 'allTasks']);
});
