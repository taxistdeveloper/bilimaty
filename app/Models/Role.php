<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Role extends Model
{
    protected static string $table = 'roles';

    /** @var list<string> */
    protected static array $fillable = ['slug', 'name_ru', 'name_kk', 'description', 'is_system'];
}
