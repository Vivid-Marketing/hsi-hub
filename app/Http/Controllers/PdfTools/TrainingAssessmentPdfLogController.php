<?php

namespace App\Http\Controllers\PdfTools;

use App\Http\Controllers\Controller;
use App\Models\TrainingAssessmentPdfLog;
use Illuminate\View\View;

class TrainingAssessmentPdfLogController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->middleware('permission:view-pdf-tools');
    }

    public function index(): View
    {
        $recent = TrainingAssessmentPdfLog::query()
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('pdf-tools.training-assessment-log', [
            'recent' => $recent,
        ]);
    }
}

