<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTrainingAssessmentPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint for now (security later, like course catalog PDF ingestion).
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'name' => ['required', 'string', 'max:200'],
            'reportHtml' => ['required', 'string'],
        ];
    }
}

