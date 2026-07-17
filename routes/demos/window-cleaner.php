<?php

use App\Demos\WindowCleaner\Http\Controllers\Admin\BooksController;
use App\Demos\WindowCleaner\Http\Controllers\Admin\CloseMonthController;
use App\Demos\WindowCleaner\Http\Controllers\Admin\CustomerController;
use App\Demos\WindowCleaner\Http\Controllers\Admin\DashboardController;
use App\Demos\WindowCleaner\Http\Controllers\Admin\RunVisitsController;
use App\Demos\WindowCleaner\Http\Controllers\Admin\SmsController;
use App\Demos\WindowCleaner\Http\Controllers\Portal\AccountController;
use App\Demos\WindowCleaner\Http\Controllers\Portal\PaymentController;
use App\Demos\WindowCleaner\Http\Controllers\Portal\SwitchController;
use App\Demos\WindowCleaner\Http\Controllers\Tour\PlaygroundController;
use App\Demos\WindowCleaner\Http\Controllers\Tour\TourController;
use Illuminate\Support\Facades\Route;

Route::prefix('window-cleaner')->name('wc.')->group(function () {
    Route::view('/', 'demos.window-cleaner.home')->name('home');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'show'])->name('dashboard');
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::post('customers/{customer}/visits', [CustomerController::class, 'storeVisit'])->name('visits.store');
        Route::post('customers/{customer}/payments', [CustomerController::class, 'storePayment'])->name('payments.store');

        Route::post('run-visits', [RunVisitsController::class, 'store'])->name('run-visits');
        Route::get('books', [BooksController::class, 'show'])->name('books');
        Route::get('close-month', [CloseMonthController::class, 'show'])->name('close-month.show');
        Route::post('close-month', [CloseMonthController::class, 'store'])->name('close-month.store');

        Route::post('send-balance-texts', [SmsController::class, 'send'])->name('sms.send');
        Route::get('sms-outbox', [SmsController::class, 'outbox'])->name('sms.outbox');
    });

    Route::prefix('portal')->name('portal.')->group(function () {
        Route::get('/', [SwitchController::class, 'index'])->name('switch');
        Route::post('act-as/{customer}', [SwitchController::class, 'store'])->name('switch.store');
        Route::get('account', [AccountController::class, 'show'])->name('account');
        Route::get('pay', [PaymentController::class, 'create'])->name('pay');
        Route::post('pay', [PaymentController::class, 'store'])->name('pay.store');
        Route::get('paid/{payment}', [PaymentController::class, 'show'])->name('paid');
    });

    Route::prefix('tour')->name('tour.')->group(function () {
        Route::get('playground', [PlaygroundController::class, 'show'])->name('playground');
        Route::post('playground', [PlaygroundController::class, 'store'])->name('playground.store');
        Route::get('{page}', [TourController::class, 'show'])->name('show');
    });
});
