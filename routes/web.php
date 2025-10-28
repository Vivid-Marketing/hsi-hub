<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\PdfToolsController;
use App\Http\Controllers\Mp3ToolsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Courses routes
    Route::resource('courses', CoursesController::class);
    
    // PDF Tools routes
    Route::get('/pdf-tools', [PdfToolsController::class, 'index'])->name('pdf-tools.index');
    Route::get('/pdf-tools/create', [PdfToolsController::class, 'create'])->name('pdf-tools.create');
    Route::post('/pdf-tools/generate', [PdfToolsController::class, 'generate'])->name('pdf-tools.generate');
    
    // MP3 Tools routes
    Route::get('/mp3-tools', [Mp3ToolsController::class, 'index'])->name('mp3-tools.index');
    Route::post('/mp3-tools/extract', [Mp3ToolsController::class, 'extractMp3Url'])->name('mp3-tools.extract');
});

require __DIR__.'/auth.php';
