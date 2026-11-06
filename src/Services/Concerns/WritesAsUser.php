<?php

namespace Amarenkov\MutableContentAi\Services\Concerns;

use Amarenkov\MutableContent\Models\ModelWithFields;

/**
 * Writes of a service acting for one user: the user is the change log author.
 */
trait WritesAsUser
{
    // protected
    protected function saveAsUser(ModelWithFields $model): void
    {
        $model->withLogContext()->user($this->userId);
        $model->save();
    }

    protected function deleteAsUser(ModelWithFields $model): void
    {
        $model->withLogContext()->user($this->userId);
        $model->delete();
    }
}
