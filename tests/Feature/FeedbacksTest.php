<?php

namespace Amarenkov\MutableContentAi\Tests\Feature;

use DomainException;

use Illuminate\Support\Carbon;

use Amarenkov\MutableContentAi\Lovs\FeedbackStatus;
use Amarenkov\MutableContentAi\Models\Feedback;
use Amarenkov\MutableContentAi\Services\Feedbacks;

use Amarenkov\MutableContentAi\Tests\Fixtures\Agents\Helper;
use Amarenkov\MutableContentAi\Tests\Fixtures\Models\User;
use Amarenkov\MutableContentAi\Tests\TestCase;

class FeedbacksTest extends TestCase
{
    // public
    public function test_a_mark_keeps_the_message_the_run_and_the_context(): void
    {
        Helper::fake(['The timer is stopped.']);
        $agent = new Helper($this->user, 'chat:1');
        $run = $agent->runs()->record('Stop the timer', $agent->prompt('Stop the timer'));

        $feedback = Feedbacks::of($this->user)->add('👎', 'The timer is stopped.', $run, [
            ['author' => 'Admin', 'text' => 'Stop the timer', 'at' => Carbon::parse('2026-10-19 10:00:00', 'UTC')],
            ['author' => 'Bot', 'text' => 'The timer is stopped.', 'rated' => true],
        ], 'chat:1:42');

        $this->assertSame('👎', $feedback->reaction());
        $this->assertSame('The timer is stopped.', $feedback->message());
        $this->assertSame($run->id, $feedback->run->id);
        $this->assertSame('chat:1', $feedback->getField(Feedback::FIELD_CONVERSATION));
        $this->assertSame(FeedbackStatus::NEW, $feedback->status());
        $this->assertSame([
            ['author' => 'Admin', 'text' => 'Stop the timer', 'at' => '2026-10-19T10:00:00+00:00', 'rated' => false],
            ['author' => 'Bot', 'text' => 'The timer is stopped.', 'at' => null, 'rated' => true],
        ], $feedback->fresh()->context());

        $again = Feedbacks::of($this->user)->add('🤮', 'The timer is stopped.', $run, [], 'chat:1:42');

        $this->assertSame($feedback->id, $again->id);
        $this->assertSame('🤮', $feedback->fresh()->reaction());
        $this->assertSame(1, Feedback::query()->count());
    }

    public function test_resolve_reopen_note_and_withdraw(): void
    {
        $feedbacks = Feedbacks::of($this->user);
        $feedback = $feedbacks->add('👎', 'Wrong answer', source: 'chat:1:1');

        $feedbacks->resolve($feedback, 'Prompt fixed');

        $this->assertTrue($feedback->fresh()->isResolved());
        $this->assertSame('Prompt fixed', $feedback->fresh()->note());
        $this->assertFalse($feedbacks->withdraw('chat:1:1'));

        $feedbacks->reopen($feedback);
        $feedbacks->note($feedback, ' ');

        $this->assertSame(FeedbackStatus::NEW, $feedback->fresh()->status());
        $this->assertNull($feedback->fresh()->note());
        $this->assertTrue($feedbacks->withdraw('chat:1:1'));
        $this->assertNull($feedbacks->find($feedback->id));
        $this->assertFalse($feedbacks->withdraw('chat:1:2'));
    }

    public function test_comment_on_what_is_wrong(): void
    {
        $feedbacks = Feedbacks::of($this->user);
        $feedback = $feedbacks->add('👎', 'Wrong answer', source: 'chat:1:1');

        $feedbacks->comment($feedback, ' Asked for the time, got the weather ');

        $this->assertSame('Asked for the time, got the weather', $feedback->fresh()->comment());

        $feedbacks->comment($feedback, '');

        $this->assertNull($feedback->fresh()->comment());

        $other = User::query()->create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'secret']);

        $this->expectException(DomainException::class);
        Feedbacks::of($other)->comment($feedback, 'Mine');
    }

    public function test_other_users_do_not_see_or_change_a_feedback(): void
    {
        $feedback = Feedbacks::of($this->user)->add('👎', 'Wrong answer');
        $other = User::query()->create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'secret']);

        $this->assertNull(Feedbacks::of($other)->find($feedback->id));
        $this->assertSame($feedback->id, Feedbacks::of($this->user)->find($feedback->id)->id);

        $this->expectException(DomainException::class);
        Feedbacks::of($other)->delete($feedback);
    }
}
