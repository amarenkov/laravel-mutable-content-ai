<?php

namespace Amarenkov\MutableContentAi\Lovs;

use Amarenkov\MutableContent\Domain\Lov\Lov;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Lov\Item as LovItem;

#[ClassCode(MemoryKind::CLASS_CODE)]
#[ClassLabel('Memory kind')]
#[LovItem(MemoryKind::FACT, 'Fact')]
#[LovItem(MemoryKind::PREFERENCE, 'Preference')]
#[LovItem(MemoryKind::ALIAS, 'Alias')]
class MemoryKind extends Lov
{
    // const
    public const CLASS_CODE = 'ai_memory_kind';

    public const FACT = 'fact';
    public const PREFERENCE = 'preference';
    public const ALIAS = 'alias';
}
