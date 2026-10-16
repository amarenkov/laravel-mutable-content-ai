<?php

namespace Amarenkov\MutableContentAi\Models;

use Illuminate\Database\Eloquent\Attributes\Table;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Field\Common\Label as CommonFieldLabel;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsRequired as CFAIsRequired;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\IsImmutable as CFAIsImmutable;

/**
 * The text of a registered prompt edited in the application; an empty text means the default from the code.
 * Versions are the core change log of the record.
 */
#[Table('ai.prompts')]
#[ClassLabel('Prompt')]
#[CommonFieldLabel]
class Prompt extends ModelWithFields
{
    // const
    #[CFAType(Type::TYPE_STRING), CFALabel('Code'), CFAIsRequired, CFAIsImmutable]
    public const FIELD_CODE = 'code';

    #[CFAType(Type::TYPE_TEXT), CFALabel('Text')]
    public const FIELD_TEXT = 'text';

    // static
    protected static array|bool|null $fieldDefinitions = null;

    // public
    public function text(): ?string
    {
        $text = $this->getField(self::FIELD_TEXT);

        return $text === null || trim($text) === '' ? null : $text;
    }
}
