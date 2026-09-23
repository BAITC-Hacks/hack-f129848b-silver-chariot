<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    public $timestamps = false;

    protected $fillable = ['employee_id', 'event_id', 'rank', 'score', 'factors', 'rationale', 'source', 'created_at'];

    protected function casts(): array
    {
        return ['factors' => 'array', 'created_at' => 'datetime', 'rank' => 'integer', 'score' => 'float'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }
}
