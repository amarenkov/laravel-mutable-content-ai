<?php

namespace Amarenkov\MutableContentAi\Services\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;

use Amarenkov\MutableContent\Helpers\UserHelper;

/**
 * A service of one user: Service::of($user).
 */
trait ForUser
{
    use WritesAsUser;

    // static
    public static function of(Authenticatable|int $user): self
    {
        return new self(UserHelper::id($user));
    }

    // public
    public function __construct(
        public readonly int $userId,
    )
    {
    }
}
