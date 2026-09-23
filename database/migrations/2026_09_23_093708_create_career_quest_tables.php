<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->string('skill_id')->primary();
            $table->string('name');
            $table->string('type');
            $table->string('category');
            $table->text('description');
        });
        Schema::create('role_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->string('grade');
            $table->json('required_skills');
            $table->json('critical_skills');
            $table->unique(['role', 'grade']);
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->string('employee_id')->primary();
            $table->string('full_name');
            $table->string('department');
            $table->string('role');
            $table->string('grade');
            $table->string('manager_id')->nullable()->index();
            $table->date('hire_date');
            $table->unsignedInteger('tenure_months');
            $table->string('work_format');
            $table->string('preferred_language');
            $table->json('career_goal')->nullable();
            $table->json('skills');
            $table->date('last_review_date');
            $table->foreign(['role', 'grade'])->references(['role', 'grade'])->on('role_profiles');
            $table->foreign('manager_id')->references('employee_id')->on('employees');
        });
        Schema::create('events', function (Blueprint $table) {
            $table->string('event_id')->primary();
            $table->string('title');
            $table->text('description');
            $table->string('type');
            $table->string('format');
            $table->decimal('duration_hours', 8, 2);
            $table->boolean('mandatory');
            foreach (['target_roles', 'target_grades', 'develops_skills', 'prerequisites', 'upcoming_sessions'] as $column) {
                $table->json($column);
            }
        });
        Schema::create('activity_records', function (Blueprint $table) {
            $table->string('record_id')->primary();
            $table->string('employee_id');
            $table->string('event_id');
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->string('status');
            $table->unsignedTinyInteger('completion_pct');
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedTinyInteger('feedback_rating')->nullable();
            $table->string('assigned_by');
            $table->foreign('employee_id')->references('employee_id')->on('employees');
            $table->foreign('event_id')->references('event_id')->on('events');
            $table->index(['employee_id', 'event_id', 'status']);
            $table->index(['event_id', 'status']);
        });
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id');
            $table->string('event_id');
            $table->unsignedInteger('rank');
            $table->double('score');
            $table->json('factors');
            $table->text('rationale');
            $table->string('source');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('employee_id')->references('employee_id')->on('employees');
            $table->foreign('event_id')->references('event_id')->on('events');
            $table->index(['employee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['recommendations', 'activity_records', 'events', 'employees', 'role_profiles', 'skills'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
