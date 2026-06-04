<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessCldSinglesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-courses') || $this->user()->can('edit-courses');
    }

    public function rules(): array
    {
        return [
            'cld_ids' => ['required', 'string', 'max:65535'],
            'course_sync_rows' => ['nullable', 'string', 'max:65535'],
            'send_to_craft' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $max = (int) config('cld_api.ui.max_singles_ids', 50);
            $ids = $this->parsedLessonIds();

            if (empty($ids)) {
                $validator->errors()->add('cld_ids', 'Enter at least one numeric CLD lesson ID.');
            }
            if (count($ids) > $max) {
                $validator->errors()->add(
                    'cld_ids',
                    "You can process at most {$max} IDs per run. Remove some rows or raise CLD_UI_MAX_SINGLES_IDS."
                );
            }

            foreach ($this->parsedCourseSyncRows() as $index => $row) {
                $cldId = trim((string) ($row['cld_id'] ?? ''));
                $vimeoId = trim((string) ($row['vimeo_id'] ?? ''));

                if ($cldId === '' && $vimeoId === '') {
                    continue;
                }

                if ($cldId !== '' && ! preg_match('/^\d+$/', $cldId)) {
                    $validator->errors()->add(
                        'course_sync_rows',
                        'Row '.($index + 1).': CLD ID must be numeric.'
                    );
                }

                if ($vimeoId !== '' && $this->normalizeVimeoId($vimeoId) === null) {
                    $validator->errors()->add(
                        'course_sync_rows',
                        'Row '.($index + 1).': Vimeo ID must be numeric, blank, or N/A.'
                    );
                }
            }
        });
    }

    /**
     * @return array<int, array{cld_id: string, vimeo_id: string}>
     */
    public function parsedCourseSyncRows(): array
    {
        $json = $this->input('course_sync_rows');
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return array_values(array_map(static fn ($row) => [
                    'cld_id' => (string) ($row['cld_id'] ?? ''),
                    'vimeo_id' => (string) ($row['vimeo_id'] ?? ''),
                ], $decoded));
            }
        }

        $rows = [];
        foreach (preg_split('/\R/u', $this->input('cld_ids', '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_contains($line, "\t")) {
                [$cldId, $vimeoId] = array_pad(explode("\t", $line, 2), 2, '');
                $rows[] = ['cld_id' => trim($cldId), 'vimeo_id' => trim($vimeoId)];
            } else {
                $rows[] = ['cld_id' => $line, 'vimeo_id' => ''];
            }
        }

        return $rows;
    }

    /**
     * @return int[]
     */
    public function parsedLessonIds(): array
    {
        $max = (int) config('cld_api.ui.max_singles_ids', 50);
        $ids = [];
        foreach ($this->parsedCourseSyncRows() as $row) {
            $cldId = trim((string) ($row['cld_id'] ?? ''));
            if ($cldId !== '' && preg_match('/^\d+$/', $cldId)) {
                $ids[] = (int) $cldId;
            }
        }
        $ids = array_values(array_unique($ids));

        return array_slice($ids, 0, $max);
    }

    /**
     * Lesson ID (numeric) => Vimeo ID string for rows that supplied one.
     *
     * @return array<int, string>
     */
    public function parsedVimeoIdsByLessonId(): array
    {
        $map = [];
        foreach ($this->parsedCourseSyncRows() as $row) {
            $cldId = trim((string) ($row['cld_id'] ?? ''));
            if ($cldId === '' || ! preg_match('/^\d+$/', $cldId)) {
                continue;
            }

            $lessonId = (int) $cldId;
            $vimeoId = $this->normalizeVimeoId((string) ($row['vimeo_id'] ?? ''));
            if ($vimeoId !== null) {
                $map[$lessonId] = $vimeoId;
            }
        }

        return $map;
    }

    public function messages(): array
    {
        return [
            'cld_ids.required' => 'Enter at least one CLD lesson ID.',
        ];
    }

    private function normalizeVimeoId(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $lower = strtolower($value);
        if (in_array($lower, ['n/a', 'na', '-'], true)) {
            return null;
        }

        if (! preg_match('/^\d+$/', $value)) {
            return null;
        }

        return $value;
    }
}
