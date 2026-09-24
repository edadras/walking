<?php

namespace App\Domain\Support;

use App\Domain\Audit\AuditLogger;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Support\Facades\DB;

class SupportService
{
    private const MAX_OPEN_PER_USER = 3;

    private const REOPEN_DAYS = 7;

    public function __construct(private readonly AuditLogger $audit) {}

    public function open(User $user, TicketCategory $category, string $subject, string $body, ?string $appVersion = null, ?string $subjectType = null, ?string $subjectRef = null): SupportTicket
    {
        $open = SupportTicket::query()->where('user_id', $user->id)->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])->count();
        if ($open >= self::MAX_OPEN_PER_USER) {
            throw ApiException::conflict('too_many_open', 'چند درخواست باز داری. لطفاً منتظر پاسخ آن‌ها بمان.');
        }

        return DB::transaction(function () use ($user, $category, $subject, $body, $appVersion, $subjectType, $subjectRef) {
            $ticket = SupportTicket::query()->create([
                'user_id' => $user->id, 'category' => $category, 'subject' => $subject, 'status' => TicketStatus::Open,
                'app_version' => $appVersion, 'subject_type' => $subjectType, 'subject_ref' => $subjectRef, 'last_message_at' => now(),
            ]);
            $this->message($ticket, 'user', $user->id, $body);

            return $ticket;
        });
    }

    public function userReply(User $user, SupportTicket $ticket, string $body): SupportTicket
    {
        $reopenable = $ticket->status === TicketStatus::Resolved && $ticket->closed_at?->gt(now()->subDays(self::REOPEN_DAYS));
        if (! $ticket->status->isOpen() && ! $reopenable) {
            throw ApiException::conflict('ticket_closed', 'این درخواست بسته شده است. در صورت نیاز درخواست جدید ثبت کن.');
        }
        $this->message($ticket, 'user', $user->id, $body);
        $ticket->forceFill(['status' => TicketStatus::AwaitingSupport, 'closed_at' => null, 'last_message_at' => now()])->save();

        return $ticket;
    }

    public function closeByUser(User $user, SupportTicket $ticket): SupportTicket
    {
        $ticket->forceFill(['status' => TicketStatus::Closed, 'closed_at' => now()])->save();

        return $ticket;
    }

    /** Staff reply (visible) or internal note. */
    public function staffReply(Admin $admin, SupportTicket $ticket, string $body, bool $internal = false, ?TicketStatus $status = null): SupportTicket
    {
        $this->message($ticket, 'admin', $admin->id, $body, $internal);
        if (! $internal) {
            $next = $status ?? TicketStatus::AwaitingUser;
            $ticket->forceFill([
                'status' => $next,
                'closed_at' => $next->isOpen() ? null : now(),
                'last_message_at' => now(),
                'assigned_admin_id' => $ticket->assigned_admin_id ?? $admin->id,
            ])->save();
            $ticket->user->notify(new UserNotification('support', 'پاسخ پشتیبانی', 'به درخواست «'.$ticket->subject.'» پاسخ داده شد.', ['type' => 'support', 'id' => $ticket->public_id]));
        }
        $this->audit->log($internal ? 'support.note' : 'support.replied', $ticket, actor: $admin);

        return $ticket;
    }

    public function setStatus(Admin $admin, SupportTicket $ticket, TicketStatus $status): void
    {
        $old = $ticket->status;
        $ticket->forceFill(['status' => $status, 'closed_at' => $status->isOpen() ? null : now()])->save();
        $this->audit->log('support.status', $ticket, ['status' => $old->value], ['status' => $status->value], actor: $admin);
    }

    private function message(SupportTicket $ticket, string $authorType, int $authorId, string $body, bool $internal = false): SupportMessage
    {
        return SupportMessage::query()->create(['support_ticket_id' => $ticket->id, 'author_type' => $authorType, 'author_id' => $authorId, 'body' => trim($body), 'is_internal' => $internal]);
    }
}
