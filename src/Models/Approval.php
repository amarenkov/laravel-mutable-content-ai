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

use Amarenkov\MutableContentAi\Lovs\ApprovalStatus;
use Amarenkov\MutableContentAi\Support\Users;

/**
 * An agent action waiting for the user's word: the tool, its arguments and what it will do. Approved, the action is
 * performed by the application without asking the model again.
 */
#[Table('ai.approvals')]
#[ClassLabel('Approval')]
#[CommonFieldLabel]
class Approval extends ModelWithFields
{
    // const
    #[CFAType(Type::TYPE_INT), CFALabel('Owner'), CFAIsRequired, CFAIsImmutable]
    public const FIELD_USER_ID = 'user_id';

    #[CFAType(Type::TYPE_STRING), CFALabel('Conversation'), CFAIsImmutable]
    public const FIELD_CONVERSATION = 'conversation';

    #[CFAType(Type::TYPE_OBJECT), CFALabel('Agent run'), CFAObjectClass(Run::class)]
    public const FIELD_RUN_ID = 'run_id';

    #[CFAType(Type::TYPE_STRING), CFALabel('Tool call'), CFAIsImmutable]
    public const FIELD_TOOL_CALL_ID = 'tool_call_id';

    #[CFAType(Type::TYPE_STRING), CFALabel('Tool'), CFAIsRequired, CFAIsImmutable]
    public const FIELD_TOOL = 'tool';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Arguments'), CFAIsImmutable]
    public const FIELD_ARGUMENTS = 'arguments';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Summary'), CFAIsRequired, CFAIsImmutable]
    public const FIELD_SUMMARY = 'summary';

    #[CFAType(Type::TYPE_LOV_ITEM), CFALabel('Status'), CFAIsRequired, CFALovCode(ApprovalStatus::CLASS_CODE)]
    public const FIELD_STATUS = 'status';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Result')]
    public const FIELD_RESULT = 'result';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    // public
    public function scopeOwnedBy(Builder $query, Authenticatable|int $user): Builder
    {
        return $query->whereField(self::FIELD_USER_ID, $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereField(self::FIELD_STATUS, ApprovalStatus::PENDING);
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

    public function tool(): string
    {
        return (string)$this->getField(self::FIELD_TOOL);
    }

    public function arguments(): array
    {
        $arguments = json_decode((string)$this->getField(self::FIELD_ARGUMENTS), true);

        return is_array($arguments) ? $arguments : [];
    }

    public function summary(): string
    {
        return (string)$this->getField(self::FIELD_SUMMARY);
    }

    public function status(): string
    {
        return (string)$this->getField(self::FIELD_STATUS);
    }

    public function isPending(): bool
    {
        return $this->status() === ApprovalStatus::PENDING;
    }

    public function result(): ?string
    {
        return $this->getField(self::FIELD_RESULT);
    }
}
