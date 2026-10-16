<?php

namespace Amarenkov\MutableContentAi\Support;

/**
 * The application user model: memories and runs belong to a user.
 */
class Users
{
    // static
    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public static function model(): string
    {
        return config('mutable-content-ai.user_model') ?? config('auth.providers.users.model');
    }
}
