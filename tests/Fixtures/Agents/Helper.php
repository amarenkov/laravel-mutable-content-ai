<?php

namespace Amarenkov\MutableContentAi\Tests\Fixtures\Agents;

use Stringable;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

use Amarenkov\MutableContentAi\Services\Approvals;
use Amarenkov\MutableContentAi\Services\Memories;
use Amarenkov\MutableContentAi\Services\Runs;
use Amarenkov\MutableContentAi\Tools\Forget;
use Amarenkov\MutableContentAi\Tools\Remember;

use Amarenkov\MutableContentAi\Tests\Fixtures\Models\User;

class Helper implements Agent, Conversational, HasTools
{
    use Promptable;

    // public
    public function __construct(
        public User $user,
        public ?string $conversation = null,
        public bool $confirm = false,
    )
    {
    }

    public function runs(): Runs
    {
        return Runs::for($this->user, $this, $this->conversation);
    }

    public function instructions(): Stringable|string
    {
        return trim('You are a helper. '.Memories::of($this->user)->prompt());
    }

    public function messages(): iterable
    {
        return $this->runs()->history();
    }

    public function tools(): iterable
    {
        return [new Remember($this->user), new Forget($this->user, $this->confirm ? Approvals::of($this->user, $this->conversation) : null)];
    }
}
