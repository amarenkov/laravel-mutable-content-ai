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

use Amarenkov\MutableContentAi\Lovs\RunStatus;
use Amarenkov\MutableContentAi\Support\Users;

/**
 * One prompt to an agent: the user's message, the tool calls with their results, the answer, the model and the tokens.
 * Runs of one conversation are its history for the next prompt. The run is paid by the owner of the settings it used:
 * the user, or the owner of a grant (an old run without a payer is paid by its user).
 */
#[Table('ai.runs')]
#[ClassLabel('Agent run')]
#[CommonFieldLabel]
class Run extends ModelWithFields
{
    // const
    #[CFAType(Type::TYPE_INT), CFALabel('Owner'), CFAIsRequired, CFAIsImmutable]
    public const FIELD_USER_ID = 'user_id';

    #[CFAType(Type::TYPE_STRING), CFALabel('Agent'), CFAIsRequired, CFAIsImmutable]
    public const FIELD_AGENT = 'agent';

    #[CFAType(Type::TYPE_STRING), CFALabel('Conversation'), CFAIsImmutable]
    public const FIELD_CONVERSATION = 'conversation';

    #[CFAType(Type::TYPE_LOV_ITEM), CFALabel('Status'), CFAIsRequired, CFALovCode(RunStatus::CLASS_CODE)]
    public const FIELD_STATUS = 'status';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Prompt'), CFAIsRequired]
    public const FIELD_PROMPT = 'prompt';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Answer')]
    public const FIELD_ANSWER = 'answer';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Tool calls')]
    public const FIELD_TOOL_CALLS = 'tool_calls';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Error')]
    public const FIELD_ERROR = 'error';

    #[CFAType(Type::TYPE_STRING), CFALabel('Provider')]
    public const FIELD_PROVIDER = 'provider';

    #[CFAType(Type::TYPE_STRING), CFALabel('Model')]
    public const FIELD_MODEL = 'model';

    #[CFAType(Type::TYPE_INT), CFALabel('Input tokens')]
    public const FIELD_INPUT_TOKENS = 'input_tokens';

    #[CFAType(Type::TYPE_INT), CFALabel('Output tokens')]
    public const FIELD_OUTPUT_TOKENS = 'output_tokens';

    #[CFAType(Type::TYPE_INT), CFALabel('Duration, ms')]
    public const FIELD_DURATION_MS = 'duration_ms';

    #[CFAType(Type::TYPE_INT), CFALabel('Paid by'), CFAIsImmutable]
    public const FIELD_PAYER_ID = 'payer_id';

    #[CFAType(Type::TYPE_OBJECT), CFALabel('Grant'), CFAObjectClass(Grant::class), CFAIsImmutable]
    public const FIELD_GRANT_ID = 'grant_id';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    // public
    public function scopeOwnedBy(Builder $query, Authenticatable|int $user): Builder
    {
        return $query->whereField(self::FIELD_USER_ID, $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user);
    }

    /**
     * Runs on the user's settings: own and through the user's grants.
     */
    public function scopePaidBy(Builder $query, Authenticatable|int $user): Builder
    {
        $userId = $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user;

        return $query->where(fn (Builder $query) => $query
            ->whereField(self::FIELD_PAYER_ID, $userId)
            ->orWhere(fn (Builder $query) => $query->whereNull(self::FIELD_PAYER_ID)->whereField(self::FIELD_USER_ID, $userId)));
    }

    public function scopeInConversation(Builder $query, string $conversation): Builder
    {
        return $query->whereField(self::FIELD_CONVERSATION, $conversation);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::model(), self::FIELD_USER_ID);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Users::model(), self::FIELD_PAYER_ID);
    }

    /**
     * @return BelongsTo<Grant, $this>
     */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(Grant::class, self::FIELD_GRANT_ID)->withTrashed();
    }

    /**
     * Tool calls with their results: [['id', 'name', 'arguments', 'result'], ...].
     *
     * @return array<array{id: string, name: string, arguments: array, result: string}>
     */
    public function toolCalls(): array
    {
        $calls = json_decode((string)$this->getField(self::FIELD_TOOL_CALLS), true);

        return is_array($calls) ? $calls : [];
    }

    public function completed(): bool
    {
        return $this->getField(self::FIELD_STATUS) === RunStatus::COMPLETED;
    }
}
