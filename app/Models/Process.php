<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Process extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_enabled', 'route_name', 'icon'];
}
