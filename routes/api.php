<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaskFlowController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/assets', AssetController::class);
Route::post('/register', [TokenController::class, 'register']);
Route::post('/login', [TokenController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', fn (Request $request) => response()->json([
        'data' => UserResource::make($request->user())->resolve($request),
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
