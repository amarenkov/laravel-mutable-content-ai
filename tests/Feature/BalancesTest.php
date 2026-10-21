<?php

namespace Amarenkov\MutableContentAi\Tests\Feature;

use DomainException;

use Illuminate\Support\Carbon;

use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;

use Amarenkov\MutableContent\ValueObjects\Money;

use Amarenkov\MutableContentAi\Models\Run;
use Amarenkov\MutableContentAi\Services\Balances;
use Amarenkov\MutableContentAi\Services\Costs;
use Amarenkov\MutableContentAi\Services\Runs;
use Amarenkov\MutableContentAi\Services\Spending;

use Amarenkov\MutableContentAi\Tests\Fixtures\Agents\Helper;
use Amarenkov\MutableContentAi\Tests\Fixtures\Models\User;
use Amarenkov\MutableContentAi\Tests\TestCase;

class BalancesTest extends TestCase
{
    // protected
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('mutable-content-ai.prices', [
            'model-1.5' => ['currency' => 'USD', 'input' => 2, 'output' => 10, 'cache_read' => 0.2, 'cache_write' => 2.5],
            'plain' => ['currency' => 'USD', 'input' => 1, 'output' => 1],
        ]);
    }

    protected function record(string $model, int $input, int $output, ?int $cacheRead = null, ?int $cacheWrite = null): Run
    {
        return Runs::for($this->user, Helper::class)->record('Hi', new AgentResponse(
            'invocation',
            'Hello',
            new TextUsage($input, $output, $cacheRead, $cacheWrite),
            new Meta('provider', $model),
        ));
    }

    // public
    public function test_a_run_keeps_cache_tokens_and_costs_by_the_cache_prices(): void
    {
        $run = $this->record('model-1.5', 1_000_000, 100_000, 800_000, 100_000);

        $this->assertSame(800_000, $run->getField(Run::FIELD_CACHE_READ_TOKENS));
        $this->assertSame(100_000, $run->getField(Run::FIELD_CACHE_WRITE_TOKENS));
        $this->assertEquals(Money::of('1.61', 'USD'), $run->cost());
        $this->assertEquals(Money::of('0.0003', 'USD'), Costs::of('plain', 200, 100, 150));
        $this->assertNull(Costs::of('unknown', 200, 100));
        $this->assertNull($this->record('unknown', 10, 10)->cost());
    }

    public function test_spending_is_summed_by_day_and_currency(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00', 'UTC'));
        $this->record('plain', 1_000_000, 0);
        $this->record('unknown', 10, 10);

        $this->travelTo(Carbon::parse('2026-10-22 09:00', 'UTC'));
        $this->record('plain', 500_000, 500_000, 100_000);
        Runs::for($this->user, Helper::class)->failed('Broken', 'Connection reset');

        $days = Spending::of($this->user)->daily(Carbon::parse('2026-10-20'), timezone: 'UTC');

        $this->assertSame(['2026-10-20', '2026-10-21', '2026-10-22'], array_keys($days));
        $this->assertSame(2, $days['2026-10-20']['runs']);
        $this->assertSame(1, $days['2026-10-20']['unpriced']);
        $this->assertEquals(['USD' => Money::of(1, 'USD')], $days['2026-10-20']['costs']);
        $this->assertSame(0, $days['2026-10-21']['runs']);
        $this->assertSame([], $days['2026-10-21']['costs']);
        $this->assertSame(1, $days['2026-10-22']['failed']);
        $this->assertSame(100_000, $days['2026-10-22']['cache_read']);
        $this->assertEquals(['USD' => Money::of(1, 'USD')], $days['2026-10-22']['costs']);
        $this->assertEquals(['USD' => Money::of(2, 'USD')], Spending::of($this->user)->total()['costs']);
        $this->assertSame(['plain', 'unknown'], array_keys($days['2026-10-20']['models']));
        $this->assertSame(1, $days['2026-10-20']['models']['unknown']['unpriced']);
        $this->assertArrayNotHasKey('models', $days['2026-10-20']['models']['plain']);
        $this->assertSame(['', 'plain'], array_keys($days['2026-10-22']['models']));
        $this->assertSame(1, $days['2026-10-22']['models']['']['failed']);
        $this->assertSame([], Spending::of(User::query()->create(['name' => 'Other', 'email' => 'o@example.com', 'password' => 'x']))->total()['costs']);
    }

    public function test_the_balance_starts_from_the_latest_actual_one(): void
    {
        $balances = Balances::of($this->user);

        $this->travelTo(Carbon::parse('2026-10-20 12:00', 'UTC'));
        $balances->topUp(Money::of(10, 'USD'), note: 'Card');
        $this->record('plain', 1_000_000, 0);

        $remaining = $balances->remaining()['USD'];
        $this->assertEquals(Money::of(9, 'USD'), $remaining['balance']);
        $this->assertNull($remaining['actual']);

        $this->travelTo(Carbon::parse('2026-10-21 12:00', 'UTC'));
        $actual = $balances->setActual(Money::of('8.5', 'USD'));
        $this->record('plain', 500_000, 0);

        $this->travelTo(Carbon::parse('2026-10-22 12:00', 'UTC'));
        $balances->topUp(Money::of(5, 'USD'));

        $remaining = $balances->remaining()['USD'];
        $this->assertEquals(Money::of(13, 'USD'), $remaining['balance']);
        $this->assertEquals(Money::of('8.5', 'USD'), $remaining['actual']);
        $this->assertEquals(Money::of(5, 'USD'), $remaining['top_ups']);
        $this->assertEquals(Money::of('0.5', 'USD'), $remaining['spent']);
        $this->assertSame('2026-10-21 12:00:00', $remaining['since']->utc()->format('Y-m-d H:i:s'));
        $this->assertCount(3, $balances->entries());
        $this->assertTrue($balances->entries()->first()->isTopUp());

        $balances->delete($actual);

        $this->assertEquals(Money::of('13.5', 'USD'), $balances->remaining()['USD']['balance']);
    }

    public function test_top_ups_are_positive_and_entries_are_own(): void
    {
        $entry = Balances::of($this->user)->topUp(Money::of(1, 'USD'));
        $other = User::query()->create(['name' => 'Other', 'email' => 'o@example.com', 'password' => 'x']);

        try {
            Balances::of($this->user)->topUp(Money::of(0, 'USD'));
            $this->fail('A zero top-up is kept.');
        } catch (DomainException $exception) {
            $this->assertSame('A top-up is an amount above zero.', $exception->getMessage());
        }

        $this->expectException(DomainException::class);

        Balances::of($other)->delete($entry->id);
    }
}
