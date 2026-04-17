<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoursesPdfBatch extends Model
{
    protected $table = 'courses_pdfs_batches';
    protected $primaryKey = 'batch_id';
    public $timestamps = false;

    protected $fillable = [
        'job_id',
        'batch_index',
        'total_batches',
        'email',
        'serialized_data',
        'date_entered',
        'processed_at',
        'stitched_cpdid',
        'status',
        'error_message',
    ];
}

