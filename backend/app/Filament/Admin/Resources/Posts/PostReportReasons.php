<?php

namespace App\Filament\Admin\Resources\Posts;

final class PostReportReasons
{
    public const LABELS = [
        'inappropriate' => 'نامناسب',
        'privacy' => 'حریم خصوصی (چهره/پلاک/خانه)',
        'spam' => 'تبلیغ یا هرزنامه',
        'not_walk' => 'ربطی به پیاده‌روی ندارد',
        'other' => 'سایر',
    ];
}
