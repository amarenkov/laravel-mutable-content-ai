<?php

namespace Amarenkov\MutableContentAi\Services;

use DomainException;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

use Amarenkov\MutableContentAi\Contracts\NeedsApproval;
use Amarenkov\MutableContentAi\Lovs\ApprovalStatus;
use Amarenkov\MutableContentAi\Models\Approval;
use Amarenkov\MutableContentAi\Models\Run;

/**
 * Actions of agents waiting for a user's approval: Approvals::of($user, $conversation)->request() / find() /
 * approve($approval, $tool) / reject($approval). The outcome is added to the tool result in the run journal,
 * so the agent sees it in the history.
 */
class Approvals
{
    // static
    public static function of(Authenticatable|int $user, ?string $conversation = null): self
    {
        return new self($user instanceof Authenticatable ? (int)$user->getAuthIdentifier() : $user, $conversation);
    }

    // protected
    protected function resolve(Approval $approval, string $status, string $result): void
    {
        $approval->fill([Approval::FIELD_STATUS => $status, Approval::FIELD_RESULT => $result]);
        $approval->withLogContext()->user($this->userId);
        $approval->save();

        $run = $approval->run;
        $callId = $approval->getField(Approval::FIELD_TOOL_CALL_ID);

        if (!$run || $callId === null) {
            return;
        }

        $outcome = __('mutable-content-ai::ai.approval_'.$status, ['result' => $result]);
        $calls = array_map(
            fn (array $call) => $call['id'] === $callId ? ['result' => trim($call['result'].' '.$outcome)] + $call : $call,
            $run->toolCalls(),
        );

        $run->fill([Run::FIELD_TOOL_CALLS => json_encode($calls, JSON_UNESCAPED_UNICODE)]);
        $run->withLogContext()->user($this->userId);
        $run->save();
    }

    protected function ensurePending(Approval $approval): void
    {
        if ((int)$approval->getField(Approval::FIELD_USER_ID) !== $this->userId || !$approval->isPending()) {
            throw new DomainException(__('mutable-content-ai::ai.approval_not_pending'));
        }
    }

    // public
    public function __construct(
        public readonly int $userId,
        public readonly ?string $conversation = null,
    )
    {
    }

    public function request(string $tool, array $arguments, string $summary, ?string $toolCallId = null): Approval
    {
        $approval = new Approval();
        $approval->fill(array_filter([
            Approval::FIELD_USER_ID => $this->userId,
            Approval::FIELD_CONVERSATION => $this->conversation,
            Approval::FIELD_TOOL_CALL_ID => $toolCallId,
            Approval::FIELD_TOOL => $tool,
            Approval::FIELD_ARGUMENTS => json_encode($arguments, JSON_UNESCAPED_UNICODE),
            Approval::FIELD_SUMMARY => $summary,
            Approval::FIELD_STATUS => ApprovalStatus::PENDING,
        ], fn ($value) => $value !== null));
        $approval->withLogContext()->user($this->userId);
        $approval->save();

        return $approval;
    }

    /**
     * An approval of this user, any status; null when there is no such one.
     */
    public function find(int $id): ?Approval
    {
        return Approval::query()->ownedBy($this->userId)->find($id);
    }

    /**
     * Perform the approved action by its tool and keep the result. Throws DomainException when the approval is not
     * pending or the tool is another one.
     */
    public function approve(Approval $approval, NeedsApproval $tool): string
    {
        $this->ensurePending($approval);

        if ($tool->name() !== $approval->tool()) {
            throw new DomainException(__('mutable-content-ai::ai.approval_wrong_tool'));
        }

        return DB::transaction(function () use ($approval, $tool) {
            $result = $tool->performApproved($approval);
            $this->resolve($approval, ApprovalStatus::APPROVED, $result);

            return $result;
        });
    }

    public function reject(Approval $approval, ?string $reason = null): void
    {
        $this->ensurePending($approval);

        $this->resolve($approval, ApprovalStatus::REJECTED, $reason ?? __('mutable-content-ai::ai.approval_rejected_default'));
    }

    /**
     * Link the approvals requested during the run to it by the tool call: they were asked for before the run was
     * recorded.
     */
    public function attach(Run $run): void
    {
        $callIds = array_column($run->toolCalls(), 'id');

        if ($callIds === []) {
            return;
        }

        $approvals = Approval::query()
            ->ownedBy($this->userId)
            ->whereNull(Approval::FIELD_RUN_ID)
            ->where('created_at', '>=', $run->created_at->copy()->subDay())
            ->get();

        foreach ($approvals as $approval) {
            if (in_array($approval->getField(Approval::FIELD_TOOL_CALL_ID), $callIds, true)) {
                $approval->fill([Approval::FIELD_RUN_ID => $run->id]);
                $approval->withLogContext()->user($this->userId);
                $approval->save();
            }
        }
    }
}
