<?php

namespace App\Services\Cld;

use App\Services\Postmark\PostmarkMailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CldSyncNotifier
{
    public function __construct(
        protected PostmarkMailService $mail,
    ) {}

    public function notify(CldSyncResult $result): void
    {
        $to = $this->recipient();
        if ($to === null) {
            if ($result->hasIssues()) {
                Log::warning('CLD sync finished with issues but CLD_SYNC_NOTIFY_EMAIL is not set', $this->resultLogContext($result));
            }

            return;
        }

        if (! $result->hasIssues() && ! config('cld_api.notify.on_success')) {
            return;
        }

        if (empty(config('postmark.token'))) {
            Log::warning('CLD sync notification skipped: POSTMARK_API_KEY not set', $this->resultLogContext($result));

            return;
        }

        $subject = $result->hasIssues()
            ? '[Hub] CLD sync completed with issues'
            : '[Hub] CLD sync completed';

        $text = $this->buildTextBody($result);
        $html = '<pre style="font-family:ui-monospace,monospace;font-size:13px;white-space:pre-wrap;">'.e($text).'</pre>';

        try {
            $this->mail->sendNotification($to, $subject, $html, $text);
        } catch (\Throwable $e) {
            Log::error('CLD sync notification email failed to send', [
                'error' => $e->getMessage(),
                'context' => $this->resultLogContext($result),
            ]);
        }
    }

    public function notifyException(\Throwable $e, string $context = 'CLD sync'): void
    {
        $to = $this->recipient();
        if ($to === null) {
            Log::error($context.' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return;
        }

        if (empty(config('postmark.token'))) {
            Log::error($context.' failed (Postmark not configured)', ['exception' => $e->getMessage()]);

            return;
        }

        $subject = '[Hub] '.$context.' failed';
        $text = $context.PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL.Str::limit($e->getTraceAsString(), 4000);
        $html = '<pre style="font-family:ui-monospace,monospace;font-size:13px;white-space:pre-wrap;">'.e($text).'</pre>';

        try {
            $this->mail->sendNotification($to, $subject, $html, $text);
        } catch (\Throwable $sendError) {
            Log::error('CLD failure notification email could not be sent', [
                'original' => $e->getMessage(),
                'send_error' => $sendError->getMessage(),
            ]);
        }
    }

    protected function recipient(): ?string
    {
        $email = config('cld_api.notify.email');

        return is_string($email) && $email !== '' ? trim($email) : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resultLogContext(CldSyncResult $result): array
    {
        return [
            'mode' => $result->mode,
            'total' => $result->totalIds,
            'succeeded' => $result->succeeded,
            'failure_count' => count($result->failures),
            'abort' => $result->abortReason,
            'feedme_ok' => $result->feedMe['ok'] ?? null,
        ];
    }

    protected function buildTextBody(CldSyncResult $result): string
    {
        $lines = [];
        $lines[] = 'CLD sync report';
        $lines[] = 'Mode: '.$result->mode;
        $lines[] = 'Lesson IDs in run: '.$result->totalIds;
        $lines[] = 'Succeeded: '.$result->succeeded;
        if ($result->abortReason !== null) {
            $lines[] = 'Aborted: '.$result->abortReason;
        }
        if ($result->failures !== []) {
            $lines[] = '';
            $lines[] = 'Failures ('.count($result->failures).'):';
            foreach ($result->failures as $row) {
                $lines[] = '  - Lesson '.$row['lesson_id'].': '.$row['reason'];
            }
        }
        if ($result->feedMe !== null) {
            $lines[] = '';
            $lines[] = 'FeedMe HTTP: '.($result->feedMe['http_code'] ?? '?');
            $lines[] = 'FeedMe OK: '.(($result->feedMe['ok'] ?? false) ? 'yes' : 'no');
            if (! empty($result->feedMe['suspected_login_interstitial'])) {
                $lines[] = 'Warning: response looked like a login or control-panel page (FeedMe may not have run).';
            }
            if (! empty($result->feedMe['error'])) {
                $lines[] = 'Transport error: '.$result->feedMe['error'];
            }
            if (! empty($result->feedMe['response_excerpt'])) {
                $lines[] = 'Response excerpt: '.$result->feedMe['response_excerpt'];
            }
        }

        $lines[] = '';
        $lines[] = 'App: '.config('app.url');

        return implode("\n", $lines);
    }
}
