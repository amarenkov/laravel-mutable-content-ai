# amarenkov/laravel-mutable-content-ai

[![tests](https://github.com/amarenkov/laravel-mutable-content-ai/actions/workflows/tests.yml/badge.svg)](https://github.com/amarenkov/laravel-mutable-content-ai/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/amarenkov/laravel-mutable-content-ai)](https://packagist.org/packages/amarenkov/laravel-mutable-content-ai)
![coverage](https://img.shields.io/badge/coverage-94%25-brightgreen)

> **Very early, expect breaking changes.** The package was extracted from a single application and is used only
> there so far. Models, tables, the config and the API will change between minor versions, and an upgrade may need
> manual steps. Pin an exact version if you try it.

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
- **User settings.** Each user brings an own provider: `UserSettings::of($user)->save([...])` keeps the driver, the
  API URL, the API key (a `secret` field: encrypted, an empty value keeps the stored key, `forgetApiKey()` removes
  it), the model, the daily limit and an on/off switch. `isReady()` - enabled and has a key; `withinDailyLimit()`
  counts today's runs. An agent runs on the user's provider by returning `UserSettings::of($this->user)->provider()`
  from its `provider()` method: a laravel/ai provider built from the settings in the current process.
- **Feedback.** `Feedbacks::of($user)->add($reaction, $message, $run, $context, $source)` keeps a user's mark on an
  agent message - a thumbs down in a chat - with the conversation around it and the run that produced it, to review
  later and correct the prompts. One feedback per source (the application's key of the message), `withdraw($source)`
  removes a new one when the mark is taken back; `comment($feedback, $text)` keeps the user's words on what is wrong;
  `resolve($feedback, $note)`, `reopen()` and `note()` track the review.
- **Shared settings.** `Grants::of($owner)->give($user, 'telegram:15', 20)` lets another user run agents on the
  owner's settings in a conversation (and the nested ones: `telegram:15` covers `telegram:15:7`) with an own daily
  limit; `give($user)` - everywhere, as if the settings were the user's; `give(null, 'telegram:15')` - everybody in the
  conversation. `moveTo()`, `setDailyLimit()`, `revoke()`. `Access::for($user, $conversation)` picks the settings: own
  ready ones first, then a grant within its limit and the owner's; the agent returns `$access->provider()` and the
  journal keeps the payer and the grant (`Runs::for(...)->using($access)`), so the owner's daily limit counts the runs
  of everybody on the owner's key.
- **Costs and balance.** The journal keeps the tokens read from and written to a prompt cache. With the prices of
  `mutable-content-ai.prices` (per model, per million tokens, in the provider's currency:
  `'claude-haiku-5-5' => ['currency' => 'USD', 'input' => 0.1, 'output' => 0.5, 'cache_read' => 0.01, 'cache_write' => 0.125]`)
  `$run->cost()` is the cost of a run, `Spending::of($user)->total($from, $to)` and `daily($from)` sum the runs paid by
  the user by currency and by model. Providers do not tell the balance by the API key, so `Balances::of($user)` estimates it:
  `topUp($money)` and `setActual($money)` (the balance seen at the provider) keep entries, `remaining()` is the latest
  actual balance of each currency plus the top-ups after it minus the costs after it.
- Models with mutable fields (`Memory`, `Run`, `Approval`, `Prompt`, `Settings`, `Feedback`, `Grant`, `BalanceEntry`) in the `ai` schema: every change lands in the core change log with
  its author, an application may add its own fields.
- **No user interface.** Screens, bots and access rules belong to the application.

## Requirements

- PHP 8.4
- Laravel 13
- PostgreSQL or MariaDB 10.7+ (not SQLite, not MySQL), as the core
- `amarenkov/laravel-mutable-content` ^0.7.9, `laravel/ai` ^1.2

## Installation

```bash
composer require amarenkov/laravel-mutable-content-ai
php artisan vendor:publish --tag=mutable-content-ai-migrations
php artisan migrate
```

Then seed the core LOVs and fields as usual (`LovsSeeder`, `FieldsSeeder`). The config
(`--tag=mutable-content-ai-config`) sets the user model, the default driver of user settings, the memory limits, the model prices and the history window.

## License

MIT
