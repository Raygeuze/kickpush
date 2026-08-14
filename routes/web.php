<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Day;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TimerSessionController;
use App\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Auth;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Http\Controllers\Inertia\TeamController;
use Laravel\Jetstream\Http\Controllers\CurrentTeamController;
use Laravel\Jetstream\Http\Controllers\Inertia\TeamMemberController;
use Laravel\Jetstream\Http\Controllers\TeamInvitationController;


// Route::get('/', function () {

//     $user = Auth::user();
//     $day = \App\Models\Day::latest()->first();

//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'day' => $day,
//         'submissions' => $day->submissions()->withCount('comments')->paginate(2),
//     ]);
// });

Route::middleware([
    // 'auth:sanctum',
    // config('jetstream.auth_session'),
    // 'verified',
])->group(function () {
    Route::get('/', function () {
        
        // $user = Auth::user();
        // $day = \App\Models\Day::latest()
        //     ->with('submissions')
        //     ->with('topic')
        //     ->with('prizePool')
        //     ->first();

        // return Inertia::render('Dashboard', [
        //     'canLogin' => Route::has('login'),
        //     'canRegister' => Route::has('register'),
        //     'day' => $day,
        //     'submissions' => $day->submissions()->with('parentComments')->withCount('comments')->paginate(5),
        //     'user' => $user,
        //     'todaysVoteCount' => $user ? $user->todaysVoteCount() : null,
        // ]);

        return Inertia::render('Timer', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
        ]);
    })->name('dashboard');
});

Route::get('/today', function () {
    
    $user = Auth::user();
    $day = \App\Models\Day::latest()
        ->with('submissions')
        ->with('topic')
        ->with('prizePool')
        ->first();

    return Inertia::render('Dashboard', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'day' => $day,
        'submissions' => $day->submissions()->with('parentComments')->withCount('comments')->paginate(5),
        'user' => $user,
        'todaysVoteCount' => $user ? $user->todaysVoteCount() : null,
    ]);
})->name('today');

Route::get('/search', [\App\Http\Controllers\SearchController::class, 'search'])->name('search');
Route::get('/search/loadMoreDays', [\App\Http\Controllers\SearchController::class, 'loadMoreDays'])->name('search.loadMoreDays');
Route::get('/search/loadMoreSubmissions', [\App\Http\Controllers\SearchController::class, 'loadMoreSubmissions'])->name('search.loadMoreSubmissions');
Route::get('/search/loadMoreUsers', [\App\Http\Controllers\SearchController::class, 'loadMoreUsers'])->name('search.loadMoreUsers');

Route::get('/about', function () {
    return Inertia::render('About');
})->name('about');

Route::get('/terms', function () {
    return Inertia::render('TermsOfService');
})->name('terms');

Route::get('/privacy', function () {
    return Inertia::render('PrivacyPolicy');
})->name('privacy');

Route::get('/contact', function () {
    return Inertia::render('Contact');
})->name('contact');
Route::post('/contact/submit', [\App\Http\Controllers\ContactController::class, 'submit'])->name('contact.submit');

Route::middleware('auth')->group(function () {
    Route::get('/clients/create', [ClientController::class, 'createPage'])->name('clients.createPage');
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::post('/clients/create', [ClientController::class, 'create'])->name('clients.create');

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
    Route::delete('/invoices/{invoiceId}/sessions/{sessionId}', [InvoiceController::class, 'detachSession'])->name('invoices.sessions.detach');
    Route::post('/invoices/{invoiceId}/expenses', [InvoiceController::class, 'addExpense'])->name('invoices.expenses.add');
    Route::delete('/invoices/{invoiceId}/expenses/{expenseId}', [InvoiceController::class, 'removeExpense'])->name('invoices.expenses.remove');
    Route::post('/invoices/{invoiceId}/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
    Route::post('/invoices/{invoiceId}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.markPaid');
    Route::get('/invoices/{invoiceId}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');

    Route::get('/timer/status', [TimerSessionController::class, 'status'])->name('timer.status');
    Route::get('/timer/history', [TimerSessionController::class, 'history'])->name('timer.history');
    Route::post('/timer/start', [TimerSessionController::class, 'start'])->name('timer.start');
    Route::post('/timer/pause', [TimerSessionController::class, 'pause'])->name('timer.pause');
    Route::post('/timer/resume', [TimerSessionController::class, 'resume'])->name('timer.resume');
    Route::post('/timer/stop', [TimerSessionController::class, 'stop'])->name('timer.stop');
    Route::post('/timer/submit-to-invoice', [TimerSessionController::class, 'submitToInvoice'])->name('timer.submitToInvoice');
});

//profile stuffs
// Route::get('/user/disable', [\App\Http\Controllers\UserController::class, 'disable'])->name('user.disable');
// Route::get('/user/reenable', [\App\Http\Controllers\UserController::class, 'reenable'])->name('user.reenable');
Route::post('/user/reenable-request', [\App\Http\Controllers\ProfileController::class, 'reenableRequest'])->name('user.reenable_request');


//User stuffs - public profile
Route::get('/user/{id}', [\App\Http\Controllers\UserController::class, 'show'])->name('user.show');

// User stuff - non public profile
Route::get('/profile/payments/details', [\App\Http\Controllers\ProfileController::class, 'showPaymentsDetails'])->middleware('auth')->name('profile.paymentsDetails');
Route::get('/profile/payments', [\App\Http\Controllers\ProfileController::class, 'showPayments'])->middleware('auth')->name('profile.payments');


// Route::resource('days', \App\Http\Controllers\DayController::class);
// Route::post('/days/store', [\App\Http\Controllers\DayController::class, 'store']);
Route::get('/the-archive', [\App\Http\Controllers\DayController::class, 'index'])->name('days.index');
Route::get('/days/{id}/show', [\App\Http\Controllers\DayController::class, 'show'])->name('days.show');




// Route::resource('submissions', \App\Http\Controllers\SubmissionController::class)->middleware('auth');
Route::get('/submissions/{id}/show', [\App\Http\Controllers\SubmissionController::class, 'show'])->name('submissions.show');
// Route::get('/submissions/create', [\App\Http\Controllers\SubmissionController::class, 'create'])->middleware(['auth', 'is_not_disabled'])->name('submissions.create');
Route::post('/submissions/store', [\App\Http\Controllers\SubmissionController::class, 'store'])->middleware(['auth', 'is_not_disabled'])->name('submissions.store');
Route::post('/submissions/{id}/comments/store', [\App\Http\Controllers\CommentController::class, 'store'])->middleware(['auth', 'is_not_disabled'])->name('submissions.comments.store');
Route::post('/submissions/{id}/comments/update', [\App\Http\Controllers\CommentController::class, 'update'])->middleware(['auth', 'is_not_disabled'])->name('submissions.comments.update');
Route::get('/submissions/{id}/report/create', [\App\Http\Controllers\BehaviourReportController::class, 'createForSubmissions'])->middleware(['auth', 'is_not_disabled'])->name('submissions.report.create');
Route::post('/submissions/{id}/report/store', [\App\Http\Controllers\BehaviourReportController::class, 'storeForSubmissions'])->middleware(['auth', 'is_not_disabled'])->name('submissions.report.store');

Route::get('/comments/{id}/report/create', [\App\Http\Controllers\BehaviourReportController::class, 'createForComments'])->middleware(['auth', 'is_not_disabled'])->name('comments.report.create');
Route::post('/comments/{id}/report/store', [\App\Http\Controllers\BehaviourReportController::class, 'storeForComments'])->middleware(['auth', 'is_not_disabled'])->name('comments.report.store');

// admin tings
Route::get('/admin/dashboard', function () {
    return Inertia::render('Admin/Dashboard');
})->middleware(['is_admin'])->name('admin.dashboard');


Route::prefix('admin')->middleware(['is_admin'])->group(function () {
    Route::get('/submissions', [\App\Http\Controllers\SubmissionController::class, 'indexUnapproved'])->name('submissions.indexUnapproved');

    Route::get('/users', [\App\Http\Controllers\UserController::class, 'adminIndex'])->name('user.adminIndex');
    Route::get('user/{id}', [\App\Http\Controllers\UserController::class, 'adminShow'])->name('user.adminShow');

    Route::get('/topics', [\App\Http\Controllers\TopicController::class, 'index'])->name('topics.index');
    Route::post('/topics/store', [\App\Http\Controllers\TopicController::class, 'store'])->name('topics.store');
    Route::post('/topics/{id}/approve', [\App\Http\Controllers\TopicController::class, 'approve'])->name('topics.approve');
    Route::put('/topics/{id}/update', [\App\Http\Controllers\TopicController::class, 'update'])->name('topics.update');

    Route::get('/behaviour-reports', [\App\Http\Controllers\BehaviourReportController::class, 'index'])->name('behaviourReports.index');
    Route::post('/behaviour-reports/{id}/update', [\App\Http\Controllers\BehaviourReportController::class, 'update'])->name('behaviourReports.update');
    Route::post('/behaviour-reports/{id}/resolve', [\App\Http\Controllers\BehaviourReportController::class, 'resolve'])->name('behaviourReports.resolve');


    Route::get('/submissions/{id}/history', [\App\Http\Controllers\SubmissionController::class, 'submissionHistory'])->name('submissions.history');
    Route::get('/comments/{id}/history', [\App\Http\Controllers\CommentController::class, 'commentHistory'])->name('comments.history');

    // Route::post('/days/edit', [\App\Http\Controllers\DayController::class, 'edit'])->name('days.edit');
    // Route::post('/days/update', [\App\Http\Controllers\DayController::class, 'update'])->name('days.update');

});




