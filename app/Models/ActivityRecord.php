<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityRecord extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'record_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['record_id', 'employee_id', 'event_id', 'date', 'due_date', 'status', 'completion_pct', 'score', 'feedback_rating', 'assigned_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'completion_pct' => 'integer', 'score' => 'integer', 'feedback_rating' => 'integer'];
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
