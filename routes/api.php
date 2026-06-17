<?php

use App\Http\Controllers\HsiPagesController;
use Illuminate\Support\Facades\Route;

Route::prefix('hsi/pages')->group(function () {
    Route::get('/search', [HsiPagesController::class, 'search']);
    Route::get('/retrieve', [HsiPagesController::class, 'retrieve']);
    Route::get('/', [HsiPagesController::class, 'index']);
    Route::get('/{id}', [HsiPagesController::class, 'show'])->whereNumber('id');
});
