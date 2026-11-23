<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    protected static string $table = 'users';
    protected static ?string $softDelete = 'deleted_at';

    /** @var list<string> */
    protected static array $fillable = [
        'login', 'email', 'password_hash', 'last_name', 'first_name', 'middle_name',
        'phone', 'locale', 'is_active', 'password_changed_at',
    ];
}
