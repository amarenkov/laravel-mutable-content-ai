<?php

namespace Amarenkov\MutableContentAi\Services;

use DateTimeInterface;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

use Amarenkov\MutableContent\Models\ModelWithFields;
use Amarenkov\MutableContent\ValueObjects\Money;

use Amarenkov\MutableContentAi\Models\Run;

/**
 * What the runs on the user's settings cost: own runs and the runs through the user's grants.
 * Spending::of($user)->total($from, $to) / daily($from, $to).
 */
class Spending
{
    // static
    public static function of(Authenticatable|int $user): self
    {
        return new self($user instanceof Authenticatable ? (int)$user->getAuthIdentifier() : $user);
    }

    /**
     * Totals of the runs: costs by currency, runs of models without a price are counted in unpriced. With the models,
     * the same totals by model ('' - failed runs without a model), in the order of names.
     *
     * @param iterable<Run> $runs
     * @return array{runs: int, failed: int, input: int, output: int, cache_read: int, cache_write: int, costs: array<string, Money>, unpriced: int, models?: array<string, array>}
     */
    public static function summarize(iterable $runs, bool $byModel = true): array
    {
        $result = ['runs' => 0, 'failed' => 0, 'input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0, 'costs' => [], 'unpriced' => 0];
        $models = [];

        foreach ($runs as $run) {
            $models[(string)$run->getField(Run::FIELD_MODEL)][] = $run;

            $result['runs']++;
            $result['failed'] += $run->completed() ? 0 : 1;
            $result['input'] += (int)$run->getField(Run::FIELD_INPUT_TOKENS);
            $result['output'] += (int)$run->getField(Run::FIELD_OUTPUT_TOKENS);
            $result['cache_read'] += (int)$run->getField(Run::FIELD_CACHE_READ_TOKENS);
            $result['cache_write'] += (int)$run->getField(Run::FIELD_CACHE_WRITE_TOKENS);

            $cost = $run->cost();

            if ($cost === null) {
                $result['unpriced'] += $run->completed() ? 1 : 0;
            } else {
                $result['costs'][$cost->currency] = ($result['costs'][$cost->currency] ?? Money::zero($cost->currency))->add($cost);
            }
        }

        ksort($result['costs']);

        if ($byModel) {
            ksort($models);
            $result['models'] = array_map(fn (array $runs) => static::summarize($runs, false), $models);
        }

        return $result;
    }

    // public
    public function __construct(
        public readonly int $userId,
    )
    {
    }

    /**
     * Runs paid by the user from the moment (inclusive) to the moment (exclusive), in any timezone.
     *
     * @return Collection<int, Run>
     */
    public function runs(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): Collection
    {
        return Run::query()
            ->paidBy($this->userId)
            ->when($from, fn ($query) => $query->where('created_at', '>=', Carbon::instance($from)->setTimezone(config('app.timezone'))))
            ->when($to, fn ($query) => $query->where('created_at', '<', Carbon::instance($to)->setTimezone(config('app.timezone'))))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{runs: int, failed: int, input: int, output: int, cache_read: int, cache_write: int, costs: array<string, Money>, unpriced: int, models?: array<string, array>}
     */
    public function total(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): array
    {
        return static::summarize($this->runs($from, $to));
    }

    /**
     * Totals by day in the timezone (the display one by default), every day of the period, the earliest first.
     *
     * @return array<string, array{runs: int, failed: int, input: int, output: int, cache_read: int, cache_write: int, costs: array<string, Money>, unpriced: int, models?: array<string, array>}>
     */
    public function daily(DateTimeInterface $from, ?DateTimeInterface $to = null, ?string $timezone = null): array
    {
        $timezone ??= ModelWithFields::displayTimezone();
        $from = Carbon::instance($from)->setTimezone($timezone)->startOfDay();
        $byDay = $this->runs($from, $to)->groupBy(fn (Run $run) => $run->created_at->copy()->setTimezone($timezone)->toDateString());

        $result = [];

        $end = Carbon::instance($to ?? now())->setTimezone($timezone);

        for ($day = $from->copy(); $to === null ? $day <= $end : $day < $end; $day->addDay()) {
            $result[$day->toDateString()] = static::summarize($byDay->get($day->toDateString(), []));
        }

        return $result;
    }
}
