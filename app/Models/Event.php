<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['event_id', 'title', 'description', 'type', 'format', 'duration_hours', 'mandatory', 'target_roles', 'target_grades', 'develops_skills', 'prerequisites', 'upcoming_sessions'];

    protected function casts(): array
    {
        return ['target_roles' => 'array', 'target_grades' => 'array', 'develops_skills' => 'array', 'prerequisites' => 'array', 'upcoming_sessions' => 'array', 'mandatory' => 'boolean', 'duration_hours' => 'float'];
    }

    public function activityRecords(): HasMany
    {
        return $this->hasMany(ActivityRecord::class, 'event_id', 'event_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'event_id', 'event_id');
    }
}
