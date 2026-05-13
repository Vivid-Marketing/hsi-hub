<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_ts_ms
 * @property int|null $received_at_unix
 * @property string|null $source
 * @property string|null $client_ip
 * @property string|null $hub_ip
 * @property string|null $level
 * @property string $event_type
 * @property string $survey
 * @property string|null $page
 * @property string $path
 * @property string|null $visitor_id
 * @property string|null $user_agent
 * @property array|null $extras
 */
class SurveysPdfLogEvent extends Model
{
    protected $table = 'surveys_pdf_log_events';

    protected $fillable = [
        'event_ts_ms',
        'received_at_unix',
        'source',
        'client_ip',
        'hub_ip',
        'level',
        'event_type',
        'survey',
        'page',
        'path',
        'visitor_id',
        'user_agent',
        'extras',
    ];

    protected $casts = [
        'event_ts_ms' => 'integer',
        'received_at_unix' => 'integer',
        'extras' => 'array',
    ];

    public function getClientOccurredAtAttribute(): Carbon
    {
        return Carbon::createFromTimestampMs((int) $this->event_ts_ms)
            ->timezone((string) config('app.timezone'));
    }
}
