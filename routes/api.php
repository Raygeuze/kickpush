<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\StripeWebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// voting routes
Route::put('/submission/{id}/vote', [\App\Http\Controllers\SubmissionController::class, 'vote'])->middleware(['auth', 'is_not_disabled']);
Route::put('/submission/{id}/remove-vote', [\App\Http\Controllers\SubmissionController::class, 'removeVote'])->middleware(['auth', 'is_not_disabled']);
Route::put('/submission/{id}/update', [\App\Http\Controllers\SubmissionController::class, 'update'])->middleware(['auth', 'is_not_disabled'])->name('api.submission.update');

Route::post('/comments/{commentId}/like', [\App\Http\Controllers\CommentController::class, 'like'])->middleware(['auth', 'is_not_disabled'])->name('comments.like');
Route::post('/comments/{commentId}/dislike', [\App\Http\Controllers\CommentController::class, 'dislike'])->middleware(['auth', 'is_not_disabled'])->name('comments.dislike');


// day submissions
Route::get('/day/{id}/submissions', [\App\Http\Controllers\DayController::class, 'daySubmissions']);


// admin activities
Route::put('/admin/submissions/{id}/approve', [\App\Http\Controllers\SubmissionController::class, 'approve'])
    ->middleware(['is_admin'])
    ->name('submissions.approve');

Route::put('/admin/submissions/{id}/disapprove', [\App\Http\Controllers\SubmissionController::class, 'disapprove'])
    ->middleware(['is_admin'])
    ->name('submissions.disapprove');

Route::put('/admin/user/{id}/disable', [\App\Http\Controllers\UserController::class, 'disable'])
    ->middleware(['is_admin'])
    ->name('admin.user.disable');
Route::put('/admin/user/{id}/enable', [\App\Http\Controllers\UserController::class, 'enable'])
    ->middleware(['is_admin'])
    ->name('admin.user.enable');

Route::get('countries', [\App\Http\Controllers\CountryController::class, 'getCountries']);


//stripe
Route::get('/account_session', [StripeController::class, 'createAccountSession'])->middleware(['auth', 'is_not_disabled'])->name('stripe.account_session');

Route::post('payment/initiate', [StripeController::class, 'initiatePayment'])->middleware(['auth', 'is_not_disabled'])->name('stripe.payment.initiate');
Route::post('payment/complete', [StripeController::class, 'completePayment'])->middleware(['auth', 'is_not_disabled'])->name('stripe.payment.complete');
Route::post('payment/failure', [StripeController::class, 'failPayment'])->middleware(['auth', 'is_not_disabled'])->name('stripe.payment.failure');

// Stripe webhooks
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->withoutMiddleware(Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
