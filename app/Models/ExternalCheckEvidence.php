<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalCheckEvidence extends Model
{
    protected $table = 'external_check_evidences';

    protected $fillable = [
        'account_opening_id',
        'external_check_item_id',
        'result',
        'screenshot_path',
        'advisor_observation',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = ['uploaded_at' => 'datetime'];
}
