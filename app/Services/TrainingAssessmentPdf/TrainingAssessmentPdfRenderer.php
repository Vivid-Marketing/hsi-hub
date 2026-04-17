<?php

namespace App\Services\TrainingAssessmentPdf;

use Dompdf\Dompdf;
use Dompdf\Options;

class TrainingAssessmentPdfRenderer
{
    public function buildHtml(TrainingAssessmentPdfType $type, string $title, string $reportHtml): string
    {
        $titleEscaped = e($title);

        // Match legacy templates closely (including remote assets/CSS).
        $styles = match ($type) {
            TrainingAssessmentPdfType::Hrca => implode("\n", [
                '<link rel="stylesheet" type="text/css" media="screen" href="https://apismd.hsi.com/reports/assets/styles-hrca.css" />',
                '<link rel="stylesheet" type="text/css" media="screen" href="https://apismd.hsi.com/reports/assets/styles-hrca-appended.css" />',
            ]),
            default => '<link rel="stylesheet" type="text/css" media="screen" href="https://apismd.hsi.com/reports/assets/styles.css" />'
                ."\n".$this->defaultInlineStyles(),
        };

        $body = <<<HTML
<body class="survey-entry survey-t4c form-loaded">
    <div class="logo">
        <img src="https://apismd.hsi.com/reports/assets/hsi-logo.png">
    </div>
    <div class="survey-title">
        {$titleEscaped}
    </div>
    <div class="survey-wrapper">
        <div id="smartwizard" dir="" class="sw sw-theme-basic sw-justified">
            <div class="tab-content">
                <div id="step-21" data-page-num="21" class="tab-pane report-pane" role="tabpanel" aria-labelledby="step-21" style="">
                    {$reportHtml}
                </div>
            </div>
        </div>
    </div>
</body>
HTML;

        $html = <<<HTML
<html lang="en" style="--sw-progress-width: 100%;">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&amp;display=swap" rel="stylesheet">
    <title>HSI | {$titleEscaped}</title>
    {$styles}
</head>
{$body}
</html>
HTML;

        // Legacy substitution to avoid SVG rendering issues in Dompdf.
        return str_replace(
            'https://hsiassetstorage.sfo2.digitaloceanspaces.com/assets/images/solutions/homeIcons/safety-data-sheets-icon.svg',
            'https://apismd.hsi.com/reports/assets/safety-data-sheets-icon.jpg',
            $html
        );
    }

    public function renderPdfBinary(string $html): string
    {
        [$fontDir, $fontCacheDir, $tempDir] = $this->prepareWritableRuntimeDirs();

        $options = new Options();
        $options->set([
            'isRemoteEnabled' => true,
            // Cloudways/shared hosting often has read-only vendor/; font cache must be writable.
            'fontDir' => $fontDir,
            'fontCache' => $fontCacheDir,
            'tempDir' => $tempDir,
        ]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function prepareWritableRuntimeDirs(): array
    {
        $base = storage_path('app/dompdf');
        $fontDir = $base.'/fonts';
        $fontCache = $base.'/font-cache';
        $temp = $base.'/temp';

        foreach ([$base, $fontDir, $fontCache, $temp] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }

        return [$fontDir, $fontCache, $temp];
    }

    private function defaultInlineStyles(): string
    {
        return <<<HTML
<style>
html {
    margin: 20px;
}
.bg-image {
    display: none !important;
}
.survey-entry .survey-wrapper .tab-content .tab-pane.report-pane .report-results .question-report-inner .question-frequencies span {
    margin: 5px;
}
.survey-entry .survey-wrapper .tab-content .tab-pane.report-pane .report-results .question-report-inner .question-report-courses .course-link a {
    margin: 5px 2px !important;
}
.report-summary-meta div {
    margin: 5px;
}
.survey-entry .survey-wrapper .tab-content .tab-pane.report-pane .report-results .question-report-inner .question-regulations span {
    margin: 5px 2px !important;
}
.logo {
    text-align: center;
    display: block;
    width: 100%;
}
.survey-entry .survey-wrapper .tab-content .tab-pane.report-pane .report-results .question-report-inner .question-question ul {
    list-style-type: disc;
    padding-inline-start: 25px;
}
</style>
HTML;
    }
}

