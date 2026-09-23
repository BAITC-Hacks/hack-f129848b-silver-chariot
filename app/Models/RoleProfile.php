<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleProfile extends Model
{
    public $timestamps = false;

    protected $fillable = ['role', 'grade', 'required_skills', 'critical_skills'];

    protected function casts(): array
    {
        return ['required_skills' => 'array', 'critical_skills' => 'array'];
    }
}
