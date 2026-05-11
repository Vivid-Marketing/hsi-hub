<?php

use App\Http\Controllers\CldFeedsController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Mp3ToolsController;
use App\Http\Controllers\PdfToolsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RichTextToolsController;
use App\Http\Controllers\TrainingAssessmentPdfController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Internal\CourseCatalogPdfBatchesController;
use App\Http\Controllers\Internal\CronScheduleController;
use App\Http\Controllers\Internal\SurveysPdfLogsController;
use App\Http\Controllers\PdfTools\CourseCatalogPdfLogController;
use App\Http\Controllers\PdfTools\TrainingAssessmentPdfLogController;
use Illuminate\Support\Facades\Route;

// Public CLD JSON feeds (for Craft FeedMe)
Route::prefix('feeds/cld')->group(function () {
    Route::get('/courses', [CldFeedsController::class, 'courses'])->name('feeds.cld.courses');
    Route::get('/courses/singles', [CldFeedsController::class, 'singles'])->name('feeds.cld.courses.singles');
});

// Internal endpoint for Course Catalog PDF batch ingestion (signed request).
Route::post('/internal/course-catalog-pdf/batches', [CourseCatalogPdfBatchesController::class, 'store'])
    ->middleware('internal.signature')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->name('internal.course-catalog-pdf.batches.store');

// Internal endpoint for batched Survey PDF logging events (signed request).
Route::post('/internal/surveys-pdf/logs', [SurveysPdfLogsController::class, 'store'])
    ->middleware('surveys-pdf.signature')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->name('internal.surveys-pdf.logs.store');

// Internal endpoint to run Laravel scheduler via a single cron hit.
Route::get('/internal/cron/schedule', [CronScheduleController::class, 'run'])
    ->middleware('cron.token')
    ->name('internal.cron.schedule');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
});

// Public endpoints for Training Assessment report PDFs (legacy generateReportHtml*.php).
Route::prefix('reports')->middleware(['training-assessment-pdf.cors'])->group(function () {
    // Preflight support (so our CORS middleware actually runs on OPTIONS).
    Route::options('/generate-report-html', fn () => response('', 204))
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    Route::options('/generate-report-html-hrca', fn () => response('', 204))
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    Route::options('/generate-report-html-qew', fn () => response('', 204))
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

    Route::post('/generate-report-html', [TrainingAssessmentPdfController::class, 'generateDefault'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
        ->name('reports.training-assessment.generate-default');

    Route::post('/generate-report-html-hrca', [TrainingAssessmentPdfController::class, 'generateHrca'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
        ->name('reports.training-assessment.generate-hrca');

    Route::post('/generate-report-html-qew', [TrainingAssessmentPdfController::class, 'generateQew'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
        ->name('reports.training-assessment.generate-qew');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Courses routes (explicit middleware; avoids legacy $this->middleware() on base controller issues)
    Route::get('/courses', [CoursesController::class, 'index'])
        ->middleware('permission:view-courses')
        ->name('courses.index');
    Route::post('/courses/cld/process-singles', [CoursesController::class, 'processSingles'])
        ->name('courses.process-singles');
    Route::get('/courses/{course}', [CoursesController::class, 'show'])
        ->middleware('permission:view-courses')
        ->name('courses.show');
    Route::get('/courses/{course}/edit', [CoursesController::class, 'edit'])
        ->middleware('permission:edit-courses')
        ->name('courses.edit');
    Route::match(['put', 'patch'], '/courses/{course}', [CoursesController::class, 'update'])
        ->middleware('permission:edit-courses')
        ->name('courses.update');
    Route::delete('/courses/{course}', [CoursesController::class, 'destroy'])
        ->middleware('permission:delete-courses')
        ->name('courses.destroy');

    // PDF Tools routes
    Route::get('/pdf-tools', [PdfToolsController::class, 'index'])->name('pdf-tools.index');
    Route::get('/pdf-tools/create', [PdfToolsController::class, 'create'])->name('pdf-tools.create');
    Route::post('/pdf-tools/generate', [PdfToolsController::class, 'generate'])->name('pdf-tools.generate');
    Route::get('/pdf-tools/course-catalog-pdf', [CourseCatalogPdfLogController::class, 'index'])->name('pdf-tools.course-catalog-pdf.index');
    Route::get('/pdf-tools/training-assessment', [TrainingAssessmentPdfLogController::class, 'index'])->name('pdf-tools.training-assessment.index');

    // MP3 Tools routes
    Route::get('/mp3-tools', [Mp3ToolsController::class, 'index'])->name('mp3-tools.index');
    Route::post('/mp3-tools/extract', [Mp3ToolsController::class, 'extractMp3Url'])->name('mp3-tools.extract');
    Route::get('/mp3-tools/diagnose', [Mp3ToolsController::class, 'diagnose'])->name('mp3-tools.diagnose');

    // Rich Text Tools routes
    Route::prefix('rich-text-tools')->name('rich-text-tools.')->group(function () {
        Route::get('/', [RichTextToolsController::class, 'index'])->name('index');
        Route::post('/clean-links', [RichTextToolsController::class, 'cleanLinks'])->name('clean-links');
    });

    // User Management routes
    Route::resource('users', UserController::class);
});

require __DIR__.'/auth.php';
