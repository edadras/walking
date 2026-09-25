<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    use HasPublicId;

    public const PENDING = 'pending';

    public const PUBLISHED = 'published';

    public const HIDDEN = 'hidden';

    public const REJECTED = 'rejected';

    public const STATUSES = [self::PENDING => 'در انتظار تأیید پیاده‌روی', self::PUBLISHED => 'منتشرشده', self::HIDDEN => 'پنهان', self::REJECTED => 'ردشده'];

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['id', 'user_id', 'walking_session_id'];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'published_at' => 'datetime',
            'views_count' => 'integer',
            'likes_count' => 'integer',
            'qualified_views' => 'integer',
            'qualified_likes' => 'integer',
            'reports_count' => 'integer',
            'points_awarded' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WalkingSession::class, 'walking_session_id');
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function thumbUrl(): string
    {
        return Storage::disk('public')->url($this->thumb_path);
    }

    public function deleteFiles(): void
    {
        Storage::disk('public')->delete([$this->image_path, $this->thumb_path]);
    }
}
