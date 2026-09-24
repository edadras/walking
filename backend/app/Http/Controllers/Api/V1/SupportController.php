<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Support\SupportService;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function __construct(private readonly SupportService $support) {}

    public function categories(): JsonResponse
    {
        return response()->json(['data' => collect(TicketCategory::cases())->map(fn ($c) => ['id' => $c->value, 'label' => $c->label()])->values()]);
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()->where('user_id', $request->user()->id)->orderByDesc('last_message_at')->limit(50)->get();

        return response()->json(['data' => $tickets->map(fn (SupportTicket $t) => $this->ticket($t))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'subject' => ['required', 'string', 'min:4', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:3000'],
            'subject_type' => ['nullable', 'in:order,walking_session,visit,transaction'],
            'subject_ref' => ['nullable', 'string', 'max:32'],
        ]);
        $ticket = $this->support->open($request->user(), TicketCategory::from($data['category']), $data['subject'], $data['body'],
            $request->header('X-App-Version'), $data['subject_type'] ?? null, $data['subject_ref'] ?? null);

        return response()->json(['data' => $this->ticket($ticket->load('messages'), true)], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);

        return response()->json(['data' => $this->ticket($ticket->load('messages'), true)]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:3000']]);
        $this->support->userReply($request->user(), $ticket, $data['body']);

        return response()->json(['data' => $this->ticket($ticket->fresh()->load('messages'), true)]);
    }

    public function close(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        $this->support->closeByUser($request->user(), $ticket);

        return response()->json(['data' => $this->ticket($ticket->fresh()->load('messages'), true)]);
    }

    private function ticket(SupportTicket $t, bool $full = false): array
    {
        return [
            'id' => $t->public_id,
            'number' => '#'.strtoupper(substr($t->public_id, -6)),
            'category' => $t->category->value,
            'category_label' => $t->category->label(),
            'subject' => $t->subject,
            'status' => $t->status->value,
            'status_label' => $t->status->label(),
            'can_reply' => $t->status->isOpen() || ($t->status === TicketStatus::Resolved && $t->closed_at?->gt(now()->subDays(7))),
            'last_message_at' => $t->last_message_at->toIso8601String(),
            ...($full ? ['messages' => $t->messages->where('is_internal', false)->map(fn (SupportMessage $m) => [
                'from' => $m->author_type === 'admin' ? 'support' : 'me',
                'body' => $m->body,
                'at' => $m->created_at->toIso8601String(),
            ])->values()] : []),
        ];
    }
}
