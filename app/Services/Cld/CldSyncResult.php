<?php

namespace App\Services\Cld;

/**
 * Outcome of {@see \App\Services\CldApiService::cronJobGenerateAddUpdateCldApiDataFromList()}.
 */
final class CldSyncResult
{
    /**
     * @param  array<int, array{lesson_id: int, reason: string}>  $failures
     * @param  array<string, mixed>|null  $feedMe
     */
    public function __construct(
        public string $mode,
        public int $totalIds,
        public int $succeeded,
        public array $failures,
        public ?array $feedMe = null,
        public ?string $abortReason = null,
    ) {}

    /**
     * True when the run had nothing to process (no lesson IDs matched),
     * as opposed to a run that actually attempted work and hit problems.
     */
    public function isEmptyRun(): bool
    {
        return $this->totalIds === 0
            && $this->succeeded === 0
            && $this->failures === []
            && $this->feedMe === null;
    }

    public function hasIssues(): bool
    {
        if ($this->abortReason !== null) {
            return true;
        }
        if ($this->failures !== []) {
            return true;
        }
        if ($this->feedMe !== null && empty($this->feedMe['ok'])) {
            return true;
        }

        return false;
    }
}
