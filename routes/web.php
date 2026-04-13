<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard')
        ->middleware('permission:dashboard.view');

    // Contacts
    Route::middleware('permission:contacts.view')->group(function () {
        Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::get('/contacts/page', [ContactController::class, 'page'])->name('contacts.page');
        Route::get('/contacts/export', [ContactController::class, 'export'])
            ->name('contacts.export')
            ->middleware('permission:contacts.export');
        Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    });
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])
        ->name('contacts.update')
        ->middleware('permission:contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])
        ->name('contacts.destroy')
        ->middleware('permission:contacts.delete');
    Route::post('/contacts/bulk-status', [ContactController::class, 'bulkStatus'])
        ->name('contacts.bulk-status')
        ->middleware('permission:contacts.bulk_status');
    Route::post('/contacts/bulk-labels', [ContactController::class, 'bulkLabels'])
        ->name('contacts.bulk-labels')
        ->middleware('permission:contacts.bulk_status');
    Route::post('/contacts/quick-add', [ContactController::class, 'quickAdd'])
        ->name('contacts.quick-add')
        ->middleware('permission:import.manage');

    // Labels
    Route::get('/labels', [LabelController::class, 'index'])
        ->name('labels.index')
        ->middleware('permission:labels.view');
    Route::middleware('permission:labels.manage')->group(function () {
        Route::post('/labels', [LabelController::class, 'store'])->name('labels.store');
        Route::put('/labels/{label}', [LabelController::class, 'update'])->name('labels.update');
        Route::delete('/labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');
    });

    // Statuses
    Route::get('/statuses', [StatusController::class, 'index'])
        ->name('statuses.index')
        ->middleware('permission:statuses.view');
    Route::middleware('permission:statuses.manage')->group(function () {
        Route::post('/statuses', [StatusController::class, 'store'])->name('statuses.store');
        Route::put('/statuses/{status}', [StatusController::class, 'update'])->name('statuses.update');
        Route::delete('/statuses/{status}', [StatusController::class, 'destroy'])->name('statuses.destroy');
    });

    // Import
    Route::middleware('permission:import.manage')->group(function () {
        Route::get('/import', [ImportController::class, 'create'])->name('import.create');
        Route::post('/import/preview', [ImportController::class, 'preview'])->name('import.preview');
        Route::post('/import', [ImportController::class, 'store'])->name('import.store');
        Route::get('/import/{import}', [ImportController::class, 'show'])->name('import.show');
        Route::get('/import/{import}/status', [ImportController::class, 'status'])->name('import.status');
    });

    // WhatsApp
    Route::middleware('permission:whatsapp.view')->group(function () {
        Route::get('/whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp.index');
        Route::get('/whatsapp/{account}/chat', [WhatsAppController::class, 'chat'])->name('whatsapp.chat');
        Route::get('/whatsapp/{account}/messages', [WhatsAppController::class, 'messages'])->name('whatsapp.messages');
        Route::get('/whatsapp/{account}/templates', [WhatsAppController::class, 'templates'])->name('whatsapp.templates');
    });
    Route::middleware('permission:whatsapp.manage')->group(function () {
        Route::post('/whatsapp', [WhatsAppController::class, 'store'])->name('whatsapp.store');
        Route::put('/whatsapp/{account}', [WhatsAppController::class, 'update'])->name('whatsapp.update');
        Route::delete('/whatsapp/{account}', [WhatsAppController::class, 'destroy'])->name('whatsapp.destroy');
        Route::post('/whatsapp/{account}/refresh', [WhatsAppController::class, 'refresh'])->name('whatsapp.refresh');
        Route::post('/whatsapp/{account}/send', [WhatsAppController::class, 'send'])->name('whatsapp.send');
        Route::post('/whatsapp/{account}/bulk-send', [WhatsAppController::class, 'bulkSend'])->name('whatsapp.bulk-send');
    });

    // Profile (always accessible)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin
    Route::prefix('admin')->name('admin.')->group(function () {
        // Users
        Route::get('/users', [UserController::class, 'index'])
            ->name('users.index')
            ->middleware('permission:admin.users.view');
        Route::middleware('permission:admin.users.create')->group(function () {
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
        });
        Route::middleware('permission:admin.users.update')->group(function () {
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        });
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->name('users.destroy')
            ->middleware('permission:admin.users.delete');

        // Roles
        Route::get('/roles', [RoleController::class, 'index'])
            ->name('roles.index')
            ->middleware('permission:admin.roles.view');
        Route::middleware('permission:admin.roles.manage')->group(function () {
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });
    });
});

// WhatsApp Webhook (public, no auth)
Route::get('/webhook/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhook/whatsapp', [WhatsAppWebhookController::class, 'handle']);

require __DIR__.'/auth.php';
