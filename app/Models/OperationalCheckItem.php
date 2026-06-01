<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalCheckItem extends Model
{
    protected $fillable = ['name', 'description', 'responsible_role', 'is_required', 'active', 'sort_order'];
}
