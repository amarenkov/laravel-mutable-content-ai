<?php

namespace Amarenkov\MutableContentAi\Support;

/**
 * Loose search by words for agent tools: a word matches without its ending, records with more matched words win.
 * Works on any database because it scores in PHP.
 */
class TextSearch
{
    // static
    /**
     * Words of a query: letters and digits, longer than two characters, without the stop words.
     *
     * @param array<string> $stopWords
     * @return array<string>
     */
    public static function words(string $query, array $stopWords = []): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($words, fn (string $word) => mb_strlen($word) > 2 && !in_array($word, $stopWords, true)));
    }

    /**
     * How many of the words the text has; a word longer than four letters is compared without the last two.
     *
     * @param array<string> $words
     */
    public static function score(string $text, array $words): int
    {
        $text = mb_strtolower($text);
        $score = 0;

        foreach ($words as $word) {
            $stem = mb_strlen($word) > 4 ? mb_substr($word, 0, max(4, mb_strlen($word) - 2)) : $word;
            $score += (int)str_contains($text, $stem);
        }

        return $score;
    }

    /**
     * The items with the best score, in their order; all of them when there are no words.
     *
     * @template T
     * @param iterable<T> $items
     * @param callable(T): string $text
     * @param array<string> $words
     * @return array<T>
     */
    public static function best(iterable $items, callable $text, array $words): array
    {
        $items = is_array($items) ? array_values($items) : iterator_to_array($items, false);

        if ($words === []) {
            return $items;
        }

        $scores = array_map(fn ($item) => static::score($text($item), $words), $items);
        $best = $scores ? max($scores) : 0;

        return $best === 0 ? [] : array_values(array_filter($items, fn ($item, $index) => $scores[$index] === $best, ARRAY_FILTER_USE_BOTH));
    }
}
