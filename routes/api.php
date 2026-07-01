<?php

use App\Http\Controllers\HsiAskController;
use App\Http\Controllers\HsiPagesController;
use Illuminate\Support\Facades\Route;

Route::middleware('hsi-ai.cors')->group(function () {
  Route::prefix('hsi')->group(function () {
    Route::options('/ask', fn () => response('', 204));
    Route::post('/ask', [HsiAskController::class, 'ask']);
    Route::options('/search', fn () => response('', 204));
    Route::get('/search', [HsiAskController::class, 'search']);
  });

  Route::prefix('hsi/pages')->group(function () {
    Route::get('/search', [HsiPagesController::class, 'search']);
    Route::get('/retrieve', [HsiPagesController::class, 'retrieve']);
    Route::get('/', [HsiPagesController::class, 'index']);
    Route::get('/{id}', [HsiPagesController::class, 'show'])->whereNumber('id');
  });
});
