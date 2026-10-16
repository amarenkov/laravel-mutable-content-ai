# amarenkov/laravel-mutable-content-ai

> **Work in progress.** Not released yet: the API may change.

AI agents for [`amarenkov/laravel-mutable-content`](https://github.com/amarenkov/laravel-mutable-content) apps,
built on [`laravel/ai`](https://github.com/laravel/ai): a long-term memory of each user and a journal of agent runs
that gives an agent its conversation history together with the tools it called.

## At a glance

```php
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

use Amarenkov\MutableContentAi\Services\Memories;
use Amarenkov\MutableContentAi\Services\Runs;
use Amarenkov\MutableContentAi\Tools\Forget;
use Amarenkov\MutableContentAi\Tools\Remember;

class Helper implements Agent, Conversational, HasTools
{
    use Promptable;

    public function __construct(public User $user, public string $conversation)
    {
    }

    public function runs(): Runs
    {
        return Runs::for($this->user, $this, $this->conversation);
    }

    public function instructions(): string
    {
        return 'You are a helper. '.Memories::of($this->user)->prompt();
    }

    public function messages(): iterable
    {
        return $this->runs()->history();
    }

    public function tools(): iterable
    {
        return [new Remember($this->user), new Forget($this->user)];
    }
}

$agent = new Helper($user, 'chat:15');
$response = $agent->prompt('Remember: Pete is @petrov');
$agent->runs()->record('Remember: Pete is @petrov', $response);
```

## Features

- **Memory.** `Memories::of($user)->remember($text, $kind)`, `forget($id)`, `all()` and `prompt()` - a numbered
  block for the agent instructions. Kinds of the `ai_memory_kind` LOV: `fact`, `preference`, `alias`. The same text
  is kept once; the number and the length of memories are limited in the config. `Remember` and `Forget` are ready
  agent tools.
- **Run journal.** `Runs::for($user, $agent, $conversation)->record($prompt, $response)` keeps the prompt, the tool
  calls with their results, the answer, the provider, the model, the tokens and the duration; `failed()` keeps an
  error. `history()` replays recent completed runs of the conversation as agent messages **with the tool calls**:
  an agent that sees only its past answers tends to imitate them in words instead of calling the tools.
  `countSince()` counts runs of a user, for daily limits.
- **Approvals.** A tool implementing `NeedsApproval` with the `AsksForApproval` trait does not act when its
  `approvalSummary()` describes the call: it keeps an `Approval` (tool, arguments, what it will do) and tells the
  model the action waits. The application shows it to the user; `Approvals::approve($approval, $tool)` performs the
  action with the saved arguments without asking the model again, `reject()` declines it. The outcome is added to the
  tool result in the run journal. `Forget` asks for approval when given an `Approvals` instance.
- **Prompts.** `Prompts::register($code, $label, $default, $placeholders)` in the code,
  `Prompts::render($code, $values)` in an agent, `Prompts::save($code, $text, $user)` from a screen of the application.
  An empty or default text returns the prompt to the default; `history()` gives the versions from the core change log.
- **Tool schemas from fields.** `FieldSchema::for(Model::class)->only([...])->describe([...])->build($schema)` turns
  field definitions into tool parameters: LOV items become enums with their labels, object references record numbers,
  dates strings in a stated format, required flags come from the field usage; `values($arguments)` turns the arguments
  back into field values. `FindRecords` is a ready search tool over a query the application gives, by words of a title
  field and by field filters.
- Models with mutable fields (`Memory`, `Run`, `Approval`, `Prompt`) in the `ai` schema: every change lands in the core change log with
  its author, an application may add its own fields.
- **No user interface.** Screens, bots and access rules belong to the application.

## Requirements

- PHP 8.4
- Laravel 13
- PostgreSQL or MariaDB 10.7+ (not SQLite, not MySQL), as the core
- `amarenkov/laravel-mutable-content` ^0.7.6, `laravel/ai` ^1.2

## Installation

```bash
composer require amarenkov/laravel-mutable-content-ai
php artisan vendor:publish --tag=mutable-content-ai-migrations
php artisan migrate
```

Then seed the core LOVs and fields as usual (`LovsSeeder`, `FieldsSeeder`). The config
(`--tag=mutable-content-ai-config`) sets the user model, the memory limits and the history window.

## License

MIT
