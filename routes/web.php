<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaskFlowController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TaskFlowController::class, 'dashboard'])->name('home');
Route::get('/about', [TaskFlowController::class, 'landingSection'])->defaults('section', 'about')->name('landing.about');
Route::get('/features', [TaskFlowController::class, 'landingSection'])->defaults('section', 'features')->name('landing.features');
Route::get('/how-it-works', [TaskFlowController::class, 'landingSection'])->defaults('section', 'how-it-works')->name('landing.how-it-works');
Route::get('/contact', [TaskFlowController::class, 'landingSection'])->defaults('section', 'contact')->name('landing.contact');
Route::get('/login', [TaskFlowController::class, 'showLogin'])->name('login');
Route::get('/register', [TaskFlowController::class, 'showRegister'])->name('register');
Route::post('/auth/login', [TaskFlowController::class, 'login'])->middleware('guest');
Route::post('/auth/register', [TaskFlowController::class, 'register'])->middleware('guest');
Route::get('/auth/google', [TaskFlowController::class, 'redirectToGoogle'])->name('google.redirect')->middleware('guest');
Route::get('/auth/google/callback', [TaskFlowController::class, 'handleGoogleCallback'])->name('google.callback')->middleware('guest');
Route::post('/logout', [TaskFlowController::class, 'logout'])->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/settings', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/workspace', [TaskFlowController::class, 'workspacePage'])->name('workspace.index');
    Route::get('/workspace/today', [TaskFlowController::class, 'workspacePage'])->defaults('filter', 'today')->name('workspace.today');
    Route::get('/workspace/overdue', [TaskFlowController::class, 'workspacePage'])->defaults('filter', 'overdue')->name('workspace.overdue');
    Route::get('/workspace/completed', [TaskFlowController::class, 'workspacePage'])->defaults('filter', 'done')->name('workspace.completed');
    Route::get('/workspace/projects/{project}', [TaskFlowController::class, 'workspaceProject'])->name('workspace.projects.show');
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
