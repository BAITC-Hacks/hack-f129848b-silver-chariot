<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'skill_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['skill_id', 'name', 'type', 'category', 'description'];

    protected function casts(): array
    {
        return [];
    }
}
