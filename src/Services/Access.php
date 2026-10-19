<?php

namespace Amarenkov\MutableContentAi\Services;

use Illuminate\Contracts\Auth\Authenticatable;

use Amarenkov\MutableContentAi\Models\Grant;

/**
 * Whose language model settings an agent uses for the user in the conversation: Access::for($user, 'telegram:15').
 * The user's own ready settings come first, even over the daily limit: somebody else's key is not a spare one.
 * Without them - a grant whose owner has ready settings: personal in the conversation, personal everywhere, for
 * everybody in the conversation; within the limits first. Null - no agent for the user here.
 */
class Access
{
    // static
    public static function for(Authenticatable|int $user, ?string $conversation = null): ?self
    {
        $userId = $user instanceof Authenticatable ? (int)$user->getAuthIdentifier() : $user;
        $own = UserSettings::of($userId);

        if ($own->isReady()) {
            return new self($userId, $own);
        }

        $grants = Grant::query()->availableTo($userId, $conversation)->get()
            ->sortBy(fn (Grant $grant) => [$grant->isForEverybody() ? 1 : 0, $grant->isEverywhere() ? 1 : 0, $grant->id])
            ->values();

        $exhausted = null;

        foreach ($grants as $grant) {
            $access = new self($userId, UserSettings::of($grant->ownerId()), $grant);

            if (!$access->settings->isReady()) {
                continue;
            }

            if ($access->withinDailyLimit()) {
                return $access;
            }

            $exhausted ??= $access;
        }

        return $exhausted;
    }

    // public
    public function __construct(
        public readonly int $userId,
        public readonly UserSettings $settings,
        public readonly ?Grant $grant = null,
    )
    {
    }

    /**
     * The settings are somebody else's, through a grant.
     */
    public function isShared(): bool
    {
        return $this->grant !== null;
    }

    /**
     * Who pays for the runs.
     */
    public function payerId(): int
    {
        return $this->settings->userId;
    }

    /**
     * Within the daily limit of the settings and of the grant.
     */
    public function withinDailyLimit(): bool
    {
        return $this->settings->withinDailyLimit() && ($this->grant === null || Grants::withinDailyLimit($this->grant));
    }

    public function model(): ?string
    {
        return $this->settings->model();
    }

    public function provider(): string
    {
        return $this->settings->provider();
    }
}
