<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = $request->user()->notifications()->latest()->cursorPaginate(min(50, $request->integer('per_page', 20)));

        return response()->json([
            'data' => collect($page->items())->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'category' => $n->data['category'] ?? null,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'data' => $n->data['data'] ?? [],
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'meta' => ['next_cursor' => $page->nextCursor()?->encode(), 'unread' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['sometimes', 'array', 'max:100'], 'ids.*' => ['uuid']]);
        $query = $request->user()->unreadNotifications();
        if (! empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        }
        $query->update(['read_at' => now()]);

        return response()->json(['data' => ['unread' => $request->user()->unreadNotifications()->count()]]);
    }
}
