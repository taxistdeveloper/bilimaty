<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AcademicYear extends Model
{
    protected static string $table = 'academic_years';

    /** @var list<string> */
    protected static array $fillable = ['title', 'start_date', 'end_date', 'status'];
}
