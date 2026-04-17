<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoursesPdfData extends Model
{
    protected $table = 'courses_pdfs_data';
    protected $primaryKey = 'cpdid';
    public $timestamps = false;

    protected $fillable = [
        'date_entered',
        'serialized_data',
        'email',
        'status',
        'pdf_url',
        'pdf_generated_at',
        'email_sent_at',
        'email_message_id',
    ];
}

