<?php

namespace App\Http\Controllers\PdfTools;

use App\Http\Controllers\Controller;
use App\Models\CoursesPdfBatch;
use App\Models\CoursesPdfData;
use Illuminate\View\View;

class CourseCatalogPdfLogController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->middleware('permission:view-pdf-tools');
    }

    public function index(): View
    {
        $recentData = CoursesPdfData::query()
            ->orderByDesc('cpdid')
            ->limit(50)
            ->get();

        $recentBatchJobs = CoursesPdfBatch::query()
            ->selectRaw('job_id, MAX(date_entered) AS last_seen, MIN(total_batches) AS total_batches, SUM(status = "pending") AS pending_batches, SUM(status = "processed") AS processed_batches, SUM(status = "failed") AS failed_batches, MAX(email) AS email')
            ->groupBy('job_id')
            ->orderByDesc('last_seen')
            ->limit(50)
            ->get();

        return view('pdf-tools.course-catalog-log', [
            'recentData' => $recentData,
            'recentBatchJobs' => $recentBatchJobs,
        ]);
    }
}

