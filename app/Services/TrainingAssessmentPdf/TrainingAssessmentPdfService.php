<?php

namespace App\Services\TrainingAssessmentPdf;

use Illuminate\Support\Str;

class TrainingAssessmentPdfService
{
    public function __construct(
        private readonly TrainingAssessmentPdfRenderer $renderer,
        private readonly TrainingAssessmentPdfUploader $uploader,
    ) {}

    public function generateAndUpload(
        TrainingAssessmentPdfType $type,
        string $title,
        string $name,
        string $reportHtml,
    ): array {
        $safeName = trim(str_replace(' ', '-', $name));
        $safeName = $safeName !== '' ? $safeName : 'unknown';
        $safeName = Str::limit(preg_replace('/[^A-Za-z0-9\-_]+/', '-', $safeName) ?? 'unknown', 80, '');

        $filename = $this->makeLegacyishFilename($type, $safeName);

        $html = $this->renderer->buildHtml($type, $title, $reportHtml);
        $pdfBinary = $this->renderer->renderPdfBinary($html);

        $localPath = $this->writeLocalPdf($filename, $pdfBinary);

        $basePath = (string) config('training_assessment_pdf.do_spaces.base_path', 'reports/training-assessments/pdf');
        $remoteKey = rtrim($basePath, '/').'/'.$filename.'.pdf';

        $url = $this->uploader->upload($localPath, $remoteKey, 'application/pdf');

        return [
            'filename' => $filename,
            'pdf_url' => $url,
            'local_path' => $localPath,
        ];
    }

    private function makeLegacyishFilename(TrainingAssessmentPdfType $type, string $safeName): string
    {
        $date = match ($type) {
            // HRCA legacy used Y-m-d:h:i:s (with colons); keep similar but avoid colon for portability.
            TrainingAssessmentPdfType::Hrca => now()->format('Y-m-d-H-i-s'),
            default => now()->format('Y-m-d-H-i'),
        };

        return match ($type) {
            TrainingAssessmentPdfType::Hrca => 'HRCA-'.$safeName.'-'.$date,
            TrainingAssessmentPdfType::Qew => 'HSI-QEW-training-assessment-report-'.$safeName.'-'.$date,
            default => 'HSI-T4C-training-assessment-report-'.$safeName.'-'.$date,
        };
    }

    private function writeLocalPdf(string $filename, string $pdfBinary): string
    {
        $debugEnabled = (bool) config('training_assessment_pdf.local_debug.enabled', true);
        $dir = (string) config('training_assessment_pdf.local_debug.pdf_path', storage_path('app/reports/training-assessments/pdf'));

        if (! $debugEnabled) {
            $dir = storage_path('app/tmp/training-assessments/pdf');
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = rtrim($dir, '/').'/'.$filename.'.pdf';
        file_put_contents($path, $pdfBinary);

        return $path;
    }
}

