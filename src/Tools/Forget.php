<?php

namespace Amarenkov\MutableContentAi\Tools;

use Stringable;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;

use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

use Amarenkov\MutableContentAi\Concerns\AsksForApproval;
use Amarenkov\MutableContentAi\Contracts\NeedsApproval;
use Amarenkov\MutableContentAi\Models\Memory;
use Amarenkov\MutableContentAi\Services\Approvals;
use Amarenkov\MutableContentAi\Services\Memories;

/**
 * Agent tool: forget one of the user's memories by its number; with approvals given, only after the user approves.
 */
class Forget implements Tool, NeedsApproval
{
    use AsksForApproval;

    // protected
    protected function approvals(): Approvals
    {
        return $this->approvals ?? Approvals::of($this->user);
    }

    protected function approvalSummary(Request $request): ?string
    {
        if (!$this->approvals) {
            return null;
        }

        $memory = Memory::query()->ownedBy($this->user)->find($request->integer('id'));

        return $memory ? __('mutable-content-ai::ai.forget_summary', ['text' => $memory->text()]) : null;
    }

    protected function perform(Request $request): Stringable|string
    {
        $id = $request->integer('id');

        return Memories::of($this->user)->forget($id)
            ? __('mutable-content-ai::ai.forgotten', ['id' => $id])
            : __('mutable-content-ai::ai.no_memory', ['id' => $id]);
    }

    // public
    public function __construct(
        protected Authenticatable|int $user,
        protected ?Approvals $approvals = null,
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
}
