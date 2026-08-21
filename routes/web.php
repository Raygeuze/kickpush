<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\BusinessExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimerSessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Timer', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/clients', [ClientController::class, 'indexPage'])->name('clients.index');
    Route::get('/clients/create', [ClientController::class, 'createPage'])->name('clients.createPage');
    Route::get('/clients/list', [ClientController::class, 'list'])->name('clients.list');
    Route::post('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::put('/clients/{clientId}', [ClientController::class, 'update'])->name('clients.update');

    Route::get('/projects/list', [ProjectController::class, 'list'])->name('projects.list');
    Route::get('/projects/{projectId}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::put('/projects/{projectId}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{projectId}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::get('/tasks/list', [TaskController::class, 'list'])->name('tasks.list');
    Route::post('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::put('/tasks/{taskId}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{taskId}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    Route::get('/business-expenses', [BusinessExpenseController::class, 'index'])->name('businessExpenses.index');
    Route::post('/business-expenses', [BusinessExpenseController::class, 'store'])->name('businessExpenses.store');
    Route::post('/business-expenses/{businessExpenseId}', [BusinessExpenseController::class, 'update'])->name('businessExpenses.update');
    Route::delete('/business-expenses/{businessExpenseId}', [BusinessExpenseController::class, 'destroy'])->name('businessExpenses.destroy');

    Route::post('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::get('/invoices/latest', [InvoiceController::class, 'latest'])->name('invoices.latest');
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/tax-summary/financial-year', [InvoiceController::class, 'financialYearTaxSummary'])->name('invoices.financialYearTaxSummary');
    Route::get('/invoices/{invoiceId}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoiceId}/tax-summary', [InvoiceController::class, 'taxSummary'])->name('invoices.taxSummary');
    Route::get('/invoices/{invoiceId}/details', [InvoiceController::class, 'details'])->name('invoices.details');
    Route::post('/invoices/{invoiceId}/financial-year', [InvoiceController::class, 'assignFinancialYear'])->name('invoices.financialYear.assign');
    Route::get('/invoices/{invoiceId}/timer/status', [InvoiceController::class, 'inlineTimerStatus'])->name('invoices.timer.status');
    Route::post('/invoices/{invoiceId}/timer/start', [InvoiceController::class, 'startInlineTimer'])->name('invoices.timer.start');
    Route::post('/invoices/{invoiceId}/timer/pause', [InvoiceController::class, 'pauseInlineTimer'])->name('invoices.timer.pause');
    Route::post('/invoices/{invoiceId}/timer/resume', [InvoiceController::class, 'resumeInlineTimer'])->name('invoices.timer.resume');
    Route::post('/invoices/{invoiceId}/timer/stop', [InvoiceController::class, 'stopInlineTimer'])->name('invoices.timer.stop');
    Route::post('/invoices/{invoiceId}/sessions', [InvoiceController::class, 'attachSession'])->name('invoices.sessions.attach');
    Route::post('/invoices/{invoiceId}/sessions/manual', [InvoiceController::class, 'createManualSession'])->name('invoices.sessions.manual');
    Route::post('/invoices/{invoiceId}/sessions/{sessionId}/resume', [InvoiceController::class, 'resumeStoppedSession'])->name('invoices.sessions.resume');
    Route::post('/invoices/{invoiceId}/sessions/{sessionId}/date', [InvoiceController::class, 'updateSessionDate'])->name('invoices.sessions.updateDate');
    Route::post('/invoices/{invoiceId}/sessions/{sessionId}/duration', [InvoiceController::class, 'updateSessionDuration'])->name('invoices.sessions.updateDuration');
    Route::post('/invoices/{invoiceId}/sessions/{sessionId}/task', [InvoiceController::class, 'updateSessionTask'])->name('invoices.sessions.updateTask');
    Route::post('/invoices/{invoiceId}/discount', [InvoiceController::class, 'updateDiscount'])->name('invoices.discount.update');
    Route::delete('/invoices/{invoiceId}/sessions/{sessionId}', [InvoiceController::class, 'detachSession'])->name('invoices.sessions.detach');
    Route::post('/invoices/{invoiceId}/expenses', [InvoiceController::class, 'addExpense'])->name('invoices.expenses.add');
    Route::delete('/invoices/{invoiceId}/expenses/{expenseId}', [InvoiceController::class, 'removeExpense'])->name('invoices.expenses.remove');
    Route::post('/invoices/{invoiceId}/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
    Route::post('/invoices/{invoiceId}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.markPaid');
    Route::post('/invoices/{invoiceId}/email-client', [InvoiceController::class, 'emailClientPdf'])->name('invoices.emailClientPdf');
    Route::delete('/invoices/{invoiceId}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::get('/invoices/{invoiceId}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');

    Route::get('/timer/status', [TimerSessionController::class, 'status'])->name('timer.status');
    Route::get('/timer/history', [TimerSessionController::class, 'history'])->name('timer.history');
    Route::post('/timer/start', [TimerSessionController::class, 'start'])->name('timer.start');
    Route::post('/timer/pause', [TimerSessionController::class, 'pause'])->name('timer.pause');
    Route::post('/timer/resume', [TimerSessionController::class, 'resume'])->name('timer.resume');
    Route::post('/timer/stop', [TimerSessionController::class, 'stop'])->name('timer.stop');
    Route::post('/timer/submit-to-invoice', [TimerSessionController::class, 'submitToInvoice'])->name('timer.submitToInvoice');
});

require __DIR__.'/auth.php';
