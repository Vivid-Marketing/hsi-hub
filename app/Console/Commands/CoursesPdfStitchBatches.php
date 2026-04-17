<?php

namespace App\Console\Commands;

use App\Models\CoursesPdfBatch;
use App\Models\CoursesPdfData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CoursesPdfStitchBatches extends Command
{
    protected $signature = 'courses:pdf-stitch-batches {--limit=5 : Max jobs to stitch per run}';
    protected $description = 'Stitch complete Course Catalog PDF batches into a single payload.';

    public function handle(): int
    {
        $jobLimit = (int) $this->option('limit');
        if ($jobLimit <= 0) {
            $jobLimit = 5;
        }

        $readyJobs = CoursesPdfBatch::query()
            ->selectRaw('job_id, MIN(total_batches) AS total_batches, COUNT(*) AS pending_batches, MAX(email) AS email, MIN(date_entered) AS first_seen')
            ->where('status', 'pending')
            ->groupBy('job_id')
            // Avoid relying on SELECT aliases in HAVING/ORDER BY (varies across MySQL/MariaDB modes).
            ->havingRaw('COUNT(*) = MIN(total_batches)')
            ->orderByRaw('MIN(date_entered) ASC')
            ->limit($jobLimit)
            ->get();

        if ($readyJobs->isEmpty()) {
            $this->info('No complete batch jobs ready for stitching.');
            return self::SUCCESS;
        }

        foreach ($readyJobs as $job) {
            $jobId = (string) $job->job_id;
            $expected = (int) $job->total_batches;

            DB::beginTransaction();
            try {
                $batches = CoursesPdfBatch::query()
                    ->where('job_id', $jobId)
                    ->where('status', 'pending')
                    ->orderBy('batch_index')
                    ->lockForUpdate()
                    ->get();

                if ($batches->count() !== $expected) {
                    $this->markBatchJobFailed($jobId, "Expected {$expected} batches but found ".$batches->count());
                    DB::commit();
                    continue;
                }

                $emails = $batches->pluck('email')->unique()->values();
                if ($emails->count() !== 1 || ! filter_var((string) $emails[0], FILTER_VALIDATE_EMAIL)) {
                    $this->markBatchJobFailed($jobId, 'Invalid or mismatched email addresses across batches');
                    DB::commit();
                    continue;
                }
                $email = (string) $emails[0];

                $combined = [];
                $failed = [];

                foreach ($batches as $row) {
                    [$payload, $wasFixed] = $this->safeUnserialize((string) $row->serialized_data);
                    if (! is_array($payload)) {
                        $failed[] = (int) $row->batch_index;
                        continue;
                    }
                    if ($wasFixed) {
                        $this->warn("Repaired serialized lengths for job {$jobId} batch {$row->batch_index}");
                    }
                    $combined = array_merge($combined, $payload);
                }

                if (! empty($failed)) {
                    $this->markBatchJobFailed($jobId, 'Failed to unserialize batches: '.implode(', ', $failed));
                    DB::commit();
                    continue;
                }

                $cpd = CoursesPdfData::query()->create([
                    'serialized_data' => serialize($combined),
                    'date_entered' => now()->format('Y-m-d H:i:s'),
                    'email' => $email,
                    'status' => null,
                ]);

                CoursesPdfBatch::query()
                    ->where('job_id', $jobId)
                    ->update([
                        'status' => 'processed',
                        'processed_at' => now()->format('Y-m-d H:i:s'),
                        'stitched_cpdid' => $cpd->cpdid,
                        'error_message' => null,
                    ]);

                DB::commit();
                $this->info("Stitched {$expected} batches for job {$jobId} into cpdid {$cpd->cpdid}");
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Failed stitching job {$jobId}: ".$e->getMessage());
                $this->markBatchJobFailed($jobId, 'Stitching exception: '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function markBatchJobFailed(string $jobId, string $message): void
    {
        CoursesPdfBatch::query()
            ->where('job_id', $jobId)
            ->where('status', 'pending')
            ->update([
                'status' => 'failed',
                'error_message' => $message,
                'processed_at' => now()->format('Y-m-d H:i:s'),
            ]);
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

