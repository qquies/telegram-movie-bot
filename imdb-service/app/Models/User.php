<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Заглушка для конфига auth. API-сервис не использует аутентификацию пользователей.
 */
class User extends Authenticatable
{
    protected $table = 'users';
}
