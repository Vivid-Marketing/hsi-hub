<?php

use App\Http\Controllers\HsiPagesController;
use Illuminate\Support\Facades\Route;

Route::get('/hsi/pages/search', [HsiPagesController::class, 'search']);

