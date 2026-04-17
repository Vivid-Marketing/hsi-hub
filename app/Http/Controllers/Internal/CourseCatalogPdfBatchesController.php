<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\CoursesPdfBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseCatalogPdfBatchesController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->json()->all();
        if (! is_array($data)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid JSON payload',
            ], 400);
        }

        $jobId = isset($data['jobId']) ? trim((string) $data['jobId']) : '';
        $batchIndex = $data['batchIndex'] ?? null;
        $totalBatches = $data['totalBatches'] ?? null;
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $coursesPayload = $data['courses'] ?? null;

        if ($jobId === '' || strlen($jobId) > 128) {
            return response()->json(['status' => 'error', 'message' => 'Missing or invalid jobId'], 422);
        }
        if (! is_numeric($batchIndex) || (int) $batchIndex < 0) {
            return response()->json(['status' => 'error', 'message' => 'Missing or invalid batchIndex'], 422);
        }
        if (! is_numeric($totalBatches) || (int) $totalBatches <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Missing or invalid totalBatches'], 422);
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['status' => 'error', 'message' => 'Missing or invalid email'], 422);
        }

        $batchIndex = (int) $batchIndex;
        $totalBatches = (int) $totalBatches;
        if ($batchIndex >= $totalBatches) {
            return response()->json(['status' => 'error', 'message' => 'batchIndex must be less than totalBatches'], 422);
        }

        if (is_string($coursesPayload)) {
            $coursesData = json_decode($coursesPayload, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($coursesData)) {
                return response()->json(['status' => 'error', 'message' => 'Invalid courses JSON'], 422);
            }
        } elseif (is_array($coursesPayload)) {
            $coursesData = $coursesPayload;
        } else {
            return response()->json(['status' => 'error', 'message' => '"courses" must be an array or JSON string'], 422);
        }

        $serializedData = serialize($coursesData);
        $now = now()->format('Y-m-d H:i:s');

        CoursesPdfBatch::updateOrCreate(
            [
                'job_id' => $jobId,
                'batch_index' => $batchIndex,
            ],
            [
                'total_batches' => $totalBatches,
                'email' => $email,
                'serialized_data' => $serializedData,
                'date_entered' => $now,
                'status' => 'pending',
                'error_message' => null,
                'processed_at' => null,
                'stitched_cpdid' => null,
            ]
        );

        $storedCount = CoursesPdfBatch::query()->where('job_id', $jobId)->count();
        $pendingCount = CoursesPdfBatch::query()->where('job_id', $jobId)->where('status', 'pending')->count();
        $isComplete = $pendingCount >= $totalBatches;

        return response()->json([
            'status' => 'success',
            'message' => 'Batch stored',
            'jobId' => $jobId,
            'batchIndex' => $batchIndex,
            'totalBatches' => $totalBatches,
            'storedBatches' => $storedCount,
            'pendingBatches' => $pendingCount,
            'isComplete' => $isComplete,
        ]);
    }
}

