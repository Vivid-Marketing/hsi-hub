<?php

namespace App\Console\Commands;

use App\Models\CoursesPdfData;
use App\Services\CourseCatalogPdf\CourseCatalogPdfBuilder;
use App\Services\DoSpacesUploader;
use App\Services\Postmark\PostmarkMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CoursesPdfProcess extends Command
{
    protected $signature = 'courses:pdf-process {--limit=10 : Max rows to process per run}';
    protected $description = 'Generate Course Catalog PDFs, upload, and email links.';

    public function handle(
        CourseCatalogPdfBuilder $builder,
        DoSpacesUploader $uploader,
        PostmarkMailService $postmark
    ): int {
        $limit = (int) $this->option('limit');
        if ($limit <= 0) {
            $limit = 10;
        }

        $rows = CoursesPdfData::query()
            ->whereNull('status')
            ->orderByDesc('cpdid')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No PDF requests to process.');
            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            /** @var \App\Models\CoursesPdfData $row */
            try {
                $email = (string) ($row->email ?? '');
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $row->update(['status' => 'failed: invalid email']);
                    continue;
                }

                [$rawCourses, $wasFixed] = $this->safeUnserialize((string) ($row->serialized_data ?? ''));
                if (! is_array($rawCourses)) {
                    $row->update(['status' => 'failed: unserialize']);
                    continue;
                }
                if ($wasFixed) {
                    $this->warn("Repaired serialized lengths for cpdid {$row->cpdid}");
                }

                $librariesList = $builder->buildGroupedLibraries($rawCourses);
                $html = $builder->buildHtml($librariesList);
                $pdfBinary = $builder->renderPdfBinary($html);

                $baseName = Str::slug(str_replace(['@', '.'], '-', $email)).'-'.now()->format('Y-m-d-H-i-s').'-'.$row->cpdid;
                $pdfFilename = $baseName.'.pdf';

                $localDebugEnabled = (bool) config('course_catalog_pdf.local_debug.enabled', true);
                $localPdfPath = rtrim((string) config('course_catalog_pdf.local_debug.pdf_path'), '/');
                $localHtmlPath = rtrim((string) config('course_catalog_pdf.local_debug.html_path'), '/');

                $tmpPdf = sys_get_temp_dir().DIRECTORY_SEPARATOR.$pdfFilename;
                file_put_contents($tmpPdf, $pdfBinary);

                if ($localDebugEnabled) {
                    if (! is_dir($localPdfPath)) {
                        @mkdir($localPdfPath, 0775, true);
                    }
                    if (! is_dir($localHtmlPath)) {
                        @mkdir($localHtmlPath, 0775, true);
                    }
                    @file_put_contents($localPdfPath.DIRECTORY_SEPARATOR.$pdfFilename, $pdfBinary);
                    @file_put_contents($localHtmlPath.DIRECTORY_SEPARATOR.$baseName.'.html', $html);
                }

                $basePath = trim((string) config('course_catalog_pdf.do_spaces.base_path', 'reports/courses/pdf'), '/');
                $remoteKey = $basePath.'/'.$pdfFilename;
                $pdfUrl = $uploader->upload($tmpPdf, $remoteKey, 'application/pdf');

                $row->update([
                    'status' => 'pdf_generated',
                    'pdf_url' => $pdfUrl,
                    'pdf_generated_at' => now()->format('Y-m-d H:i:s'),
                ]);

                $result = $postmark->sendCourseCatalogPdfEmail($email, $pdfUrl);
                $row->update([
                    'status' => 'sent',
                    'email_sent_at' => now()->format('Y-m-d H:i:s'),
                    'email_message_id' => $result['MessageID'] ?? null,
                ]);

                $this->info("Processed cpdid {$row->cpdid} -> sent to {$email}");
            } catch (\Throwable $e) {
                report($e);
                $row->update(['status' => 'failed: '.$e->getMessage()]);
                $this->error("Failed cpdid {$row->cpdid}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0:mixed,1:bool} [payload, wasFixed]
     */
    private function safeUnserialize(string $data): array
    {
        $opts = ['allowed_classes' => false];
        $result = @unserialize($data, $opts);
        if ($result !== false || $data === 'b:0;') {
            return [$result, false];
        }

        $fixed = preg_replace_callback(
            '!s:(\d+):"(.*?)";!s',
            static fn ($m) => 's:'.strlen($m[2]).':"'.$m[2].'";',
            $data
        );

        $result = @unserialize((string) $fixed, $opts);

        return [$result, $result !== false];
    }
}

