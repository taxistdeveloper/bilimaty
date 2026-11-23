<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * Validation rules of a student (person + profile). One source for the form, the edit form
 * and the Excel import.
 */
final class StudentValidator
{
    /**
     * @param array<string, mixed>|null $student existing row (uniqueness ignores it)
     * @return array<string, string>
     */
    public static function rules(?array $student = null): array
    {
        $userIgnore = $student !== null ? ',' . $student['user_id'] : '';
        $studentIgnore = $student !== null ? ',' . $student['id'] : '';

        return [
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'login' => 'nullable|alpha_dash|min:3|max:64|unique:users,login' . $userIgnore,
            'email' => 'nullable|email|max:190|unique:users,email' . $userIgnore,
            'phone' => 'nullable|phone|max:20',
            'locale' => 'required|in:kk,ru',
            'student_number' => 'nullable|alpha_dash|max:30|unique:students,student_number' . $studentIgnore,
            'iin' => 'nullable|iin|unique:students,iin' . $studentIgnore,
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'enrollment_date' => 'nullable|date',
            'funding' => 'nullable|in:state_grant,paid',
        ];
    }
}
