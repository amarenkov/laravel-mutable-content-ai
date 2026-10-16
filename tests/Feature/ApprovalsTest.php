<?php

namespace Amarenkov\MutableContentAi\Tests\Feature;

use DomainException;

use Laravel\Ai\Responses\Data\ToolCall;

use Amarenkov\MutableContentAi\Contracts\NeedsApproval;
use Amarenkov\MutableContentAi\Lovs\ApprovalStatus;
use Amarenkov\MutableContentAi\Models\Approval;
use Amarenkov\MutableContentAi\Models\Run;
use Amarenkov\MutableContentAi\Services\Approvals;
use Amarenkov\MutableContentAi\Services\Memories;
use Amarenkov\MutableContentAi\Tools\Forget;

use Amarenkov\MutableContentAi\Tests\Fixtures\Agents\Helper;
use Amarenkov\MutableContentAi\Tests\Fixtures\Models\User;
use Amarenkov\MutableContentAi\Tests\TestCase;

class ApprovalsTest extends TestCase
{
    // public
    public function test_an_action_waits_for_approval_and_is_performed_on_approve(): void
    {
        $memory = Memories::of($this->user)->remember('Pete is @petrov');
        Helper::fake([new ToolCall('call-1', 'forget', ['id' => $memory->id]), 'Waiting.']);
        $agent = new Helper($this->user, 'chat:1', confirm: true);

        $response = $agent->prompt('Forget about Pete');
        $run = $agent->runs()->record('Forget about Pete', $response, answer: 'Waiting for approval.');
        $approval = Approval::query()->ownedBy($this->user)->sole();

        $this->assertCount(1, Memories::of($this->user)->all());
        $this->assertTrue($approval->isPending());
        $this->assertSame('forget «Pete is @petrov»', $approval->summary());
        $this->assertSame(['id' => $memory->id], $approval->arguments());
        $this->assertSame($run->id, $approval->run->id);
        $this->assertStringStartsWith('Not done yet', $run->toolCalls()[0]['result']);
        $this->assertSame('Waiting for approval.', $run->getField(Run::FIELD_ANSWER));

        $approvals = Approvals::of($this->user, 'chat:1');
        $result = $approvals->approve($approval, new Forget($this->user, $approvals));

        $this->assertSame('Memory #'.$memory->id.' forgotten.', $result);
        $this->assertCount(0, Memories::of($this->user)->all());
        $this->assertSame(ApprovalStatus::APPROVED, $approval->fresh()->status());
        $this->assertStringEndsWith('The user approved: '.$result, $run->fresh()->toolCalls()[0]['result']);

        $this->expectException(DomainException::class);
        $approvals->approve($approval->fresh(), new Forget($this->user, $approvals));
    }

    public function test_reject_and_other_users(): void
    {
        $memory = Memories::of($this->user)->remember('Works from 10');
        $approvals = Approvals::of($this->user);
        $approval = $approvals->request('forget', ['id' => $memory->id], 'forget «Works from 10»');
        $other = User::query()->create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'secret']);

        $this->assertNull(Approvals::of($other)->find($approval->id));

        try {
            Approvals::of($other)->reject($approval);
            $this->fail('Rejected by another user');
        } catch (DomainException) {
        }

        try {
            $approvals->approve($approval, new class implements NeedsApproval {
                public function name(): string
                {
                    return 'remember';
                }

                public function performApproved(Approval $approval): string
                {
                    return 'wrong';
                }
            });
            $this->fail('Approved by another tool');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('another tool', $exception->getMessage());
        }

        $approvals->reject($approval);

        $this->assertSame(ApprovalStatus::REJECTED, $approval->fresh()->status());
        $this->assertSame('not approved', $approval->fresh()->result());
        $this->assertCount(1, Memories::of($this->user)->all());
    }

    public function test_without_approvals_the_tool_acts_at_once(): void
    {
        $memory = Memories::of($this->user)->remember('Short');
        Helper::fake([new ToolCall('call-1', 'forget', ['id' => $memory->id]), 'Done.']);

        new Helper($this->user)->prompt('Forget it');

        $this->assertCount(0, Memories::of($this->user)->all());
        $this->assertSame(0, Approval::query()->count());
    }
}
