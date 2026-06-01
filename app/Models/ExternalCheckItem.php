<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalCheckItem extends Model
{
    protected $fillable = ['name', 'url', 'is_required', 'active', 'sort_order'];
}
