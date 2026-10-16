<?php

namespace Amarenkov\MutableContentAi\Support;

/**
 * A prompt known to the application: its code, a label for people, the default text and the placeholders it may use.
 */
final readonly class PromptDefinition
{
    // public
    /**
     * @param array<string, string> $placeholders name => what it is replaced with
     */
    public function __construct(
        public string $code,
        public string $label,
        public string $default,
        public array $placeholders = [],
    )
    {
    }
}
