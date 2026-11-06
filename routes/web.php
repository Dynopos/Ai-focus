<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemoryController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Meta WhatsApp Cloud API webhook (signature-verified, CSRF-exempt)
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:whatsapp-webhook')
    ->name('webhooks.whatsapp.receive');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'status'])->name('tasks.status');
    Route::post('/tasks/{task}/follow-up', [TaskController::class, 'followUp'])->name('tasks.follow-up');
    Route::post('/tasks/{task}/reassign', [TaskController::class, 'requestReassign'])->name('tasks.reassign');

    Route::get('/decision-room', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('/decision-room/new', [ApprovalController::class, 'create'])->name('approvals.create');
    Route::post('/decision-room', [ApprovalController::class, 'store'])->name('approvals.store');
    Route::get('/decision-room/{approval}', [ApprovalController::class, 'show'])->name('approvals.show');
    Route::post('/decision-room/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/decision-room/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    Route::post('/decision-room/{approval}/modify', [ApprovalController::class, 'modify'])->name('approvals.modify');
    Route::post('/decision-room/{approval}/retry', [ApprovalController::class, 'retry'])->name('approvals.retry');

    Route::get('/communications', [CommunicationController::class, 'index'])->name('communications.index');
    Route::get('/communications/{log}', [CommunicationController::class, 'show'])->name('communications.show');
    Route::post('/communications/{log}/retry', [CommunicationController::class, 'retry'])->name('communications.retry');

    Route::get('/automations', [AutomationController::class, 'index'])->name('automations.index');
    Route::put('/automations', [AutomationController::class, 'update'])->name('automations.update');
    Route::post('/automations/{automation}/run', [AutomationController::class, 'runNow'])->name('automations.run');

    Route::resource('contacts', ContactController::class)->except(['show']);
    Route::resource('memory', MemoryController::class)->except(['show', 'create'])->parameters(['memory' => 'memory']);

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/phone', [SettingsController::class, 'startPhoneVerification'])->name('settings.phone');

    Route::get('/audit', AuditController::class)->name('audit.index');
});
