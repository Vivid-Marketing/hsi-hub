<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $type
 * @property string|null $origin
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string $title
 * @property string $name
 * @property string $status
 * @property string|null $pdf_url
 * @property string|null $error_message
 * @property int|null $duration_ms
 * @property array|null $request_payload
 */
class TrainingAssessmentPdfLog extends Model
{
    protected $table = 'training_assessment_pdf_logs';

    protected $fillable = [
        'type',
        'origin',
        'ip',
        'user_agent',
        'title',
        'name',
        'status',
        'pdf_url',
        'error_message',
        'duration_ms',
        'request_payload',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'duration_ms' => 'integer',
    ];
}

