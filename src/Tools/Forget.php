<?php

namespace Amarenkov\MutableContentAi\Tools;

use Stringable;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;

use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

use Amarenkov\MutableContentAi\Services\Memories;

/**
 * Agent tool: forget one of the user's memories by its number.
 */
class Forget implements Tool
{
    // public
    public function __construct(
        protected Authenticatable|int $user,
    )
    {
    }

    public function name(): string
    {
        return 'forget';
    }

    public function description(): Stringable|string
    {
        return __('mutable-content-ai::ai.tool_forget');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description(__('mutable-content-ai::ai.tool_forget_id'))->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $id = $request->integer('id');

        return Memories::of($this->user)->forget($id)
            ? __('mutable-content-ai::ai.forgotten', ['id' => $id])
            : __('mutable-content-ai::ai.no_memory', ['id' => $id]);
    }
}
