<?php

namespace Amarenkov\MutableContentAi\Services;

use InvalidArgumentException;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

use Amarenkov\MutableContent\Helpers\LogHelper;
use Amarenkov\MutableContent\Models\Log\Entry;

use Amarenkov\MutableContentAi\Models\Prompt;
use Amarenkov\MutableContentAi\Support\PromptDefinition;

/**
 * Prompts of the application: registered in the code with a default text, edited in the database, versioned by the
 * core change log. Prompts::register(...), Prompts::render($code, $values), Prompts::save($code, $text, $user).
 */
class Prompts
{
    // static
    /** @var array<string, PromptDefinition> */
    protected static array $definitions = [];

    /**
     * @param array<string, string> $placeholders name => what it is replaced with; used as {name} in the text
     */
    public static function register(string $code, string $label, string $default, array $placeholders = []): void
    {
        static::$definitions[$code] = new PromptDefinition($code, $label, $default, $placeholders);
    }

    /**
     * @return array<string, PromptDefinition>
     */
    public static function definitions(): array
    {
        return static::$definitions;
    }

    public static function definition(string $code): PromptDefinition
    {
        return static::$definitions[$code] ?? throw new InvalidArgumentException('Unknown prompt '.$code);
    }

    public static function record(string $code): ?Prompt
    {
        return Prompt::query()->whereField(Prompt::FIELD_CODE, $code)->first();
    }

    /**
     * The edited text, or the default one when it was not edited.
     */
    public static function text(string $code): string
    {
        return static::record($code)?->text() ?? static::definition($code)->default;
    }

    public static function isEdited(string $code): bool
    {
        return static::record($code)?->text() !== null;
    }

    /**
     * The text with {placeholders} replaced; unknown ones are left as they are.
     *
     * @param array<string, string> $values
     */
    public static function render(string $code, array $values = []): string
    {
        return strtr(static::text($code), array_combine(
            array_map(fn (string $name) => '{'.$name.'}', array_keys($values)),
            array_map('strval', array_values($values)),
        ));
    }

    /**
     * Save the text; an empty one or the default returns the prompt to the default.
     */
    public static function save(string $code, ?string $text, Authenticatable|int|null $user = null): void
    {
        $definition = static::definition($code);
        $text = $text === null || trim($text) === '' || $text === $definition->default ? null : $text;
        $prompt = static::record($code);

        if (!$prompt && $text === null) {
            return;
        }

        $prompt ??= new Prompt();
        $prompt->fill([Prompt::FIELD_CODE => $code, Prompt::FIELD_TEXT => $text]);

        if (!$prompt->isDirty()) {
            return;
        }

        $prompt->withLogContext()->user($user instanceof Authenticatable ? (int)$user->getAuthIdentifier() : $user);
        $prompt->save();
    }

    /**
     * Versions of the text, newest first: when, by whom (user id) and the text, null for the default.
     *
     * @return Collection<int, array{date: \Illuminate\Support\Carbon, user_id: ?int, text: ?string}>
     */
    public static function history(string $code): Collection
    {
        $prompt = static::record($code);

        if (!$prompt) {
            return new Collection();
        }

        return Entry::fromQuery(LogHelper::query(Prompt::class)->where(LogHelper::COLUMN_ENTITY_ID, $prompt->id))
            ->orderBy('id')
            ->get()
            ->filter(fn ($entry) => $entry instanceof Entry && array_key_exists(Prompt::FIELD_TEXT, $entry->changes()->items))
            ->map(function (Entry $entry) {
                $text = $entry->changes()->items[Prompt::FIELD_TEXT][1];

                return [
                    'date' => $entry->date,
                    'user_id' => $entry->user_id,
                    'text' => is_string($text) && trim($text) !== '' ? $text : null,
                ];
            })
            ->values();
    }
}
