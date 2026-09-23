<?php

return [
    'employee' => [
        'employee_id' => 'E_TEST', 'full_name' => 'Private Test Name',
        'role' => 'Backend Engineer', 'grade' => 'Middle', 'career_goal' => null,
        'skills' => ['SK_SYSTEM_DESIGN' => 2, 'SK_PUBLIC_SPEAKING' => 0, 'SK_SQL' => 2],
        'last_review_date' => '2026-09-30', 'work_format' => 'hybrid',
    ],
    'events' => [
        [
            'event_id' => 'EV_006', 'title' => 'Designing High-Load Systems', 'type' => 'workshop',
            'format' => 'online', 'mandatory' => false, 'duration_hours' => 12,
            'target_roles' => ['Backend Engineer'], 'target_grades' => ['Middle', 'Senior', 'Lead'],
            'prerequisites' => ['SK_SYSTEM_DESIGN' => 2], 'upcoming_sessions' => ['2026-10-07'],
            'develops_skills' => [['skill_id' => 'SK_SYSTEM_DESIGN', 'gain' => 1, 'max_level' => 5]],
        ],
        [
            'event_id' => 'EV_036', 'title' => 'Public Speaking Club', 'type' => 'meetup',
            'format' => 'offline', 'mandatory' => false, 'duration_hours' => 2,
            'target_roles' => ['Backend Engineer'], 'target_grades' => ['Middle', 'Senior', 'Lead'],
            'prerequisites' => [], 'upcoming_sessions' => ['2026-10-08'],
            'develops_skills' => [['skill_id' => 'SK_PUBLIC_SPEAKING', 'gain' => 1, 'max_level' => 4]],
        ],
        [
            'event_id' => 'EV_SQL', 'title' => 'SQL Course', 'type' => 'course',
            'format' => 'self_paced', 'mandatory' => false, 'duration_hours' => 8,
            'target_roles' => ['Backend Engineer'], 'target_grades' => ['Middle', 'Senior', 'Lead'],
            'prerequisites' => [], 'upcoming_sessions' => [],
            'develops_skills' => [['skill_id' => 'SK_SQL', 'gain' => 1, 'max_level' => 4]],
        ],
    ],
    'role_profiles' => [
        ['role' => 'Backend Engineer', 'grade' => 'Senior', 'required_skills' => ['SK_SYSTEM_DESIGN' => 4, 'SK_PUBLIC_SPEAKING' => 3, 'SK_SQL' => 3], 'critical_skills' => ['SK_SYSTEM_DESIGN']],
        ['role' => 'Backend Engineer', 'grade' => 'Lead', 'required_skills' => ['SK_SYSTEM_DESIGN' => 5, 'SK_PUBLIC_SPEAKING' => 3, 'SK_SQL' => 4], 'critical_skills' => ['SK_SYSTEM_DESIGN']],
        ['role' => 'Data Analyst', 'grade' => 'Senior', 'required_skills' => ['SK_SQL' => 4], 'critical_skills' => ['SK_SQL']],
    ],
    'history' => [
        ['record_id' => 'R1', 'employee_id' => 'E_TEST', 'event_id' => 'EV_036', 'date' => '2026-08-01', 'status' => 'no_show'],
        ['record_id' => 'R2', 'employee_id' => 'E_TEST', 'event_id' => 'EV_036', 'date' => '2026-08-15', 'status' => 'no_show'],
        ['record_id' => 'R3', 'employee_id' => 'E_TEST', 'event_id' => 'EV_036', 'date' => '2026-09-01', 'status' => 'no_show'],
    ],
    'skills' => [
        ['skill_id' => 'SK_SYSTEM_DESIGN', 'name' => 'System Design'],
        ['skill_id' => 'SK_PUBLIC_SPEAKING', 'name' => 'Public Speaking'],
        ['skill_id' => 'SK_SQL', 'name' => 'SQL'],
    ],
];
