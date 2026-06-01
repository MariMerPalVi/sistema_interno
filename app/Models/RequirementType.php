<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequirementType extends Model
{
    protected $fillable = ['name', 'slug', 'validation_rules', 'allows_auto_extraction', 'requires_manual_validation'];
}
