<?php

namespace Amarenkov\MutableContentAi\Services;

use DomainException;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

use Amarenkov\MutableContentAi\Lovs\MemoryKind;
use Amarenkov\MutableContentAi\Models\Memory;

/**
 * Long-term memory of one user: Memories::of($user)->remember($text) / forget($id) / prompt().
 * The user is also the change log author: memories are written on the user's word.
 */
class Memories
{
    // static
    public static function of(Authenticatable|int $user): self
    {
        return new self($user instanceof Authenticatable ? (int)$user->getAuthIdentifier() : $user);
    }

    // protected
    protected function normalize(string $text): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    // public
    public function __construct(
        public readonly int $userId,
    )
    {
    }

    /**
     * @return Collection<int, Memory>
     */
    public function all(): Collection
    {
        return Memory::query()->ownedBy($this->userId)->orderBy('id')->get();
    }

    /**
     * Remember a text; the same text is remembered once. Throws DomainException when it is empty, too long or the
     * memory is full.
     */
    public function remember(string $text, string $kind = MemoryKind::FACT): Memory
    {
        $text = $this->normalize($text);

        if ($text === '') {
            throw new DomainException(__('mutable-content-ai::ai.memory_empty'));
        }

        $maxLength = (int)config('mutable-content-ai.memory.max_length');

        if (mb_strlen($text) > $maxLength) {
            throw new DomainException(__('mutable-content-ai::ai.memory_too_long', ['max' => $maxLength]));
        }

        $memories = $this->all();
        $same = $memories->first(fn (Memory $memory) => mb_strtolower($memory->text()) === mb_strtolower($text));

        if ($same) {
            return $same;
        }

        $maxItems = (int)config('mutable-content-ai.memory.max_items');

        if ($memories->count() >= $maxItems) {
            throw new DomainException(__('mutable-content-ai::ai.memory_full', ['max' => $maxItems]));
        }

        $memory = new Memory();
        $memory->fill([
            Memory::FIELD_USER_ID => $this->userId,
            Memory::FIELD_KIND => $kind,
            Memory::FIELD_TEXT => $text,
        ]);
        $memory->withLogContext()->user($this->userId);
        $memory->save();

        return $memory;
    }

    /**
     * Forget a memory of this user; false when there is no such memory.
     */
    public function forget(int $id): bool
    {
        $memory = Memory::query()->ownedBy($this->userId)->find($id);

        if (!$memory) {
            return false;
        }

        $memory->withLogContext()->user($this->userId);
        $memory->delete();

        return true;
    }

    /**
     * The memories as a block of agent instructions, numbered so the agent can forget one; empty when there are none.
     */
    public function prompt(): string
    {
        $memories = $this->all();

        if ($memories->isEmpty()) {
            return '';
        }

        return __('mutable-content-ai::ai.memory_prompt')."\n".$memories
            ->map(fn (Memory $memory) => '#'.$memory->id.' ('.$memory->kind().') '.$memory->text())
            ->implode("\n");
    }
}
