<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectedAdditionalService extends Model
{
    protected $fillable = ['account_opening_id', 'additional_service_id', 'selected_by'];

    public function additionalService(): BelongsTo
    {
        return $this->belongsTo(AdditionalService::class);
    }
}
