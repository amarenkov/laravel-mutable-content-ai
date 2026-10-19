<?php

namespace Amarenkov\MutableContentAi\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Label as CommonFieldLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsRequired as CFAIsRequired;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsImmutable as CFAIsImmutable;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\TypeSettings as CFATypeSettings;

use Amarenkov\MutableContentAi\Support\Users;

/**
 * A user's language model settings shared with another user or with everybody in a conversation. Without a user the
 * grant is for everybody in the conversation; without a conversation it works everywhere, as the user's own settings.
 * A conversation covers the nested ones: "telegram:15" covers "telegram:15:7". A revoked grant is soft deleted.
 */
#[Table('ai.grants')]
#[ClassLabel('AI grant')]
#[CommonFieldLabel]
class Grant extends ModelWithFields
{
    // const
    #[CFAType(Type::TYPE_INT), CFALabel('Owner'), CFAIsRequired, CFAIsImmutable]
    public const FIELD_OWNER_ID = 'owner_id';

    #[CFAType(Type::TYPE_INT), CFALabel('User'), CFAIsImmutable]
    public const FIELD_USER_ID = 'user_id';

    #[CFAType(Type::TYPE_STRING), CFALabel('Conversation')]
    public const FIELD_CONVERSATION = 'conversation';

    #[CFAType(Type::TYPE_INT), CFALabel('Daily limit'), CFATypeSettings([TypeSettings::ALLOW_NEGATIVE => false, TypeSettings::ALLOW_ZERO => false])]
    public const FIELD_DAILY_LIMIT = 'daily_limit';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    /**
     * The conversation and the ones containing it: "telegram:15:7" => ["telegram:15:7", "telegram:15", "telegram"].
     *
     * @return array<string>
     */
    public static function conversationScopes(string $conversation): array
    {
        $parts = explode(':', $conversation);
        $scopes = [];

        for ($count = count($parts); $count > 0; $count--) {
            $scopes[] = implode(':', array_slice($parts, 0, $count));
        }

        return $scopes;
    }

    // public
    public function scopeOwnedBy(Builder $query, Authenticatable|int $user): Builder
    {
        return $query->whereField(self::FIELD_OWNER_ID, $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user);
    }

    /**
     * Grants given to the user personally.
     */
    public function scopeGivenTo(Builder $query, Authenticatable|int $user): Builder
    {
        return $query->whereField(self::FIELD_USER_ID, $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user);
    }

    /**
     * Grants working for the user in the conversation: personal and for everybody there.
     */
    public function scopeAvailableTo(Builder $query, Authenticatable|int $user, ?string $conversation = null): Builder
    {
        $userId = $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user;

        return $query
            ->where(fn (Builder $query) => $query->whereField(self::FIELD_USER_ID, $userId)->orWhereNull(self::FIELD_USER_ID))
            ->where(fn (Builder $query) => $conversation === null
                ? $query->whereNull(self::FIELD_CONVERSATION)
                : $query->whereNull(self::FIELD_CONVERSATION)->orWhereIn(self::FIELD_CONVERSATION, self::conversationScopes($conversation)))
            ->whereField(self::FIELD_OWNER_ID, '<>', $userId);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Users::model(), self::FIELD_OWNER_ID);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::model(), self::FIELD_USER_ID);
    }

    public function ownerId(): int
    {
        return (int)$this->getField(self::FIELD_OWNER_ID);
    }

    public function userId(): ?int
    {
        $userId = $this->getField(self::FIELD_USER_ID);

        return $userId === null ? null : (int)$userId;
    }

    public function conversation(): ?string
    {
        return $this->getField(self::FIELD_CONVERSATION);
    }

    public function isForEverybody(): bool
    {
        return $this->userId() === null;
    }

    public function isEverywhere(): bool
    {
        return $this->conversation() === null;
    }

    public function dailyLimit(): ?int
    {
        $limit = $this->getField(self::FIELD_DAILY_LIMIT);

        return $limit === null ? null : (int)$limit;
    }

    public function isRevoked(): bool
    {
        return $this->trashed();
    }
}
