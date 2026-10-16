<?php

namespace Amarenkov\MutableContentAi\Tests\Feature;

use DomainException;

use Amarenkov\MutableContentAi\Lovs\MemoryKind;
use Amarenkov\MutableContentAi\Models\Memory;
use Amarenkov\MutableContentAi\Services\Memories;

use Amarenkov\MutableContentAi\Tests\Fixtures\Models\User;
use Amarenkov\MutableContentAi\Tests\TestCase;

class MemoriesTest extends TestCase
{
    // public
    public function test_remember_and_forget(): void
    {
        $memories = Memories::of($this->user);

        $alias = $memories->remember('  Pete is   @petrov ', MemoryKind::ALIAS);
        $fact = $memories->remember('Works from 10 to 19');

        $this->assertSame('Pete is @petrov', $alias->text());
        $this->assertSame(MemoryKind::ALIAS, $alias->kind());
        $this->assertSame(MemoryKind::FACT, $fact->kind());
        $this->assertSame($alias->id, $memories->remember('pete is @PETROV')->id);
        $this->assertSame([$alias->id, $fact->id], $memories->all()->pluck('id')->all());

        $this->assertSame(
            "What you remember about the user (number, kind, text):\n#{$alias->id} (alias) Pete is @petrov\n#{$fact->id} (fact) Works from 10 to 19",
            $memories->prompt(),
        );

        $this->assertTrue($memories->forget($alias->id));
        $this->assertFalse($memories->forget($alias->id));
        $this->assertSame([$fact->id], $memories->all()->pluck('id')->all());
        $this->assertNotNull(Memory::withTrashed()->find($alias->id)?->deleted_at);
    }

    public function test_memories_are_per_user(): void
    {
        $other = User::query()->create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'secret']);
        $mine = Memories::of($this->user)->remember('Mine');

        $this->assertFalse(Memories::of($other)->forget($mine->id));
        $this->assertSame('', Memories::of($other)->prompt());
        $this->assertCount(1, Memories::of($this->user)->all());
    }

    public function test_limits(): void
    {
        config(['mutable-content-ai.memory.max_items' => 1, 'mutable-content-ai.memory.max_length' => 10]);
        $memories = Memories::of($this->user);

        $memories->remember('Short one');

        foreach (['  ' => 'empty', 'Far too long text' => 'Too long', 'Second' => 'full'] as $text => $message) {
            try {
                $memories->remember($text);
                $this->fail('Remembered: '.$text);
            } catch (DomainException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
    }
}
