<?php

declare(strict_types=1);

/**
 * Simple reference dictionaries managed by the generic DictionaryController.
 *
 * field: type      text | number | select | switch
 *        rules     Validator rules; "{id}" is replaced by the edited record id (for unique)
 *        options   select source: departments | specialties | teachers | enum:<lang prefix>
 *        list      show as a column in the list
 *        filter    show as a filter above the list (select fields)
 * usage: [table, column] pairs that reference a record; such records cannot be deleted.
 */
return [
    'departments' => [
        'table' => 'departments',
        'icon' => 'bi-diagram-3',
        'order' => 'name_ru',
        'search' => ['code', 'name_ru', 'name_kk'],
        'fields' => [
            'code' => ['type' => 'text', 'rules' => 'required|alpha_dash|max:20|unique:departments,code,{id}', 'list' => true],
            'name_ru' => ['type' => 'text', 'rules' => 'required|string|max:255', 'list' => true],
            'name_kk' => ['type' => 'text', 'rules' => 'required|string|max:255', 'list' => true],
            'head_teacher_id' => ['type' => 'select', 'options' => 'teachers', 'rules' => 'nullable|integer|exists:teachers,id', 'list' => true],
            'is_active' => ['type' => 'switch', 'rules' => 'nullable|boolean', 'list' => true],
        ],
        'usage' => [['specialties', 'department_id'], ['teachers', 'department_id'], ['subjects', 'department_id'], ['classrooms', 'department_id']],
    ],

    'specialties' => [
        'table' => 'specialties',
        'icon' => 'bi-mortarboard',
        'order' => 'code',
        'search' => ['code', 'name_ru', 'name_kk'],
        'fields' => [
            'department_id' => ['type' => 'select', 'options' => 'departments', 'rules' => 'required|integer|exists:departments,id', 'list' => true, 'filter' => true],
            'code' => ['type' => 'text', 'rules' => 'required|alpha_dash|max:20|unique:specialties,code,{id}', 'list' => true],
            'name_ru' => ['type' => 'text', 'rules' => 'required|string|max:255', 'list' => true],
            'name_kk' => ['type' => 'text', 'rules' => 'required|string|max:255', 'list' => true],
            'is_active' => ['type' => 'switch', 'rules' => 'nullable|boolean', 'list' => true],
        ],
        'usage' => [['qualifications', 'specialty_id'], ['groups', 'specialty_id']],
    ],

    'qualifications' => [
        'table' => 'qualifications',
        'icon' => 'bi-award',
        'order' => 'code',
        'search' => ['code', 'name_ru', 'name_kk'],
        'fields' => [
            'specialty_id' => ['type' => 'select', 'options' => 'specialties', 'rules' => 'required|integer|exists:specialties,id', 'list' => true, 'filter' => true],
            'code' => ['type' => 'text', 'rules' => 'required|alpha_dash|max:20|unique:qualifications,code,{id}', 'list' => true],
            'name_ru' => ['type' => 'text', 'rules' => 'required|string|max:255', 'list' => true],
            'name_kk' => ['type' => 'text', 'rules' => 'required|string|max:255', 'list' => true],
            'is_active' => ['type' => 'switch', 'rules' => 'nullable|boolean', 'list' => true],
        ],
        'usage' => [['groups', 'qualification_id']],
    ],

    'subjects' => [
        'table' => 'subjects',
        'icon' => 'bi-book',
        'order' => 'name_ru',
        'search' => ['code', 'name_ru', 'name_kk'],
        'fields' => [
            'code' => ['type' => 'text', 'rules' => 'required|max:30|unique:subjects,code,{id}', 'list' => true],
            'name_ru' => ['type' => 'text', 'rules' => 'required|string|max:255', 'list' => true],
            'name_kk' => ['type' => 'text', 'rules' => 'required|string|max:255', 'list' => true],
            'department_id' => ['type' => 'select', 'options' => 'departments', 'rules' => 'nullable|integer|exists:departments,id', 'list' => true, 'filter' => true],
            'is_active' => ['type' => 'switch', 'rules' => 'nullable|boolean', 'list' => true],
        ],
        'usage' => [['curriculum_subjects', 'subject_id'], ['schedule_entries', 'subject_id']],
    ],

    'classrooms' => [
        'table' => 'classrooms',
        'icon' => 'bi-door-open',
        'order' => 'number',
        'search' => ['number', 'building', 'name'],
        'fields' => [
            'number' => ['type' => 'text', 'rules' => 'required|string|max:20', 'list' => true],
            'building' => ['type' => 'text', 'rules' => 'nullable|string|max:50', 'list' => true],
            'name' => ['type' => 'text', 'rules' => 'nullable|string|max:150', 'list' => true],
            'type' => ['type' => 'select', 'options' => 'enum:classroom_types', 'rules' => 'required|in:lecture,practice,computer,laboratory,workshop,gym,assembly,other', 'list' => true, 'filter' => true],
            'capacity' => ['type' => 'number', 'rules' => 'required|integer|min:1|max:1000', 'list' => true],
            'department_id' => ['type' => 'select', 'options' => 'departments', 'rules' => 'nullable|integer|exists:departments,id', 'list' => false, 'filter' => true],
            'is_active' => ['type' => 'switch', 'rules' => 'nullable|boolean', 'list' => true],
        ],
        // Composite uniqueness (building + number) is checked in the controller.
        'unique_together' => ['building', 'number'],
        'usage' => [['schedule_entries', 'classroom_id'], ['schedule_changes', 'new_classroom_id']],
    ],

    'lesson_periods' => [
        'table' => 'lesson_periods',
        'icon' => 'bi-bell',
        'order' => 'number',
        'search' => ['number'],
        'fields' => [
            'number' => ['type' => 'number', 'rules' => 'required|integer|between:1,12|unique:lesson_periods,number,{id}', 'list' => true],
            'start_time' => ['type' => 'time', 'rules' => 'required|time', 'list' => true],
            'end_time' => ['type' => 'time', 'rules' => 'required|time|after:start_time', 'list' => true],
            'is_active' => ['type' => 'switch', 'rules' => 'nullable|boolean', 'list' => true],
        ],
        'usage' => [['schedule_entries', 'lesson_period_id'], ['schedule_changes', 'new_lesson_period_id']],
    ],

    'workload_types' => [
        'table' => 'workload_types',
        'icon' => 'bi-hourglass-split',
        'order' => 'sort_order',
        'search' => ['code', 'name_ru', 'name_kk'],
        'fields' => [
            'code' => ['type' => 'text', 'rules' => 'required|alpha_dash|max:30|unique:workload_types,code,{id}', 'list' => true],
            'name_ru' => ['type' => 'text', 'rules' => 'required|string|max:150', 'list' => true],
            'name_kk' => ['type' => 'text', 'rules' => 'required|string|max:150', 'list' => true],
            'lesson_type' => ['type' => 'select', 'options' => 'enum:lesson_types', 'rules' => 'nullable|in:lecture,practice,lab,consultation|unique:workload_types,lesson_type,{id}', 'list' => true],
            'curriculum_field' => ['type' => 'select', 'options' => 'enum:curriculum_fields', 'rules' => 'nullable|in:hours_theory,hours_practice,hours_lab|unique:workload_types,curriculum_field,{id}', 'list' => true],
            'sort_order' => ['type' => 'number', 'rules' => 'required|integer|between:0,999'],
            'is_active' => ['type' => 'switch', 'rules' => 'nullable|boolean', 'list' => true],
        ],
        'usage' => [['workload_assignments', 'workload_type_id']],
    ],

    'document_types' => [
        'table' => 'document_types',
        'icon' => 'bi-file-earmark-text',
        'order' => 'sort_order',
        'search' => ['code', 'name_ru', 'name_kk'],
        'fields' => [
            'code' => ['type' => 'text', 'rules' => 'required|alpha_dash|max:30|unique:document_types,code,{id}', 'list' => true],
            'name_ru' => ['type' => 'text', 'rules' => 'required|string|max:150', 'list' => true],
            'name_kk' => ['type' => 'text', 'rules' => 'required|string|max:150', 'list' => true],
            'description_ru' => ['type' => 'text', 'rules' => 'nullable|string|max:500'],
            'description_kk' => ['type' => 'text', 'rules' => 'nullable|string|max:500'],
            'generator' => ['type' => 'select', 'options' => 'enum:document_generators', 'rules' => 'required|in:study_certificate,transcript,personal_data,none', 'list' => true],
            'needs_purpose' => ['type' => 'switch', 'rules' => 'nullable|boolean'],
            'allow_attachment' => ['type' => 'switch', 'rules' => 'nullable|boolean'],
            'processing_days' => ['type' => 'number', 'rules' => 'nullable|integer|between:1,60', 'list' => true],
            'sort_order' => ['type' => 'number', 'rules' => 'required|integer|between:0,999'],
            'is_active' => ['type' => 'switch', 'rules' => 'nullable|boolean', 'list' => true],
        ],
        'usage' => [['document_requests', 'type_id']],
    ],
];
