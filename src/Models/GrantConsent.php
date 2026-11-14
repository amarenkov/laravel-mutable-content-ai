<?php

namespace Amarenkov\MutableContentAi\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Label as CommonFieldLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsRequired as CFAIsRequired;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsImmutable as CFAIsImmutable;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\ObjectClass as CFAObjectClass;
use Amarenkov\MutableContent\Models\Concerns\OwnedByUser;
use Amarenkov\MutableContent\Helpers\UserHelper;

/**
 * The decision of a user on a grant offered to them: accepted or declined. An acceptance holds for the provider
 * endpoint of the owner's settings at that moment: when the owner points the settings elsewhere, it is asked again.
 */
#[Table('ai.grant_consents')]
#[ClassLabel('AI grant consent')]
#[CommonFieldLabel]
class GrantConsent extends ModelWithFields
{
    use OwnedByUser;

    // const
    #[CFAType(Type::TYPE_OBJECT), CFALabel('Grant'), CFAObjectClass(Grant::class), CFAIsRequired, CFAIsImmutable]
    public const FIELD_GRANT_ID = 'grant_id';

    #[CFAType(Type::TYPE_INT), CFALabel('User'), CFAIsRequired, CFAIsImmutable]
    public const FIELD_USER_ID = 'user_id';

    #[CFAType(Type::TYPE_BOOL), CFALabel('Accepted'), CFAIsRequired]
    public const FIELD_IS_ACCEPTED = 'is_accepted';

    #[CFAType(Type::TYPE_STRING), CFALabel('Endpoint')]
    public const FIELD_ENDPOINT = 'endpoint';

    // public
    public function ownerField(): string
    {
        return self::FIELD_USER_ID;
    }

    /**
     * @return BelongsTo<Grant, $this>
     */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(Grant::class, self::FIELD_GRANT_ID)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserHelper::model('mutable-content-ai'), self::FIELD_USER_ID);
    }

    public function grantId(): int
    {
        return (int)$this->getField(self::FIELD_GRANT_ID);
    }

    public function userId(): int
    {
        return (int)$this->getField(self::FIELD_USER_ID);
    }

    public function isAccepted(): bool
    {
        return (bool)$this->getField(self::FIELD_IS_ACCEPTED);
    }

    public function endpoint(): ?string
    {
        return $this->getField(self::FIELD_ENDPOINT);
    }
}
