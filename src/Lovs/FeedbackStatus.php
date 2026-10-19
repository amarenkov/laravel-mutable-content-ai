<?php

namespace Amarenkov\MutableContentAi\Lovs;

use Amarenkov\MutableContent\Domain\Lov\Lov;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Lov\Item as LovItem;

#[ClassCode(FeedbackStatus::CLASS_CODE)]
#[ClassLabel('Feedback status')]
#[LovItem(FeedbackStatus::NEW, 'New')]
#[LovItem(FeedbackStatus::RESOLVED, 'Resolved')]
class FeedbackStatus extends Lov
{
    // const
    public const CLASS_CODE = 'ai_feedback_status';

    public const NEW = 'new';
    public const RESOLVED = 'resolved';
}
