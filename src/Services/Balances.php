<?php

namespace Amarenkov\MutableContentAi\Services;

use DateTimeInterface;
use DomainException;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

use Amarenkov\MutableContent\ValueObjects\Money;

use Amarenkov\MutableContentAi\Lovs\BalanceEntryKind;
use Amarenkov\MutableContentAi\Models\BalanceEntry;
use Amarenkov\MutableContentAi\Models\Run;

/**
 * Money on the user's accounts at the providers, one per currency: Balances::of($user)->topUp($money) /
 * setActual($money) / remaining(). Providers do not tell the balance by the API key, so it is estimated:
 * the latest actual balance, plus the top-ups after it, minus the costs of the runs paid by the user after it.
 */
class Balances
{
    // static
    public static function of(Authenticatable|int $user): self
    {
        return new self($user instanceof Authenticatable ? (int)$user->getAuthIdentifier() : $user);
    }

    // protected
    protected function add(string $kind, Money $amount, ?DateTimeInterface $at, ?string $note): BalanceEntry
    {
        $entry = new BalanceEntry();
        $entry->fill(array_filter([
            BalanceEntry::FIELD_USER_ID => $this->userId,
            BalanceEntry::FIELD_KIND => $kind,
            BalanceEntry::FIELD_AMOUNT => $amount,
            BalanceEntry::FIELD_AT => $at ?? now(),
            BalanceEntry::FIELD_NOTE => $note === null ? null : trim($note),
        ], fn ($value) => $value !== null && $value !== ''));
        $entry->withLogContext()->user($this->userId);
        $entry->save();

        return $entry;
    }

    // public
    public function __construct(
        public readonly int $userId,
    )
    {
    }

    /**
     * Money put on the account: an amount above zero.
     */
    public function topUp(Money $amount, ?DateTimeInterface $at = null, ?string $note = null): BalanceEntry
    {
        if ($amount->number()->isLessThanOrEqualTo(0)) {
            throw new DomainException(__('mutable-content-ai::ai.top_up_not_positive'));
        }

        return $this->add(BalanceEntryKind::TOP_UP, $amount, $at, $note);
    }

    /**
     * The balance seen at the provider at the moment: the estimate of the currency starts from it.
     */
    public function setActual(Money $amount, ?DateTimeInterface $at = null, ?string $note = null): BalanceEntry
    {
        return $this->add(BalanceEntryKind::BALANCE, $amount, $at, $note);
    }

    public function find(int $id): BalanceEntry
    {
        $entry = BalanceEntry::query()->ownedBy($this->userId)->find($id);

        if (!$entry instanceof BalanceEntry) {
            throw new DomainException(__('mutable-content-ai::ai.balance_entry_not_found'));
        }

        return $entry;
    }

    public function delete(BalanceEntry|int $entry): void
    {
        $entry = $this->find($entry instanceof BalanceEntry ? $entry->id : $entry);
        $entry->withLogContext()->user($this->userId);
        $entry->delete();
    }

    /**
     * Entries of the user, the latest first.
     *
     * @return Collection<int, BalanceEntry>
     */
    public function entries(): Collection
    {
        return BalanceEntry::query()->ownedBy($this->userId)->get()
            ->sortBy([fn (BalanceEntry $a, BalanceEntry $b) => $b->at() <=> $a->at(), fn (BalanceEntry $a, BalanceEntry $b) => $b->id <=> $a->id])
            ->values();
    }

    /**
     * The estimated balance of every currency with entries: the actual balance it starts from (null - from zero
     * before the first top-up), the top-ups and the costs after it.
     *
     * @return array<string, array{balance: Money, actual: ?Money, since: ?Carbon, top_ups: Money, spent: Money}>
     */
    public function remaining(): array
    {
        $result = [];

        foreach ($this->entries()->groupBy(fn (BalanceEntry $entry) => $entry->amount()->currency) as $currency => $entries) {
            $actual = $entries->first(fn (BalanceEntry $entry) => !$entry->isTopUp());
            $since = $actual?->at();

            $topUps = $entries
                ->filter(fn (BalanceEntry $entry) => $entry->isTopUp() && ($since === null || $entry->at() > $since))
                ->reduce(fn (Money $sum, BalanceEntry $entry) => $sum->add($entry->amount()), Money::zero($currency));

            $spent = Spending::of($this->userId)->runs($since)
                ->map(fn (Run $run) => $run->cost())
                ->filter(fn (?Money $cost) => $cost?->currency === $currency)
                ->reduce(fn (Money $sum, Money $cost) => $sum->add($cost), Money::zero($currency));

            $result[$currency] = [
                'balance' => ($actual?->amount() ?? Money::zero($currency))->add($topUps)->subtract($spent),
                'actual' => $actual?->amount(),
                'since' => $since,
                'top_ups' => $topUps,
                'spent' => $spent,
            ];
        }

        ksort($result);

        return $result;
    }
}
