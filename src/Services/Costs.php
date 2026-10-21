<?php

namespace Amarenkov\MutableContentAi\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

use Amarenkov\MutableContent\ValueObjects\Money;

use Amarenkov\MutableContentAi\Models\Run;

/**
 * Costs of runs by the prices of mutable-content-ai.prices: per model, per million tokens, in the provider's currency.
 * Cached input is paid by the cache prices when a model has them, otherwise as the usual input.
 */
class Costs
{
    // static
    /**
     * @return array{currency: string, input: int|float|string, output: int|float|string, cache_read?: int|float|string, cache_write?: int|float|string}|null
     */
    public static function price(?string $model): ?array
    {
        if ($model === null || $model === '') {
            return null;
        }

        $price = (config('mutable-content-ai.prices') ?? [])[$model] ?? null;

        return is_array($price) && isset($price['currency'], $price['input'], $price['output']) ? $price : null;
    }

    public static function currency(?string $model): ?string
    {
        return static::price($model)['currency'] ?? null;
    }

    /**
     * The cost of the tokens, where the input includes the tokens read from and written to the cache.
     */
    public static function of(?string $model, int $input, int $output, int $cacheRead = 0, int $cacheWrite = 0): ?Money
    {
        $price = static::price($model);

        if ($price === null) {
            return null;
        }

        $sum = static::tokens(max(0, $input - $cacheRead - $cacheWrite), $price['input'])
            ->plus(static::tokens($cacheRead, $price['cache_read'] ?? $price['input']))
            ->plus(static::tokens($cacheWrite, $price['cache_write'] ?? $price['input']))
            ->plus(static::tokens($output, $price['output']));

        return Money::of($sum->dividedBy(1_000_000, Money::MAX_DECIMALS, RoundingMode::HalfUp), $price['currency']);
    }

    public static function run(Run $run): ?Money
    {
        return static::of(
            (string)$run->getField(Run::FIELD_MODEL),
            (int)$run->getField(Run::FIELD_INPUT_TOKENS),
            (int)$run->getField(Run::FIELD_OUTPUT_TOKENS),
            (int)$run->getField(Run::FIELD_CACHE_READ_TOKENS),
            (int)$run->getField(Run::FIELD_CACHE_WRITE_TOKENS),
        );
    }

    // protected
    protected static function tokens(int $tokens, int|float|string $price): BigDecimal
    {
        return BigDecimal::of($tokens)->multipliedBy(Money::toDecimalString($price));
    }
}
