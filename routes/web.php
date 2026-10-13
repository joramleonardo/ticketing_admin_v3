<?php

use App\Http\Controllers\Admin\NewTicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CsmController;
use App\Http\Controllers\CsmReportsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MyTicketsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingReportsController;
use App\Http\Controllers\TicketStatusController;
use App\Http\Controllers\TicketV2Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Optional: legacy reports route pointing to same controller
Route::get('/reports/no-csm-all', [CsmReportsController::class, 'index'])
    ->middleware(['auth'])                 // ⬅️ protect if you like
    ->name('reports.no-csm-all');

// Public endpoint to check if a reference has CSM (used by client rate page etc.)
Route::get('/csm/check/{ref}', [CsmController::class, 'check'])
    ->name('csm.check');

Route::get('/client', [ClientController::class, 'index'])->name('client.main');
Route::post('/client/tickets', [ClientController::class, 'store'])->name('client.tickets.store');
Route::post('/client-tickets-v2', [ClientController::class, 'store'])->name('tickets.v2.store');
Route::get('/tickets/{ref}/attachments', [ClientController::class, 'listAttachments'])
    ->name('tickets.attachments.index');
Route::get('/tickets/{ref}/attachments/{attachment}', [ClientController::class, 'downloadAttachment'])
    ->whereNumber('attachment')
    ->name('tickets.attachments.download');

Route::get('/client/track/{employee_code}', [ClientController::class, 'track'])->name('client.track');
Route::get('/client/ticket/{ref}', [ClientController::class, 'ticketShow']);
Route::get('/client/ticket/{ref}/messages', [ClientController::class, 'ticketMessages']);
Route::post('/client/ticket/{ref}/messages', [ClientController::class, 'postTicketMessage']);
Route::get('/client/ticket/{ref}/rating', [ClientController::class, 'ratingShow']);
Route::post('/client/ticket/{ref}/rating', [ClientController::class, 'ratingStore']);
Route::get('/client/rate/{ref}', [ClientController::class, 'ratePage'])->name('client.rate');

Route::middleware('auth')->group(function () {

    // ✅ CSM Dashboard (AuthenticatedLayout → CSMDashboard → Has/No CSM tables)
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Export monthly ticket stats as CSV
    Route::get('/dashboard/export', [DashboardController::class, 'export'])
        ->name('dashboard.export');

    Route::get('/csm/dashboard', [CsmReportsController::class, 'index'])
        ->name('csm.dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin
    Route::get('/admin/new-tickets', [NewTicketController::class, 'index'])->name('admin.new-tickets');
    Route::get('/admin/my-tickets', [MyTicketsController::class,  'index'])->name('admin.my-tickets');
    Route::get('/admin/my-tickets/check-updates', [MyTicketsController::class, 'checkUpdates'])->name('admin.my-tickets.check-updates');
    Route::get('/admin/my-tickets/export', [MyTicketsController::class, 'export'])->name('admin.my-tickets.export');

    // Redirect legacy route to current Tickets V2 index
    Route::redirect('/all-tickets', '/admin/tickets-v2')->name('tickets.all');

    Route::put('/tickets/{id}', [TicketStatusController::class, 'update'])->name('tickets.update');
    Route::put('/tickets-attend/{id}', [TicketStatusController::class, 'attend'])->name('tickets.attend');
    Route::put('/tickets-complete/{id}', [TicketStatusController::class, 'complete'])->name('tickets.complete');
    Route::put('/tickets-close/{id}', [TicketStatusController::class, 'close'])->name('tickets.close');

    Route::get('/tickets/logs/{reference_code}', [TicketStatusController::class, 'getActivityLogs']);
    Route::post('/tickets-v2', [TicketStatusController::class, 'storeNew'])->name('tickets.v2.storeNew');

    Route::prefix('admin/tickets-v2')->group(function () {
        Route::get('/', [TicketV2Controller::class, 'index'])->name('tickets-v2.index');
        Route::get('/quick-search', [TicketV2Controller::class, 'quickSearch'])->name('tickets-v2.quick-search');
        Route::get('/check-updates', [TicketV2Controller::class, 'checkUpdates'])->name('tickets-v2.check-updates');
        Route::get('/export', [TicketV2Controller::class, 'export'])->name('tickets-v2.export');
        Route::post('/bulk-assign', [TicketV2Controller::class, 'bulkAssign'])->name('tickets-v2.bulk-assign');
        Route::post('/bulk-close', [TicketV2Controller::class, 'bulkClose'])->name('tickets-v2.bulk-close');
        Route::post('/bulk-delete', [TicketV2Controller::class, 'bulkDelete'])->name('tickets-v2.bulk-delete');
        Route::get('/create', [TicketV2Controller::class, 'create'])->name('tickets-v2.create');
        Route::post('/', [TicketV2Controller::class, 'store'])->name('tickets-v2.store');
    });

    Route::delete('/admin/tickets-v2/{id}', [TicketV2Controller::class, 'destroy'])->name('tickets-v2.destroy');

    Route::get('/ratings/pending', [\App\Http\Controllers\RatingsController::class, 'pending'])->name('ratings.pending');
    Route::get('/ratings/dashboard', [RatingReportsController::class, 'index'])->name('ratings.dashboard');

    Route::get('/ticket-employees', [TicketV2Controller::class, 'getEmployees']);
    Route::get('/admin/staff-members', fn () => response()->json(app(\App\Services\StaffService::class)->getActiveStaff()))->name('admin.staff.index');

    Route::get('/admin/tickets/{ref}/messages', [ClientController::class, 'ticketMessages'])->name('admin.tickets.messages.index');
    Route::post('/admin/tickets/{ref}/messages', [TicketV2Controller::class, 'postStaffMessage'])->name('admin.tickets.messages.store');

    Route::prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::patch('/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('toggle-status');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });
});

Route::get('/ticket-classifications', fn () => response()->json(\App\Support\TicketClassification::all()))->name('ticket.classifications');

require __DIR__.'/auth.php';
