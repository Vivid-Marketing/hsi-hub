<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SurveysPdfLogsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $maxEvents = (int) config('surveys_pdf.max_events', 50);

        $data = $request->json()->all();
        if (! is_array($data)) {
            Log::warning('surveys_pdf_logs.invalid_json', [
                'content_type' => (string) $request->headers->get('content-type', ''),
                'content_length' => (int) $request->headers->get('content-length', 0),
                'ip' => (string) $request->ip(),
            ]);

            return response()->json(['success' => true]);
        }

        $source = isset($data['source']) ? trim((string) $data['source']) : '';
        $clientIp = isset($data['clientIp']) ? trim((string) $data['clientIp']) : '';
        $receivedAt = $data['receivedAt'] ?? null;
        $eventsRaw = $data['events'] ?? null;

        if (! is_array($eventsRaw)) {
            Log::warning('surveys_pdf_logs.missing_events', [
                'source' => $source,
                'clientIp' => $clientIp,
                'receivedAt' => is_numeric($receivedAt) ? (int) $receivedAt : null,
                'ip' => (string) $request->ip(),
            ]);

            return response()->json(['success' => true]);
        }

        $events = array_values($eventsRaw);
        $originalCount = count($events);
        if ($originalCount > $maxEvents) {
            $events = array_slice($events, 0, $maxEvents);
        }

        $logged = 0;
        $dropped = 0;

        foreach ($events as $idx => $event) {
            if (! is_array($event)) {
                $dropped++;
                continue;
            }

            $tsMs = $event['ts'] ?? null;
            $level = isset($event['level']) ? trim((string) $event['level']) : '';
            $eventName = isset($event['event']) ? trim((string) $event['event']) : '';
            $survey = isset($event['survey']) ? trim((string) $event['survey']) : '';
            $page = isset($event['page']) ? trim((string) $event['page']) : null;
            $path = isset($event['path']) ? trim((string) $event['path']) : '';
            $context = is_array($event['context'] ?? null) ? $event['context'] : [];

            if (! is_numeric($tsMs) || $eventName === '' || $survey === '' || $path === '') {
                $dropped++;
                continue;
            }

            $visitorId = isset($context['visitorId']) ? trim((string) $context['visitorId']) : null;

            $logContext = [
                'source' => $source !== '' ? $source : null,
                'clientIp' => $clientIp !== '' ? $clientIp : null,
                'receivedAt' => is_numeric($receivedAt) ? (int) $receivedAt : null,
                'eventIndex' => (int) $idx,
                'ts' => (int) $tsMs,
                'level' => $level !== '' ? $level : null,
                'event' => $eventName,
                'survey' => $survey,
                'page' => $page !== '' ? $page : null,
                'path' => $path,
                'visitorId' => $visitorId !== '' ? $visitorId : null,
                // Keep context bounded: include only small, useful search facets.
                'userAgent' => isset($context['userAgent']) ? substr((string) $context['userAgent'], 0, 300) : null,
                'language' => isset($context['language']) ? substr((string) $context['language'], 0, 32) : null,
                'platform' => isset($context['platform']) ? substr((string) $context['platform'], 0, 64) : null,
                'timezone' => isset($context['timezone']) ? substr((string) $context['timezone'], 0, 64) : null,
            ];

            // Best-effort: include a compact hint for pdf_fetch_error.
            if ($eventName === 'pdf_fetch_error' && isset($context['responseInfo'])) {
                $ri = $context['responseInfo'];
                $logContext['responseInfo'] = is_scalar($ri) ? substr((string) $ri, 0, 500) : null;
            }

            // Emit an event-level log line to make searching easy.
            if ($level === 'error') {
                Log::error('surveys_pdf_event', $logContext);
            } else {
                Log::info('surveys_pdf_event', $logContext);
            }

            $logged++;
        }

        Log::info('surveys_pdf_batch', [
            'source' => $source !== '' ? $source : null,
            'clientIp' => $clientIp !== '' ? $clientIp : null,
            'receivedAt' => is_numeric($receivedAt) ? (int) $receivedAt : null,
            'eventsReceived' => $originalCount,
            'eventsProcessed' => count($events),
            'eventsLogged' => $logged,
            'eventsDropped' => $dropped + max(0, $originalCount - count($events)),
            'ip' => (string) $request->ip(),
        ]);

        return response()->json(['success' => true]);
    }
}

