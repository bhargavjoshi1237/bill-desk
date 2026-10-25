<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PrintController;
use App\Http\Middleware\SetUserLocale;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware(['auth', SetUserLocale::class])->group(function () {
    Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

    Route::prefix('print')->name('print.')->controller(PrintController::class)->group(function () {
        Route::get('/entries/{entry}/receipt', 'entryReceipt')->name('entry-receipt');
        Route::get('/payments/{payment}/receipt', 'paymentReceipt')->name('payment-receipt');
        Route::get('/customers/{customer}/statement', 'customerStatement')->name('customer-statement');
    });
});
