<?php

namespace App\Enums;

enum SessionRewardStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Rewarded = 'rewarded';
    case Denied = 'denied';
}
