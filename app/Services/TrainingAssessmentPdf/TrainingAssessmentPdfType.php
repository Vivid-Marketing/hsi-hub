<?php

namespace App\Services\TrainingAssessmentPdf;

enum TrainingAssessmentPdfType: string
{
    case Default = 'default';
    case Hrca = 'hrca';
    case Qew = 'qew';
}

