<?php

namespace Amarenkov\MutableContentAi\Tools;

use Closure;
use Stringable;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;

use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContentAi\Schemas\FieldSchema;
use Amarenkov\MutableContentAi\Support\TextSearch;

/**
 * Agent tool: find records of a class by words of a title field and by exact field filters, newest first.
 * The application gives the query, so it decides whose records the agent sees.
 */
class FindRecords implements Tool
{
    // protected
    protected function fieldSchema(): FieldSchema
    {
        return FieldSchema::for($this->class)->only($this->filters)->required($this->filters, false);
    }

    // public
    /**
     * @param Closure(): Builder<ModelWithFields> $query records the user may see
     * @param class-string<ModelWithFields> $class
     * @param array<string> $filters field codes the agent may filter by
     */
    public function __construct(
        protected string $name,
        protected string $description,
        protected Closure $query,
        protected string $class,
        protected string $titleField,
        protected array $filters = [],
        protected int $limit = 10,
        protected int $scan = 3000,
    )
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): Stringable|string
    {
        return $this->description;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'text' => $schema->string()->description(__('mutable-content-ai::ai.find_text')),
        ] + $this->fieldSchema()->build($schema);
    }

    public function handle(Request $request): Stringable|string
    {
        $words = TextSearch::words($request->string('text')->toString());
        $query = ($this->query)();

        foreach ($this->fieldSchema()->values($request->toArray()) as $code => $value) {
            $query->whereField($code, $value);
        }

        $total = $words ? null : $query->clone()->count();
        $records = $query->orderByDesc('id')->limit($words ? $this->scan : $this->limit)->get()->all();

        if ($words) {
            $records = TextSearch::best($records, fn (ModelWithFields $record) => (string)$record->getField($this->titleField), $words);
            $total = count($records);
        }

        if (!$records) {
            return __('mutable-content-ai::ai.found_nothing');
        }

        $lines = [];

        foreach (array_slice($records, 0, $this->limit) as $record) {
            $lines[] = '#'.$record->id.' '.mb_substr((string)$record->getField($this->titleField), 0, 120);
        }

        return __('mutable-content-ai::ai.found', ['total' => $total, 'count' => count($lines)])."\n".implode("\n", $lines);
    }
}
