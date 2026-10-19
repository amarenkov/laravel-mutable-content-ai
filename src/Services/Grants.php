<?php

namespace Amarenkov\MutableContentAi\Services;

use DateTimeInterface;
use DomainException;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

use Amarenkov\MutableContentAi\Models\Grant;
use Amarenkov\MutableContentAi\Models\Run;

/**
 * Grants of one user's language model settings to other users:
 * Grants::of($owner)->give($user, 'telegram:15', 20) / give(null, 'telegram:15') / give($user) / moveTo() / revoke().
 * One active grant per user and conversation; giving it again changes only the limit.
 */
class Grants
{
    // static
    public static function of(Authenticatable|int $owner): self
    {
        return new self($owner instanceof Authenticatable ? (int)$owner->getAuthIdentifier() : $owner);
    }

    /**
     * Runs through the grant since the moment.
     */
    public static function usedSince(Grant $grant, DateTimeInterface $since): int
    {
        return Run::query()
            ->whereField(Run::FIELD_GRANT_ID, $grant->id)
            ->where('created_at', '>=', Carbon::instance($since)->setTimezone(config('app.timezone')))
            ->count();
    }

    /**
     * Runs through the grant today, in the application timezone.
     */
    public static function usedToday(Grant $grant): int
    {
        return static::usedSince($grant, Carbon::now(config('app.timezone'))->startOfDay());
    }

    public static function withinDailyLimit(Grant $grant): bool
    {
        $limit = $grant->dailyLimit();

        return $limit === null || static::usedToday($grant) < $limit;
    }

    // protected
    protected function save(Grant $grant, array $fields): Grant
    {
        $grant->fill($fields);

        if ($grant->isDirty() || !$grant->exists) {
            $grant->withLogContext()->user($this->ownerId);
            $grant->save();
        }

        return $grant;
    }

    protected function ensureOwn(Grant $grant): void
    {
        if ($grant->ownerId() !== $this->ownerId) {
            throw new DomainException(__('mutable-content-ai::ai.grant_not_found'));
        }
    }

    /**
     * @return Builder<Grant>
     */
    protected function same(?int $userId, ?string $conversation): Builder
    {
        $query = $this->query();

        $userId === null ? $query->whereNull(Grant::FIELD_USER_ID) : $query->whereField(Grant::FIELD_USER_ID, $userId);
        $conversation === null ? $query->whereNull(Grant::FIELD_CONVERSATION) : $query->whereField(Grant::FIELD_CONVERSATION, $conversation);

        return $query;
    }

    protected function normalizeLimit(?int $dailyLimit): ?int
    {
        if ($dailyLimit !== null && $dailyLimit < 1) {
            throw new DomainException(__('mutable-content-ai::ai.grant_wrong_limit'));
        }

        return $dailyLimit;
    }

    // public
    public function __construct(
        public readonly int $ownerId,
    )
    {
    }

    /**
     * Active grants of the owner.
     *
     * @return Builder<Grant>
     */
    public function query(): Builder
    {
        return Grant::query()->ownedBy($this->ownerId);
    }

    public function find(int $id, bool $withRevoked = false): ?Grant
    {
        return $this->query()->when($withRevoked, fn (Builder $query) => $query->withTrashed())->find($id);
    }

    /**
     * Share the settings with the user (null - with everybody in the conversation) in the conversation (null -
     * everywhere). An existing grant is returned; the limit given replaces its limit.
     */
    public function give(Authenticatable|int|null $user, ?string $conversation = null, ?int $dailyLimit = null): Grant
    {
        $userId = $user instanceof Authenticatable ? (int)$user->getAuthIdentifier() : $user;
        $conversation = $conversation === null || trim($conversation) === '' ? null : trim($conversation);

        if ($userId === null && $conversation === null) {
            throw new DomainException(__('mutable-content-ai::ai.grant_to_nobody'));
        }

        if ($userId === $this->ownerId) {
            throw new DomainException(__('mutable-content-ai::ai.grant_to_owner'));
        }

        $dailyLimit = $this->normalizeLimit($dailyLimit);
        $grant = $this->same($userId, $conversation)->first();

        if ($grant) {
            return $dailyLimit === null ? $grant : $this->save($grant, [Grant::FIELD_DAILY_LIMIT => $dailyLimit]);
        }

        return $this->save(new Grant(), [
            Grant::FIELD_OWNER_ID => $this->ownerId,
            Grant::FIELD_USER_ID => $userId,
            Grant::FIELD_CONVERSATION => $conversation,
            Grant::FIELD_DAILY_LIMIT => $dailyLimit,
        ]);
    }

    /**
     * Move a personal grant to the conversation or everywhere (null). The same grant already there is revoked.
     */
    public function moveTo(Grant $grant, ?string $conversation): Grant
    {
        $this->ensureOwn($grant);

        if ($grant->isForEverybody() && $conversation === null) {
            throw new DomainException(__('mutable-content-ai::ai.grant_to_nobody'));
        }

        foreach ($this->same($grant->userId(), $conversation)->whereKeyNot($grant->id)->get() as $duplicate) {
            $this->revoke($duplicate);
        }

        return $this->save($grant, [Grant::FIELD_CONVERSATION => $conversation]);
    }

    public function setDailyLimit(Grant $grant, ?int $dailyLimit): Grant
    {
        $this->ensureOwn($grant);

        return $this->save($grant, [Grant::FIELD_DAILY_LIMIT => $this->normalizeLimit($dailyLimit)]);
    }

    public function revoke(Grant $grant): void
    {
        $this->ensureOwn($grant);

        if ($grant->trashed()) {
            return;
        }

        $grant->withLogContext()->user($this->ownerId);
        $grant->delete();
    }
}
