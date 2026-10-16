<?php

namespace Amarenkov\MutableContentAi\Schemas;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type as SchemaType;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Domain\LovRegistry;
use Amarenkov\MutableContent\Models\ModelWithFields;

/**
 * Agent tool parameters from the field definitions of a class: types, labels, LOV items as enums, object references as
 * numbers, required flags. FieldSchema::for(Account::class)->only([...])->describe([...])->build($schema) in a tool's
 * schema(), ->values($request->toArray()) in its handle().
 */
class FieldSchema
{
    // const
    protected const NUMBER_TYPES = [
        Type::TYPE_FLOAT,
        Type::TYPE_MONEY,
        Type::TYPE_WEIGHT,
        Type::TYPE_DENSITY,
        Type::TYPE_SURFACE_DENSITY,
        Type::TYPE_LENGTH,
        Type::TYPE_AREA,
        Type::TYPE_VOLUME,
    ];

    // static
    /**
     * @param class-string<ModelWithFields> $class
     */
    public static function for(string $class, ?array $scopes = null): self
    {
        return new self($class::getFieldDefinitions($scopes));
    }

    // protected
    /** @var array<string> */
    protected array $only = [];

    /** @var array<string, bool> */
    protected array $required = [];

    /** @var array<string, string> */
    protected array $descriptions = [];

    /**
     * @return array<string, Field>
     */
    protected function fields(): array
    {
        if ($this->only === []) {
            return $this->fields;
        }

        $result = [];

        foreach ($this->only as $code) {
            if (isset($this->fields[$code])) {
                $result[$code] = $this->fields[$code];
            }
        }

        return $result;
    }

    protected function isRequired(Field $field): bool
    {
        return $this->required[$field->code] ?? $field->usage()->isRequired;
    }

    /**
     * @return array<string, string> code => label
     */
    protected function lovItems(Field $field): array
    {
        return $field->lovCode ? app(LovRegistry::class)->getLovItemsOptions($field->lovCode) : [];
    }

    protected function description(Field $field): string
    {
        $parts = [__((string)($field->label ?? $field->code))];

        $parts[] = match ($field->fieldType) {
            Type::TYPE_DATE => __('mutable-content-ai::ai.schema_date'),
            Type::TYPE_DATETIME => __('mutable-content-ai::ai.schema_datetime', ['timezone' => ModelWithFields::displayTimezone()]),
            Type::TYPE_OBJECT => __('mutable-content-ai::ai.schema_object'),
            Type::TYPE_LOV_ITEM => implode(', ', array_map(fn ($code, $label) => $code.' - '.$label, array_keys($this->lovItems($field)), $this->lovItems($field))),
            default => null,
        };

        $parts[] = $this->descriptions[$field->code] ?? null;

        return implode('. ', array_filter($parts, fn ($part) => $part !== null && $part !== ''));
    }

    protected function type(JsonSchema $schema, Field $field): SchemaType
    {
        $type = match (true) {
            $field->fieldType === Type::TYPE_INT, $field->fieldType === Type::TYPE_OBJECT => $schema->integer(),
            $field->fieldType === Type::TYPE_BOOL => $schema->boolean(),
            in_array($field->fieldType, self::NUMBER_TYPES, true) => $schema->number(),
            default => $schema->string(),
        };

        if ($field->fieldType === Type::TYPE_LOV_ITEM && $this->lovItems($field)) {
            $type->enum(array_map('strval', array_keys($this->lovItems($field))));
        }

        return $type->description($this->description($field));
    }

    // public
    /**
     * @param array<string, Field> $fields
     */
    public function __construct(
        protected array $fields,
    )
    {
    }

    /**
     * Only these fields, in this order.
     *
     * @param array<string> $codes
     */
    public function only(array $codes): static
    {
        $this->only = $codes;

        return $this;
    }

    /**
     * Required or optional regardless of the field definitions.
     *
     * @param array<string> $codes
     */
    public function required(array $codes, bool $required = true): static
    {
        foreach ($codes as $code) {
            $this->required[$code] = $required;
        }

        return $this;
    }

    /**
     * Extra words for the model about fields: code => text.
     *
     * @param array<string, string> $descriptions
     */
    public function describe(array $descriptions): static
    {
        $this->descriptions = $descriptions + $this->descriptions;

        return $this;
    }

    /**
     * Tool parameters: code => schema type.
     *
     * @return array<string, SchemaType>
     */
    public function build(JsonSchema $schema): array
    {
        $result = [];

        foreach ($this->fields() as $code => $field) {
            $type = $this->type($schema, $field);
            $result[$code] = $this->isRequired($field) ? $type->required() : $type;
        }

        return $result;
    }

    /**
     * Field values from tool arguments: only the fields of the schema, numbers as numbers, LOV items only known ones,
     * empty values dropped.
     *
     * @return array<string, mixed>
     */
    public function values(array $arguments): array
    {
        $result = [];

        foreach ($this->fields() as $code => $field) {
            $value = $arguments[$code] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $value = match (true) {
                $field->fieldType === Type::TYPE_INT, $field->fieldType === Type::TYPE_OBJECT => is_numeric($value) ? (int)$value : null,
                $field->fieldType === Type::TYPE_BOOL => (bool)$value,
                in_array($field->fieldType, self::NUMBER_TYPES, true) => is_numeric($value) ? $value + 0 : null,
                $field->fieldType === Type::TYPE_LOV_ITEM => array_key_exists((string)$value, $this->lovItems($field)) ? (string)$value : null,
                default => is_scalar($value) ? (string)$value : null,
            };

            if ($value !== null) {
                $result[$code] = $value;
            }
        }

        return $result;
    }
}
