<?php

namespace Amarenkov\MutableContentAi\Tests\Feature;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;

use Laravel\Ai\Tools\Request;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Domain\Field\Usage;

use Amarenkov\MutableContentAi\Lovs\MemoryKind;
use Amarenkov\MutableContentAi\Models\Memory;
use Amarenkov\MutableContentAi\Models\Run;
use Amarenkov\MutableContentAi\Schemas\FieldSchema;
use Amarenkov\MutableContentAi\Services\Memories;
use Amarenkov\MutableContentAi\Support\TextSearch;
use Amarenkov\MutableContentAi\Tools\FindRecords;

use Amarenkov\MutableContentAi\Tests\TestCase;

class SchemasTest extends TestCase
{
    // protected
    protected function schema(FieldSchema $fieldSchema): array
    {
        return array_map(fn ($type) => $type->toArray(), $fieldSchema->build(new JsonSchemaTypeFactory()));
    }

    // public
    public function test_schema_from_field_definitions(): void
    {
        $schema = $this->schema(FieldSchema::for(Memory::class)->only([Memory::FIELD_KIND, Memory::FIELD_TEXT, 'unknown'])->describe([Memory::FIELD_TEXT => 'One sentence']));

        $this->assertSame([Memory::FIELD_KIND, Memory::FIELD_TEXT], array_keys($schema));
        $this->assertSame('string', $schema[Memory::FIELD_KIND]['type']);
        $this->assertSame([MemoryKind::ALIAS, MemoryKind::FACT, MemoryKind::PREFERENCE], $schema[Memory::FIELD_KIND]['enum']);
        $this->assertSame('Kind. alias - Alias, fact - Fact, preference - Preference', $schema[Memory::FIELD_KIND]['description']);
        $this->assertSame('Text. One sentence', $schema[Memory::FIELD_TEXT]['description']);

        $run = $this->schema(FieldSchema::for(Run::class)->only([Run::FIELD_INPUT_TOKENS, Run::FIELD_PROMPT])->required([Run::FIELD_PROMPT], false));

        $this->assertSame('integer', $run[Run::FIELD_INPUT_TOKENS]['type']);
        $this->assertSame('string', $run[Run::FIELD_PROMPT]['type']);

        $tool = new FindRecords('find', 'Find', fn () => Memory::query(), Memory::class, Memory::FIELD_TEXT, [Memory::FIELD_KIND]);
        $object = (new JsonSchemaTypeFactory())->object($tool->schema(new JsonSchemaTypeFactory()))->toArray();

        $this->assertSame(['text', Memory::FIELD_KIND], array_keys($object['properties']));
        $this->assertArrayNotHasKey('required', $object);
    }

    public function test_types_and_values(): void
    {
        $field = fn (string $code, string $type, bool $required = false) => (new Field($code, $type))->label(ucfirst($code))->addUsage((new Usage('test'))->isRequired($required));
        $fieldSchema = new FieldSchema([
            'date' => $field('date', Type::TYPE_DATE, true),
            'at' => $field('at', Type::TYPE_DATETIME),
            'amount' => $field('amount', Type::TYPE_MONEY),
            'parent_id' => $field('parent_id', Type::TYPE_OBJECT),
            'done' => $field('done', Type::TYPE_BOOL),
            'kind' => $field('kind', Type::TYPE_LOV_ITEM)->lovCode(MemoryKind::CLASS_CODE),
        ]);
        $schema = $this->schema($fieldSchema);

        $this->assertSame(['string', 'string', 'number', 'integer', 'boolean', 'string'], array_column($schema, 'type'));
        $this->assertSame('Date. Date as YYYY-MM-DD', $schema['date']['description']);
        $this->assertStringContainsString('YYYY-MM-DD HH:MM', $schema['at']['description']);
        $this->assertSame('Parent_id. Record number', $schema['parent_id']['description']);

        $this->assertSame(
            ['date' => '2026-10-16', 'amount' => 12.5, 'parent_id' => 7, 'done' => true],
            $fieldSchema->values(['date' => '2026-10-16', 'at' => '', 'amount' => '12.5', 'parent_id' => '7', 'done' => 1, 'kind' => 'unknown', 'other' => 'x']),
        );
    }

    public function test_find_records(): void
    {
        $memories = Memories::of($this->user);
        $memories->remember('Pete is @petrov', MemoryKind::ALIAS);
        $memories->remember('Works from 10 to 19', MemoryKind::PREFERENCE);
        $memories->remember('Has two cats', MemoryKind::FACT);

        $tool = new FindRecords('find', 'Find', fn () => Memory::query()->ownedBy($this->user), Memory::class, Memory::FIELD_TEXT, [Memory::FIELD_KIND]);

        $this->assertStringStartsWith("Found: 3, showing 3:\n#", (string)$tool->handle(new Request([])));
        $this->assertStringEndsWith('Pete is @petrov', (string)$tool->handle(new Request(['text' => 'who is Pete'])));
        $this->assertStringEndsWith('Works from 10 to 19', (string)$tool->handle(new Request([Memory::FIELD_KIND => MemoryKind::PREFERENCE])));
        $this->assertSame('Nothing found.', (string)$tool->handle(new Request(['text' => 'dogs'])));

        $this->assertSame(['petrov'], TextSearch::words('Is it @petrov?', ['it']));
    }
}
