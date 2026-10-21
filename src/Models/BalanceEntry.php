<?php

namespace Amarenkov\MutableContentAi\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;

use Amarenkov\MutableContent\Models\ModelWithFields;
use Amarenkov\MutableContent\ValueObjects\Money;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Label as CommonFieldLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsRequired as CFAIsRequired;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsImmutable as CFAIsImmutable;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\LovCode as CFALovCode;

use Amarenkov\MutableContentAi\Lovs\BalanceEntryKind;
use Amarenkov\MutableContentAi\Support\Users;

/**
 * Money on the user's account at the language model provider: a top-up or the actual balance seen at the provider.
 * The remaining balance is estimated from the latest actual balance, the top-ups after it and the costs of the runs.
 */
#[Table('ai.balance_entries')]
#[ClassLabel('Balance entry')]
#[CommonFieldLabel]
class BalanceEntry extends ModelWithFields
{
    // const
    #[CFAType(Type::TYPE_INT), CFALabel('Owner'), CFAIsRequired, CFAIsImmutable]
    public const FIELD_USER_ID = 'user_id';

    #[CFAType(Type::TYPE_LOV_ITEM), CFALabel('Kind'), CFAIsRequired, CFALovCode(BalanceEntryKind::CLASS_CODE)]
    public const FIELD_KIND = 'kind';

    #[CFAType(Type::TYPE_MONEY), CFALabel('Amount'), CFAIsRequired]
    public const FIELD_AMOUNT = 'amount';

    #[CFAType(Type::TYPE_DATETIME), CFALabel('Date'), CFAIsRequired]
    public const FIELD_AT = 'at';

    #[CFAType(Type::TYPE_STRING), CFALabel('Note')]
    public const FIELD_NOTE = 'note';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    // public
    public function scopeOwnedBy(Builder $query, Authenticatable|int $user): Builder
    {
        return $query->whereField(self::FIELD_USER_ID, $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::model(), self::FIELD_USER_ID);
    }

    public function kind(): string
    {
        return (string)$this->getField(self::FIELD_KIND);
    }

    public function isTopUp(): bool
    {
        return $this->kind() === BalanceEntryKind::TOP_UP;
    }

    public function amount(): Money
    {
        return $this->getMoney(self::FIELD_AMOUNT);
    }

    public function at(): Carbon
    {
        return $this->getDateTime(self::FIELD_AT);
    }
}
