<?php

namespace Amarenkov\MutableContentAi\Lovs;

use Amarenkov\MutableContent\Domain\Lov\Lov;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Lov\Item as LovItem;

#[ClassCode(ApprovalStatus::CLASS_CODE)]
#[ClassLabel('Approval status')]
#[LovItem(ApprovalStatus::PENDING, 'Pending')]
#[LovItem(ApprovalStatus::APPROVED, 'Approved')]
#[LovItem(ApprovalStatus::REJECTED, 'Rejected')]
class ApprovalStatus extends Lov
{
    // const
    public const CLASS_CODE = 'ai_approval_status';

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
}
