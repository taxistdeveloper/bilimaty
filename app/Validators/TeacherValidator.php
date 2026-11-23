<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * Validation rules of a teacher (person + profile). One source for the forms and the Excel import.
 */
final class TeacherValidator
{
    /**
     * @param array<string, mixed>|null $teacher existing row (uniqueness ignores it)
     * @return array<string, string>
     */
    public static function rules(?array $teacher = null): array
    {
        $userIgnore = $teacher !== null ? ',' . $teacher['user_id'] : '';
        $teacherIgnore = $teacher !== null ? ',' . $teacher['id'] : '';

        return [
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'login' => 'nullable|alpha_dash|min:3|max:64|unique:users,login' . $userIgnore,
            'email' => 'nullable|email|max:190|unique:users,email' . $userIgnore,
            'phone' => 'nullable|phone|max:20',
            'locale' => 'required|in:kk,ru',
            'department_id' => 'nullable|integer|exists:departments,id',
            'employee_number' => 'nullable|alpha_dash|max:30|unique:teachers,employee_number' . $teacherIgnore,
            'position' => 'nullable|string|max:150',
            'academic_degree' => 'nullable|string|max:150',
            'hire_date' => 'nullable|date',
            'status' => 'required|in:active,on_leave,dismissed',
        ];
    }
}
