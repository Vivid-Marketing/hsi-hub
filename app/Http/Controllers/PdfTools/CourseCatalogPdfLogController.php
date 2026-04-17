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
            ->selectRaw('job_id, MAX(date_entered) AS last_seen, MIN(total_batches) AS total_batches, SUM(status = "pending") AS pending_batches, SUM(status = "processed") AS processed_batches, SUM(status = "failed") AS failed_batches, MAX(email) AS email, MAX(stitched_cpdid) AS stitched_cpdid, MAX(processed_at) AS processed_at, MAX(error_message) AS error_message')
            ->groupBy('job_id')
            ->orderByDesc('last_seen')
            ->limit(50)
            ->get();

        [$schedulerLogMtime, $schedulerLogTail] = $this->readSchedulerLogTail();

        return view('pdf-tools.course-catalog-log', [
            'recentData' => $recentData,
            'recentBatchJobs' => $recentBatchJobs,
            'schedulerLogMtime' => $schedulerLogMtime,
            'schedulerLogTail' => $schedulerLogTail,
        ]);
    }

    /**
     * @return array{0:?string,1:?string} [mtime, tail]
     */
    private function readSchedulerLogTail(): array
    {
        $path = storage_path('logs/scheduler.log');
        if (! is_file($path)) {
            return [null, null];
        }

        $mtime = @filemtime($path);
        $mtimeIso = $mtime ? date('Y-m-d H:i:s', $mtime) : null;

        $size = @filesize($path);
        if (! is_int($size) || $size <= 0) {
            return [$mtimeIso, null];
        }

        $maxBytes = 12_000;
        $start = max(0, $size - $maxBytes);

        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return [$mtimeIso, null];
        }

        try {
            @fseek($fh, $start);
            $data = stream_get_contents($fh);
        } finally {
            fclose($fh);
        }

        $data = is_string($data) ? $data : '';
        $data = ltrim($data, "\n\r\0\x0B");

        return [$mtimeIso, $data !== '' ? $data : null];
    }
}

