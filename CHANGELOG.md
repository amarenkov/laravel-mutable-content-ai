# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Memory` (a user's fact, preference or alias, the `ai_memory_kind` LOV) and `Memories::of($user)` service: `remember()`, `forget()`, `all()`, `prompt()`, limits in the config.
- `Run` (an agent run: prompt, tool calls with results, answer, provider, model, tokens, duration, the `ai_run_status` LOV) and `Runs::for($user, $agent, $conversation)` service: `record()`, `failed()`, `history()` as `laravel/ai` messages with the tool calls, `countSince()`.
- `Remember` and `Forget` agent tools.

[Unreleased]: https://github.com/amarenkov/laravel-mutable-content-ai/commits/main
