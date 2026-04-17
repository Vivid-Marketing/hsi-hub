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
            'send_to_craft' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $max = (int) config('cld_api.ui.max_singles_ids', 50);
            $lines = preg_split('/\R/u', $this->input('cld_ids', '')) ?: [];
            $ids = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^\d+$/', $line)) {
                    $ids[] = (int) $line;
                }
            }
            $ids = array_values(array_unique($ids));
            if (empty($ids)) {
                $validator->errors()->add('cld_ids', 'Enter at least one numeric CLD lesson ID (one per line).');
            }
            if (count($ids) > $max) {
                $validator->errors()->add(
                    'cld_ids',
                    "You can process at most {$max} IDs per run. Remove some lines or raise CLD_UI_MAX_SINGLES_IDS."
                );
            }
        });
    }

    /**
     * @return int[]
     */
    public function parsedLessonIds(): array
    {
        $max = (int) config('cld_api.ui.max_singles_ids', 50);
        $lines = preg_split('/\R/u', $this->input('cld_ids', '')) ?: [];
        $ids = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\d+$/', $line)) {
                $ids[] = (int) $line;
            }
        }
        $ids = array_values(array_unique($ids));

        return array_slice($ids, 0, $max);
    }

    public function messages(): array
    {
        return [
            'cld_ids.required' => 'Enter at least one CLD lesson ID.',
        ];
    }
}
