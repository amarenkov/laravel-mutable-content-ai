<?php

namespace Amarenkov\MutableContentAi\Lovs;

use Amarenkov\MutableContent\Domain\Lov\Lov;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Lov\Item as LovItem;

#[ClassCode(RunStatus::CLASS_CODE)]
#[ClassLabel('Run status')]
#[LovItem(RunStatus::COMPLETED, 'Completed')]
#[LovItem(RunStatus::FAILED, 'Failed')]
class RunStatus extends Lov
{
    // const
    public const CLASS_CODE = 'ai_run_status';

    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
}
