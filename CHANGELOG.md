# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Feedback on agent messages (`Models\Feedback`, table `ai.feedback` in the new migration `extend_ai_tables_with_feedback`, the `ai_feedback_status` LOV): the reaction, the marked message, the conversation around it and the run. `Services\Feedbacks`: `add()` (one feedback per source, a new reaction replaces the old one), `withdraw()`, `find()`, `resolve()`, `reopen()`, `note()`, `delete()`.

## [0.2.0] - 2026-10-19

### Added

- User settings (`Models\Settings`, table `ai.settings` in the new migration `extend_ai_tables_with_settings`): the driver, the API URL, the API key as a `secret` field, the model, the daily limit and an on/off switch of a user. `Services\UserSettings`: `save()` (an empty key keeps the stored one), `forgetApiKey()`, `isReady()`, `model()`, `dailyLimit()`, `usedToday()`, `withinDailyLimit()`, `providerConfig()` and `provider()` - the name of a laravel/ai provider built from the settings, for the `provider()` method of an agent. Config `settings.driver` (`openai-compatible`).

### Changed

- Requires `amarenkov/laravel-mutable-content` ^0.7.9 (the `secret` field type).

## [0.1.0] - 2026-10-19

First release, very early: the API, the tables and the config will change.

### Added

- `Memory` (a user's fact, preference or alias, the `ai_memory_kind` LOV) and `Memories::of($user)` service: `remember()`, `forget()`, `all()`, `prompt()`, limits in the config.
- `Run` (an agent run: prompt, tool calls with results, answer, provider, model, tokens, duration, the `ai_run_status` LOV) and `Runs::for($user, $agent, $conversation)` service: `record()`, `failed()`, `history()` as `laravel/ai` messages with the tool calls, `countSince()`; `record()` may replace the answer the model gave.
- `Remember` and `Forget` agent tools.
- `Approval` (the `ai_approval_status` LOV), `Approvals::of($user, $conversation)` service (`request()`, `find()`, `approve()` performing the action by its tool, `reject()`, `attach()` to a run), the `NeedsApproval` contract and the `AsksForApproval` trait for tools; `Forget` asks for approval when given approvals.
- `Prompt` and `Prompts` service: prompts registered in the code with a default text and placeholders, edited texts, versions from the core change log.
- `FieldSchema`: tool parameters from field definitions (`allow()` limits the LOV items offered) and field values from tool arguments; `FindRecords` search tool; `TextSearch` loose word matching.

[Unreleased]: https://github.com/amarenkov/laravel-mutable-content-ai/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/amarenkov/laravel-mutable-content-ai/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/amarenkov/laravel-mutable-content-ai/releases/tag/v0.1.0
