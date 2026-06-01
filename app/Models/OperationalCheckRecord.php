<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalCheckRecord extends Model
{
    protected $fillable = [
        'account_opening_id',
        'operational_check_item_id',
        'status',
        'account_number',
        'observation',
        'completed_by',
        'completed_at',
    ];

    protected $casts = ['completed_at' => 'datetime'];
}
