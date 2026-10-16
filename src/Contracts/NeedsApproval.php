<?php

namespace Amarenkov\MutableContentAi\Contracts;

use Amarenkov\MutableContentAi\Models\Approval;

/**
 * An agent tool that may wait for the user's approval and then perform the approved action.
 */
interface NeedsApproval
{
    public function name(): string;

    /**
     * Perform the approved action with the arguments the model gave; returns what was done.
     */
    public function performApproved(Approval $approval): string;
}
