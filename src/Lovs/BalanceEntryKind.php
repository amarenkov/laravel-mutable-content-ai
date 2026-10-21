<?php

namespace Amarenkov\MutableContentAi\Lovs;

use Amarenkov\MutableContent\Domain\Lov\Lov;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Lov\Item as LovItem;

#[ClassCode(BalanceEntryKind::CLASS_CODE)]
#[ClassLabel('Balance entry kind')]
#[LovItem(BalanceEntryKind::TOP_UP, 'Top-up')]
#[LovItem(BalanceEntryKind::BALANCE, 'Actual balance')]
class BalanceEntryKind extends Lov
{
    // const
    public const CLASS_CODE = 'ai_balance_entry_kind';

    public const TOP_UP = 'top_up';
    public const BALANCE = 'balance';
}
