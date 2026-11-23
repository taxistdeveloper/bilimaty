<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Permission extends Model
{
    protected static string $table = 'permissions';

    /** @var list<string> */
    protected static array $fillable = ['slug', 'module', 'name_ru', 'name_kk'];
}
