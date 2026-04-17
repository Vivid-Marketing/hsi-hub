<?php

namespace App\Services\Postmark;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

/**
 * Postmark email sending (HTTP API). Reusable for Courses Parser, job notifications, and other tools.
 *
 * @see https://postmarkapp.com/developer/api/email-api
 */
class PostmarkMailService
{
    protected const SEND_ENDPOINT = 'https://api.postmarkapp.com/email';

    /**
     * Low-level send (HTML + plain text).
     *
     * @return array<string, mixed> Decoded Postmark JSON (includes MessageID on success)
     *
     * @throws \RuntimeException On missing config or API error
     */
    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        ?string $from = null,
        ?string $replyTo = null,
        ?string $messageStream = null
    ): array {
        $token = config('postmark.token');
        if (empty($token)) {
            throw new \RuntimeException('Postmark is not configured. Set POSTMARK_API_KEY in .env.');
        }

        $from = $from ?? config('postmark.from');
        if (empty($from)) {
            throw new \RuntimeException('Postmark from address is not configured. Set POSTMARK_FROM_EMAIL in .env.');
        }

        $payload = [
            'From' => $from,
            'To' => $to,
            'Subject' => $subject,
            'HtmlBody' => $htmlBody,
            'TextBody' => $textBody,
            'MessageStream' => $messageStream ?? config('postmark.message_stream', 'outbound'),
        ];

        if (! empty($replyTo)) {
            $payload['ReplyTo'] = $replyTo;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Postmark-Server-Token' => $token,
        ])->timeout(30)->post(self::SEND_ENDPOINT, $payload);

        $data = $response->json() ?? [];

        if (! $response->successful()) {
            $msg = $data['Message'] ?? $response->body();
            throw new \RuntimeException('Postmark API error: '.$msg);
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Plain notification (e.g. “job finished”) — use from any queued job.
     */
    public function sendNotification(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        ?string $from = null
    ): array {
        return $this->send($to, $subject, $htmlBody, $textBody, $from);
    }

    /**
     * Render the legacy “course catalog PDF ready” HTML (Blade template).
     */
    public function renderCourseCatalogPdfHtml(string $pdfUrl, ?string $documentTitle = null): string
    {
        $title = $documentTitle ?? 'HSI Course Catalog PDF';

        return View::make('emails.postmark.course-catalog-pdf', [
            'title' => $title,
            'pdfUrl' => $pdfUrl,
            'logoUrl' => config('postmark.course_catalog_logo_url'),
            'contactUrl' => config('postmark.contact_url'),
            'hsiHomeUrl' => config('postmark.hsi_home_url'),
        ])->render();
    }

    /**
     * Plain-text body matching legacy behavior (minus HTML-specific bits).
     */
    public function courseCatalogPdfTextBody(string $pdfUrl): string
    {
        return "Thank you for your interest! We've generated a PDF containing the current list of courses you were viewing. You can download it using the link below:\n\n"
            .$pdfUrl
            ."\n\nWe hope this makes it easier for you to review and share the available courses. If you have any questions or need help finding the right course, feel free to reach out.";
    }

    /**
     * Send the same email as legacy CoursesParser::processPdfJobs (Postmark + catalog template).
     */
    public function sendCourseCatalogPdfEmail(
        string $to,
        string $pdfUrl,
        ?string $subject = null,
        ?string $from = null
    ): array {
        $subject = $subject ?? 'HSI Courses Catalog - Download PDF';
        $html = $this->renderCourseCatalogPdfHtml($pdfUrl);
        $text = $this->courseCatalogPdfTextBody($pdfUrl);

        return $this->send($to, $subject, $html, $text, $from);
    }
}
