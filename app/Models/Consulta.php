<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consulta extends Model
{
    protected $fillable = [
        'filename',
        'total_cedulas',
        'processed',
        'successful',
        'failed',
        'status',
        'error_message',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(ConsultaResult::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_cedulas === 0) return 0;
        return round(($this->processed / $this->total_cedulas) * 100, 1);
    }
}
