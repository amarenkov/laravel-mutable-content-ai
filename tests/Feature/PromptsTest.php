<?php

namespace Amarenkov\MutableContentAi\Tests\Feature;

use InvalidArgumentException;

use Amarenkov\MutableContentAi\Services\Prompts;

use Amarenkov\MutableContentAi\Tests\TestCase;

class PromptsTest extends TestCase
{
    // protected
    protected function setUp(): void
    {
        parent::setUp();

        Prompts::register('helper', 'Helper', 'You help {name}. Today is {date}.', ['name' => 'User name', 'date' => 'Today']);
    }

    // public
    public function test_default_edit_and_render(): void
    {
        $this->assertSame('You help Ann. Today is {date}.', Prompts::render('helper', ['name' => 'Ann']));
        $this->assertFalse(Prompts::isEdited('helper'));
        $this->assertSame(['name' => 'User name', 'date' => 'Today'], Prompts::definition('helper')->placeholders);

        Prompts::save('helper', 'Be brief with {name}.', $this->user);

        $this->assertTrue(Prompts::isEdited('helper'));
        $this->assertSame('Be brief with Ann.', Prompts::render('helper', ['name' => 'Ann']));

        Prompts::save('helper', 'You help {name}. Today is {date}.', $this->user);

        $this->assertFalse(Prompts::isEdited('helper'));
        $this->assertSame('You help {name}. Today is {date}.', Prompts::text('helper'));

        $this->expectException(InvalidArgumentException::class);
        Prompts::text('unknown');
    }

    public function test_history_keeps_versions(): void
    {
        $this->assertCount(0, Prompts::history('helper'));

        Prompts::save('helper', 'First', $this->user);
        Prompts::save('helper', 'First', $this->user);
        Prompts::save('helper', 'Second', $this->user);
        Prompts::save('helper', '  ', $this->user);

        $history = Prompts::history('helper');

        $this->assertSame([null, 'Second', 'First'], $history->pluck('text')->all());
        $this->assertSame($this->user->id, $history->first()['user_id']);
    }
}
