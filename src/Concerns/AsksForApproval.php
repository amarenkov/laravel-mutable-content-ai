<?php

namespace Amarenkov\MutableContentAi\Concerns;

use Stringable;

use Laravel\Ai\Tools\Request;

use Amarenkov\MutableContentAi\Models\Approval;
use Amarenkov\MutableContentAi\Services\Approvals;

/**
 * For a tool implementing NeedsApproval: when approvalSummary() describes the call, the tool does not act but asks
 * for approval and tells the model so; the approved action is performed later by Approvals::approve().
 */
trait AsksForApproval
{
    // protected
    abstract protected function approvals(): Approvals;

    /**
     * What the call will do, for the user to approve; null when it needs no approval.
     */
    abstract protected function approvalSummary(Request $request): ?string;

    abstract protected function perform(Request $request): Stringable|string;

    /**
     * Called when an approval is requested: the place to show it to the user.
     */
    protected function approvalRequested(Approval $approval): void
    {
    }

    // public
    public function handle(Request $request): Stringable|string
    {
        $summary = $this->approvalSummary($request);

        if ($summary === null) {
            return $this->perform($request);
        }

        $approval = $this->approvals()->request($this->name(), $request->toArray(), $summary, $request->toolCallId());
        $this->approvalRequested($approval);

        return __('mutable-content-ai::ai.awaiting_approval', ['summary' => $summary]);
    }

    public function performApproved(Approval $approval): string
    {
        return (string)$this->perform(new Request($approval->arguments(), $approval->getField(Approval::FIELD_TOOL_CALL_ID)));
    }
}
