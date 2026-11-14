<?php

namespace Amarenkov\MutableContentAi\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

use Amarenkov\MutableContent\Helpers\UserHelper;

use Amarenkov\MutableContentAi\Models\Grant;

/**
 * Whose language model settings an agent uses for the user in the conversation: Access::for($user, 'telegram:15').
 * The user's own ready settings come first, even over the daily limit: somebody else's key is not a spare one.
 * Without them - a grant the user accepted (Consents) whose owner has ready settings: personal in the conversation,
 * personal everywhere, for everybody in the conversation; within the limits first. Null - no agent for the user here;
 * Access::offerFor() then tells the grant to ask the user about.
 */
class Access
{
    // static
    /**
     * Grants for the user in the conversation whose owners have ready settings, in the order of preference.
     *
     * @return Collection<int, Grant>
     */
    protected static function grants(int $userId, ?string $conversation): Collection
    {
        return Grant::query()->availableTo($userId, $conversation)->get()
            ->filter(fn (Grant $grant) => UserSettings::of($grant->ownerId())->isReady())
            ->sortBy(fn (Grant $grant) => [$grant->isForEverybody() ? 1 : 0, $grant->isEverywhere() ? 1 : 0, $grant->id])
            ->values();
    }

    public static function for(Authenticatable|int $user, ?string $conversation = null): ?self
    {
        $userId = UserHelper::id($user);
        $own = UserSettings::of($userId);

        if ($own->isReady()) {
            return new self($userId, $own);
        }

        $consents = Consents::of($userId);
        $exhausted = null;

        foreach (self::grants($userId, $conversation) as $grant) {
            if (!$consents->isAccepted($grant)) {
                continue;
            }

            $access = new self($userId, UserSettings::of($grant->ownerId()), $grant);

            if ($access->withinDailyLimit()) {
                return $access;
            }

            $exhausted ??= $access;
        }

        return $exhausted;
    }

    /**
     * The grant to ask the user about when there is no access: offered here, neither accepted for the owner's current
     * endpoint nor declined. Null when the user has own ready settings or nothing is offered.
     */
    public static function offerFor(Authenticatable|int $user, ?string $conversation = null): ?Grant
    {
        $userId = UserHelper::id($user);

        if (UserSettings::of($userId)->isReady()) {
            return null;
        }

        $consents = Consents::of($userId);

        return self::grants($userId, $conversation)->first(fn (Grant $grant) => $consents->isUndecided($grant));
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
