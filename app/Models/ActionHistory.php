<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionHistory extends Model
{
    protected $fillable = ['account_opening_id', 'user_id', 'action', 'subject_type', 'subject_id', 'description', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}
