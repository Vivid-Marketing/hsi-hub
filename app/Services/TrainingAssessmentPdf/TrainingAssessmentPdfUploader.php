<?php

namespace App\Services\TrainingAssessmentPdf;

use Aws\S3\S3Client;

class TrainingAssessmentPdfUploader
{
    /**
     * Upload a local file to DigitalOcean Spaces and return the public URL.
     */
    public function upload(string $localPath, string $remoteKey, string $contentType = 'application/pdf'): string
    {
        $key = (string) config('training_assessment_pdf.do_spaces.key');
        $secret = (string) config('training_assessment_pdf.do_spaces.secret');
        $bucket = (string) config('training_assessment_pdf.do_spaces.bucket');
        $region = (string) config('training_assessment_pdf.do_spaces.region');
        $endpoint = (string) config('training_assessment_pdf.do_spaces.endpoint');

        if ($key === '' || $secret === '' || $bucket === '' || $region === '' || $endpoint === '') {
            throw new \RuntimeException('DigitalOcean Spaces is not configured for training assessment PDFs.');
        }

        $client = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);

        $client->putObject([
            'Bucket' => $bucket,
            'Key' => ltrim($remoteKey, '/'),
            'SourceFile' => $localPath,
            'ACL' => 'public-read',
            'ContentType' => $contentType,
        ]);

        return rtrim($endpoint, '/').'/'.$bucket.'/'.ltrim($remoteKey, '/');
    }
}

