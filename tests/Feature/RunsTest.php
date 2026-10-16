<?php

namespace Amarenkov\MutableContentAi\Tests\Feature;

use RuntimeException;

use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\ToolCall;

use Amarenkov\MutableContentAi\Lovs\RunStatus;
use Amarenkov\MutableContentAi\Models\Run;
use Amarenkov\MutableContentAi\Services\Memories;

use Amarenkov\MutableContentAi\Tests\Fixtures\Agents\Helper;
use Amarenkov\MutableContentAi\Tests\TestCase;

class RunsTest extends TestCase
{
    // protected
    protected function ask(Helper $agent, string $prompt): Run
    {
        return $agent->runs()->record($prompt, $agent->prompt($prompt), 25);
    }

    // public
    public function test_a_run_keeps_tool_calls_and_replays_them(): void
    {
        Helper::fake([new ToolCall('call-1', 'remember', ['text' => 'Pete is @petrov', 'kind' => 'alias']), 'Done.', 'Hello again.']);
        $agent = new Helper($this->user, 'chat:1');

        $run = $this->ask($agent, 'Remember: Pete is @petrov');
        $memory = Memories::of($this->user)->all()->first();

        $this->assertTrue($run->completed());
        $this->assertSame('Done.', $run->getField(Run::FIELD_ANSWER));
        $this->assertSame(25, $run->getField(Run::FIELD_DURATION_MS));
        $this->assertSame(Helper::class, $run->getField(Run::FIELD_AGENT));
        $this->assertSame([[
            'id' => 'call-1',
            'name' => 'remember',
            'arguments' => ['text' => 'Pete is @petrov', 'kind' => 'alias'],
            'result' => 'Remembered as #'.$memory->id.'.',
        ]], $run->toolCalls());

        $history = $agent->runs()->history();

        $this->assertCount(4, $history);
        $this->assertInstanceOf(UserMessage::class, $history[0]);
        $this->assertSame('Remember: Pete is @petrov', $history[0]->content);
        $this->assertInstanceOf(AssistantMessage::class, $history[1]);
        $this->assertSame('remember', $history[1]->toolCalls->first()->name);
        $this->assertInstanceOf(ToolResultMessage::class, $history[2]);
        $this->assertSame('Remembered as #'.$memory->id.'.', $history[2]->toolResults->first()->result);
        $this->assertSame('Done.', $history[3]->content);

        $this->ask($agent, 'Hi');

        Helper::assertPrompted(fn ($prompt) => str_contains((string)$prompt->agent->instructions(), 'Pete is @petrov'));
        $this->assertCount(6, $agent->runs()->history());
    }

    public function test_history_is_per_conversation_and_skips_failed_runs(): void
    {
        Helper::fake(['One', 'Two']);
        $first = new Helper($this->user, 'chat:1');
        $second = new Helper($this->user, 'chat:2');

        $this->ask($first, 'First');
        $this->ask($second, 'Second');
        $failed = $first->runs()->failed('Broken', new RuntimeException('Connection reset'), 10);

        $this->assertSame(RunStatus::FAILED, $failed->getField(Run::FIELD_STATUS));
        $this->assertSame('RuntimeException: Connection reset', $failed->getField(Run::FIELD_ERROR));
        $this->assertSame(['First', 'One'], array_map(fn ($message) => $message->content, $first->runs()->history()));
        $this->assertSame(['Second', 'Two'], array_map(fn ($message) => $message->content, $second->runs()->history()));
        $this->assertSame([], new Helper($this->user)->runs()->history());
        $this->assertSame(3, $first->runs()->countSince(now()->startOfDay()));

        $this->travel(3)->hours();

        $this->assertSame([], $first->runs()->history());
        $this->assertSame(['First', 'One'], array_map(fn ($message) => $message->content, $first->runs()->history(minutes: 300)));
    }
}
