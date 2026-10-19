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
 * Language model settings of a user: the provider with the user's own key, the model and the daily limit.
 */
#[Table('ai.settings')]
#[ClassLabel('AI settings')]
#[CommonFieldLabel]
class Settings extends ModelWithFields
{
    // const
    #[CFAType(Type::TYPE_INT), CFALabel('Owner'), CFAIsRequired, CFAIsImmutable]
    public const FIELD_USER_ID = 'user_id';

    #[CFAType(Type::TYPE_BOOL), CFALabel('Enabled')]
    public const FIELD_IS_ENABLED = 'is_enabled';

    #[CFAType(Type::TYPE_STRING), CFALabel('Driver')]
    public const FIELD_DRIVER = 'driver';

    #[CFAType(Type::TYPE_STRING), CFALabel('API URL')]
    public const FIELD_URL = 'url';

    #[CFAType(Type::TYPE_SECRET), CFALabel('API key')]
    public const FIELD_API_KEY = 'api_key';

    #[CFAType(Type::TYPE_STRING), CFALabel('Model')]
    public const FIELD_MODEL = 'model';

    #[CFAType(Type::TYPE_INT), CFALabel('Daily limit'), CFATypeSettings([TypeSettings::ALLOW_NEGATIVE => false, TypeSettings::ALLOW_ZERO => false])]
    public const FIELD_DAILY_LIMIT = 'daily_limit';

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

    public function isEnabled(): bool
    {
        return (bool)$this->getField(self::FIELD_IS_ENABLED);
    }

    public function hasApiKey(): bool
    {
        return $this->getSecret(self::FIELD_API_KEY) !== null;
    }

    public function dailyLimit(): ?int
    {
        $limit = $this->getField(self::FIELD_DAILY_LIMIT);

        return $limit === null ? null : (int)$limit;
    }
}
