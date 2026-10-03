<?php

namespace App\Support;

class ImportColumns
{
    public const TYPES = ['interns', 'advisers', 'classes'];

    public const INTERNS = ['first_name', 'middle_name', 'last_name', 'email', 'phone', 'student_number', 'section', 'school_year'];

    public const ADVISERS = ['first_name', 'middle_name', 'last_name', 'email', 'phone'];

    public const CLASSES = ['course_code', 'subject', 'section', 'day', 'starts_at', 'ends_at', 'school_year', 'adviser_member_no'];

    public const EXAMPLES = [
        'interns' => ['Maria', 'Reyes', 'Santos', 'maria.santos@example.com', '09171234567', '21-0001', 'SBIT-4C', '2025-2026'],
        'advisers' => ['Elsie', '', 'Isip', 'elsie.isip@example.com', '09181234567'],
        'classes' => ['CC101', 'Practicum', 'SBIT-4C', 'Monday', '08:00', '12:00', '2025-2026', 'ADV-2026-00001'],
    ];

    /** @return array<int, string> */
    public static function headersFor(string $type): array
    {
        return match ($type) {
            'interns' => self::INTERNS,
            'advisers' => self::ADVISERS,
            'classes' => self::CLASSES,
            default => abort(404),
        };
    }
}
