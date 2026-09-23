<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'employee_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['employee_id', 'full_name', 'department', 'role', 'grade', 'manager_id', 'hire_date', 'tenure_months', 'work_format', 'preferred_language', 'career_goal', 'skills', 'last_review_date'];

    protected function casts(): array
    {
        return ['career_goal' => 'array', 'skills' => 'array', 'hire_date' => 'date:Y-m-d', 'last_review_date' => 'date:Y-m-d', 'tenure_months' => 'integer'];
    }

    public function activityRecords(): HasMany
    {
        return $this->hasMany(ActivityRecord::class, 'employee_id', 'employee_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'employee_id', 'employee_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id', 'employee_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id', 'employee_id');
    }

    public function roleProfile(): ?RoleProfile
    {
        return RoleProfile::where('role', $this->role)->where('grade', $this->grade)->first();
    }

    public function nextGradeProfile(): ?RoleProfile
    {
        $grades = ['Junior', 'Middle', 'Senior', 'Lead'];
        $index = array_search($this->grade, $grades, true);

        return $index === false || $index === 3 ? null : RoleProfile::where('role', $this->role)->where('grade', $grades[$index + 1])->first();
    }
}
