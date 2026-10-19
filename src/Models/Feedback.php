<?php

namespace Amarenkov\MutableContentAi\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Label as CommonFieldLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsRequired as CFAIsRequired;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsImmutable as CFAIsImmutable;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\LovCode as CFALovCode;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\ObjectClass as CFAObjectClass;

use Amarenkov\MutableContentAi\Lovs\FeedbackStatus;
use Amarenkov\MutableContentAi\Support\Users;

/**
 * A user's mark on an agent message: the reaction, the message, the conversation around it and the run that produced
 * it. Collected to review and to correct the prompts; resolved with a note.
 */
#[Table('ai.feedback')]
#[ClassLabel('Feedback')]
#[CommonFieldLabel]
class Feedback extends ModelWithFields
{
    // const
    #[CFAType(Type::TYPE_INT), CFALabel('Owner'), CFAIsRequired, CFAIsImmutable]
    public const FIELD_USER_ID = 'user_id';

    #[CFAType(Type::TYPE_OBJECT), CFALabel('Agent run'), CFAObjectClass(Run::class), CFAIsImmutable]
    public const FIELD_RUN_ID = 'run_id';

    #[CFAType(Type::TYPE_STRING), CFALabel('Conversation'), CFAIsImmutable]
    public const FIELD_CONVERSATION = 'conversation';

    #[CFAType(Type::TYPE_STRING), CFALabel('Source'), CFAIsImmutable]
    public const FIELD_SOURCE = 'source';

    #[CFAType(Type::TYPE_STRING), CFALabel('Reaction'), CFAIsRequired]
    public const FIELD_REACTION = 'reaction';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Message'), CFAIsImmutable]
    public const FIELD_MESSAGE = 'message';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Context'), CFAIsImmutable]
    public const FIELD_CONTEXT = 'context';

    #[CFAType(Type::TYPE_LOV_ITEM), CFALabel('Status'), CFAIsRequired, CFALovCode(FeedbackStatus::CLASS_CODE)]
    public const FIELD_STATUS = 'status';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Note')]
    public const FIELD_NOTE = 'note';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    // public
    public function scopeOwnedBy(Builder $query, Authenticatable|int $user): Builder
    {
        return $query->whereField(self::FIELD_USER_ID, $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->whereField(self::FIELD_STATUS, $status);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::model(), self::FIELD_USER_ID);
    }

    /**
     * @return BelongsTo<Run, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, self::FIELD_RUN_ID);
    }

    public function reaction(): string
    {
        return (string)$this->getField(self::FIELD_REACTION);
    }

    public function message(): string
    {
        return (string)$this->getField(self::FIELD_MESSAGE);
    }

    /**
     * The conversation around the message, oldest first: [['author', 'text', 'at', 'rated'], ...].
     *
     * @return array<array{author: string, text: string, at: ?string, rated: bool}>
     */
    public function context(): array
    {
        $context = json_decode((string)$this->getField(self::FIELD_CONTEXT), true);

        return is_array($context) ? $context : [];
    }

    public function status(): string
    {
        return (string)$this->getField(self::FIELD_STATUS);
    }

    public function isResolved(): bool
    {
        return $this->status() === FeedbackStatus::RESOLVED;
    }

    public function note(): ?string
    {
        return $this->getField(self::FIELD_NOTE);
    }
}
