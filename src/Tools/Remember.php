<?php

namespace Amarenkov\MutableContentAi\Tools;

use DomainException;
use Stringable;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;

use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

use Amarenkov\MutableContentAi\Lovs\MemoryKind;
use Amarenkov\MutableContentAi\Services\Memories;

/**
 * Agent tool: remember something about the user for later conversations.
 */
class Remember implements Tool
{
    // public
    public function __construct(
        protected Authenticatable|int $user,
    )
    {
    }

    public function name(): string
    {
        return 'remember';
    }

    public function description(): Stringable|string
    {
        return __('mutable-content-ai::ai.tool_remember');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'text' => $schema->string()->description(__('mutable-content-ai::ai.tool_remember_text'))->required(),
            'kind' => $schema->string()->enum([MemoryKind::FACT, MemoryKind::PREFERENCE, MemoryKind::ALIAS])->description(__('mutable-content-ai::ai.tool_remember_kind')),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $memory = Memories::of($this->user)->remember($request->string('text')->toString(), $request->string('kind')->toString() ?: MemoryKind::FACT);
        } catch (DomainException $exception) {
            return $exception->getMessage();
        }

        return __('mutable-content-ai::ai.remembered', ['id' => $memory->id]);
    }
}
