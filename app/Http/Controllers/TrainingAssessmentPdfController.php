<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateTrainingAssessmentPdfRequest;
use App\Models\TrainingAssessmentPdfLog;
use App\Services\TrainingAssessmentPdf\TrainingAssessmentPdfService;
use App\Services\TrainingAssessmentPdf\TrainingAssessmentPdfType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingAssessmentPdfController extends Controller
{
    public function __construct(
        private readonly TrainingAssessmentPdfService $service,
    ) {}

    public function generateDefault(GenerateTrainingAssessmentPdfRequest $request): JsonResponse
    {
        return $this->generate(TrainingAssessmentPdfType::Default, $request);
    }

    public function generateHrca(GenerateTrainingAssessmentPdfRequest $request): JsonResponse
    {
        return $this->generate(TrainingAssessmentPdfType::Hrca, $request);
    }

    public function generateQew(GenerateTrainingAssessmentPdfRequest $request): JsonResponse
    {
        return $this->generate(TrainingAssessmentPdfType::Qew, $request);
    }

    private function generate(TrainingAssessmentPdfType $type, GenerateTrainingAssessmentPdfRequest $request): JsonResponse
    {
        $startedAt = microtime(true);

        $payload = $request->validated();
        $title = (string) $payload['title'];
        $name = (string) $payload['name'];
        $reportHtml = (string) $payload['reportHtml'];

        $log = new TrainingAssessmentPdfLog([
            'type' => $type->value,
            'origin' => $request->headers->get('Origin'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'title' => $title,
            'name' => $name,
            'status' => 'failed',
            'request_payload' => [
                'title' => $title,
                'name' => $name,
                // Keep HTML out of DB to avoid huge rows; keep a small sample for debugging.
                'reportHtml_sample' => mb_substr($reportHtml, 0, 2000),
                'reportHtml_length' => mb_strlen($reportHtml),
            ],
        ]);
        $log->save();

        try {
            $result = $this->service->generateAndUpload($type, $title, $name, $reportHtml);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $log->status = 'success';
            $log->pdf_url = (string) $result['pdf_url'];
            $log->duration_ms = $durationMs;
            $log->save();

            return response()->json(['pdfUrl' => $log->pdf_url]);
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $log->status = 'failed';
            $log->error_message = $e->getMessage();
            $log->duration_ms = $durationMs;
            $log->save();

            return response()->json([
                'error' => 'PDF generation failed',
            ], 500);
        }
    }
}

