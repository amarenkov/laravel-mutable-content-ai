<?php

namespace Amarenkov\MutableContentAi\Services;

use DateTimeInterface;
use Throwable;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;

use Amarenkov\MutableContentAi\Lovs\RunStatus;
use Amarenkov\MutableContentAi\Models\Run;

/**
 * The run journal of an agent for one user, optionally within one conversation (a chat, a thread, a page):
 * Runs::for($user, $agent, 'telegram:15')->record($prompt, $response) / failed() / history() / countSince().
 * Runs through somebody else's settings are paid by their owner: Runs::for(...)->using(Access::for($user, ...)).
 *
 * The history replays earlier runs with their tool calls and results: an agent that sees only past answers tends to
 * imitate them in words instead of calling the tools.
 */
class Runs
{
    // static
    public static function for(Authenticatable|int $user, Agent|string $agent, ?string $conversation = null): self
    {
        return new self(
            $user instanceof Authenticatable ? (int)$user->getAuthIdentifier() : $user,
            $agent instanceof Agent ? $agent::class : $agent,
            $conversation,
        );
    }

    // protected
    protected ?Access $access = null;

    protected function save(array $fields): Run
    {
        $run = new Run();
        $run->fill([
            Run::FIELD_USER_ID => $this->userId,
            Run::FIELD_AGENT => $this->agent,
            Run::FIELD_CONVERSATION => $this->conversation,
            Run::FIELD_PAYER_ID => $this->access?->payerId() ?? $this->userId,
            Run::FIELD_GRANT_ID => $this->access?->grant?->id,
        ] + array_filter($fields, fn ($value) => $value !== null && $value !== ''));
        $run->withLogContext()->user($this->userId);
        $run->save();

        return $run;
    }

    /**
     * @return array<Message>
     */
    protected function replay(Run $run): array
    {
        $calls = $run->toolCalls();
        $answer = trim((string)$run->getField(Run::FIELD_ANSWER));

        if ($calls === [] && $answer === '') {
            return [];
        }

        $messages = [new UserMessage((string)$run->getField(Run::FIELD_PROMPT))];

        if ($calls !== []) {
            $messages[] = new AssistantMessage('', new Collection(array_map(
                fn (array $call) => new ToolCall($call['id'], $call['name'], $call['arguments']),
                $calls,
            )));
            $messages[] = new ToolResultMessage(new Collection(array_map(
                fn (array $call) => new ToolResult($call['id'], $call['name'], $call['arguments'], $call['result']),
                $calls,
            )));
        }

        if ($answer !== '') {
            $messages[] = new AssistantMessage($answer);
        }

        return $messages;
    }

    // public
    public function __construct(
        public readonly int $userId,
        public readonly string $agent,
        public readonly ?string $conversation = null,
    )
    {
    }

    /**
     * Whose settings the runs use: the payer and the grant are kept in each run.
     */
    public function using(?Access $access): self
    {
        $this->access = $access;

        return $this;
    }

    /**
     * Keep a completed run. The answer may be replaced: what the model said is not always what happened (it says
     * "done" about an action that waits for approval), and the history must not repeat it.
     */
    public function record(string $prompt, AgentResponse $response, ?int $durationMs = null, ?string $answer = null): Run
    {
        $results = $response->toolResults->keyBy(fn (ToolResult $result) => $result->resultId ?? $result->id);

        $calls = $response->toolCalls->map(function (ToolCall $call) use ($results) {
            $id = $call->resultId ?? $call->id;
            $result = $results->get($id);

            return [
                'id' => $id,
                'name' => $call->name,
                'arguments' => $call->arguments,
                'result' => $result instanceof ToolResult ? $result->text() : '',
            ];
        })->values()->all();

        $run = $this->save([
            Run::FIELD_STATUS => RunStatus::COMPLETED,
            Run::FIELD_PROMPT => $prompt,
            Run::FIELD_ANSWER => $answer ?? $response->text,
            Run::FIELD_TOOL_CALLS => $calls ? json_encode($calls, JSON_UNESCAPED_UNICODE) : null,
            Run::FIELD_PROVIDER => $response->meta->provider,
            Run::FIELD_MODEL => $response->meta->model,
            Run::FIELD_INPUT_TOKENS => $response->usage->inputTokens,
            Run::FIELD_OUTPUT_TOKENS => $response->usage->outputTokens,
            Run::FIELD_DURATION_MS => $durationMs,
        ]);

        Approvals::of($this->userId, $this->conversation)->attach($run);

        return $run;
    }

    public function failed(string $prompt, Throwable|string $error, ?int $durationMs = null): Run
    {
        return $this->save([
            Run::FIELD_STATUS => RunStatus::FAILED,
            Run::FIELD_PROMPT => $prompt,
            Run::FIELD_ERROR => $error instanceof Throwable ? $error::class.': '.$error->getMessage() : $error,
            Run::FIELD_DURATION_MS => $durationMs,
        ]);
    }

    /**
     * Recent completed runs of this agent, user and conversation as agent messages, oldest first.
     *
     * @return array<Message>
     */
    public function history(?int $runs = null, ?int $minutes = null): array
    {
        $runs ??= (int)config('mutable-content-ai.history.runs');
        $minutes ??= (int)config('mutable-content-ai.history.minutes');

        $query = Run::query()
            ->ownedBy($this->userId)
            ->whereField(Run::FIELD_AGENT, $this->agent)
            ->whereField(Run::FIELD_STATUS, RunStatus::COMPLETED)
            ->where('created_at', '>=', now()->subMinutes($minutes));

        $this->conversation === null
            ? $query->whereNull(Run::FIELD_CONVERSATION)
            : $query->inConversation($this->conversation);

        return $query->orderByDesc('id')->limit($runs)->get()->reverse()
            ->flatMap(fn (Run $run) => $this->replay($run))
            ->values()
            ->all();
    }

    /**
     * Runs of the user with any agent since the moment in any timezone: for daily limits.
     */
    public function countSince(DateTimeInterface $since): int
    {
        $since = Carbon::instance($since)->setTimezone(config('app.timezone'));

        return Run::query()->ownedBy($this->userId)->where('created_at', '>=', $since)->count();
    }
}
