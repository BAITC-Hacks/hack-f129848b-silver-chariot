<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-hr') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $event = $this->route('event');
        $existingDevelopment = $event instanceof Event && $event->activityRecords()->exists() ? $event->develops_skills : [];
        foreach (['develops_skills' => $existingDevelopment, 'prerequisite_skills' => [], 'upcoming_sessions' => []] as $field => $default) {
            $value = $this->input($field, $default);
            if (is_array($value)) {
                $value = array_filter($value, fn (mixed $row): bool => is_array($row)
                    ? array_filter($row, fn (mixed $item): bool => $item !== null && $item !== '') !== []
                    : $row !== null && $row !== '');
            }
            $this->merge([$field => $value]);
        }
        $this->merge(['mandatory' => $this->input('mandatory', false)]);
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'type' => ['required', Rule::in(array_keys(Event::TYPES))],
            'format' => ['required', Rule::in(array_keys(Event::FORMATS))],
            'duration_hours' => 'required|numeric|min:0.01|max:999999.99',
            'mandatory' => 'required|boolean',
            'target_roles' => 'required|array|min:1|max:100',
            'target_roles.*' => 'required|string|distinct|exists:role_profiles,role',
            'target_grades' => 'required|array|min:1|max:4',
            'target_grades.*' => 'required|string|distinct|in:Junior,Middle,Senior,Lead',
            'develops_skills' => 'present|array|max:100',
            'develops_skills.*' => 'required|array:skill_id,gain,max_level',
            'develops_skills.*.skill_id' => 'required|string|distinct|exists:skills,skill_id',
            'develops_skills.*.gain' => 'required|integer|between:1,5',
            'develops_skills.*.max_level' => 'required|integer|between:1,5',
            'prerequisite_skills' => 'present|array|max:100',
            'prerequisite_skills.*' => 'required|array:skill_id,min_level',
            'prerequisite_skills.*.skill_id' => 'required|string|distinct|exists:skills,skill_id',
            'prerequisite_skills.*.min_level' => 'required|integer|between:0,5',
            'upcoming_sessions' => 'exclude_if:format,self_paced|required|array|min:1|max:100',
            'upcoming_sessions.*' => 'required|date_format:Y-m-d|distinct',
        ];
    }

    /**
     * @return array{title: string, description: string, type: string, format: string, duration_hours: float,
     *     mandatory: bool, target_roles: list<string>, target_grades: list<string>,
     *     develops_skills: list<array{skill_id: string, gain: int, max_level: int}>,
     *     prerequisites: array<string, int>, upcoming_sessions: list<string>}
     */
    public function eventData(): array
    {
        $data = $this->validated();
        $prerequisites = [];
        foreach ($data['prerequisite_skills'] as $prerequisite) {
            $prerequisites[$prerequisite['skill_id']] = (int) $prerequisite['min_level'];
        }
        $dates = array_values($data['upcoming_sessions'] ?? []);
        sort($dates, SORT_STRING);

        return [
            'title' => $data['title'], 'description' => $data['description'],
            'type' => $data['type'], 'format' => $data['format'],
            'duration_hours' => (float) $data['duration_hours'], 'mandatory' => (bool) $data['mandatory'],
            'target_roles' => array_values($data['target_roles']), 'target_grades' => array_values($data['target_grades']),
            'develops_skills' => array_values(array_map(fn (array $skill): array => [
                'skill_id' => $skill['skill_id'], 'gain' => (int) $skill['gain'], 'max_level' => (int) $skill['max_level'],
            ], $data['develops_skills'])),
            'prerequisites' => $prerequisites, 'upcoming_sessions' => $dates,
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Заполните поле «:attribute».',
            'present' => 'Заполните поле «:attribute».',
            'string' => 'Поле «:attribute» должно содержать текст.',
            'array' => 'Проверьте список «:attribute».',
            'in' => 'Выберите допустимое значение для поля «:attribute».',
            'exists' => 'Выбранное значение для поля «:attribute» отсутствует в справочнике.',
            'distinct' => 'Значения в поле «:attribute» не должны повторяться.',
            'integer' => 'Поле «:attribute» должно быть целым числом.',
            'numeric' => 'Поле «:attribute» должно быть числом.',
            'boolean' => 'Выберите значение «Да» или «Нет» для поля «:attribute».',
            'between' => 'Поле «:attribute» должно быть от :min до :max.',
            'gt' => 'Поле «:attribute» должно быть больше :value.',
            'max' => 'Превышено допустимое значение поля «:attribute» (:max).',
            'min' => 'В поле «:attribute» нужно выбрать хотя бы :min значение.',
            'date_format' => 'Укажите корректную дату в формате ГГГГ-ММ-ДД.',
            'upcoming_sessions.required' => 'Для активности по расписанию добавьте хотя бы одну дату.',
            'duration_hours.min' => 'Длительность должна быть не меньше 0,01 часа.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'Название', 'description' => 'Описание', 'type' => 'Тип', 'format' => 'Формат',
            'duration_hours' => 'Длительность', 'mandatory' => 'Обязательная активность',
            'target_roles' => 'Роли', 'target_roles.*' => 'Роли',
            'target_grades' => 'Грейды', 'target_grades.*' => 'Грейды',
            'develops_skills' => 'Развиваемые навыки', 'develops_skills.*' => 'Развиваемый навык',
            'develops_skills.*.skill_id' => 'Развиваемый навык',
            'develops_skills.*.gain' => 'Прирост навыка', 'develops_skills.*.max_level' => 'Потолок навыка',
            'prerequisite_skills' => 'Требования к навыкам', 'prerequisite_skills.*' => 'Требование к навыку',
            'prerequisite_skills.*.skill_id' => 'Необходимый навык',
            'prerequisite_skills.*.min_level' => 'Минимальный уровень',
            'upcoming_sessions' => 'Даты сессий', 'upcoming_sessions.*' => 'Дата сессии',
        ];
    }
}
