<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClientError;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * First-party crash reporting. Public (crashes happen before sign-in too) and
 * tightly throttled; reports are grouped by fingerprint so a crash loop costs
 * one row, and text is scrubbed of anything that looks like a phone number,
 * token or code before it is stored.
 */
class ClientErrorController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:2000'],
            'stack' => ['nullable', 'string', 'max:8000'],
            'fatal' => ['boolean'],
            'app_version' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z.+-]+$/'],
        ]);

        $type = self::scrub($data['type']);
        $message = mb_substr(self::scrub($data['message']), 0, 500);
        $stack = isset($data['stack']) ? mb_substr(self::scrub($data['stack']), 0, 4000) : null;
        // Group by what crashed and where, not by the (variable) message text.
        $fingerprint = hash('sha256', $type."\n".implode("\n", array_slice(explode("\n", (string) $stack), 0, 5)));
        $now = now();
        $user = $request->user('sanctum');

        DB::transaction(function () use ($fingerprint, $type, $message, $stack, $data, $now, $user) {
            $row = ClientError::query()->where('fingerprint', $fingerprint)->lockForUpdate()->first();
            if ($row) {
                $row->forceFill([
                    'occurrences' => $row->occurrences + 1,
                    'last_seen_at' => $now,
                    'message' => $message,
                    'app_version' => $data['app_version'] ?? $row->app_version,
                    'last_user_id' => $user?->id ?? $row->last_user_id,
                    // A crash seen again after being marked fixed reopens it.
                    'resolved_at' => null,
                ])->save();

                return;
            }
            ClientError::query()->create([
                'fingerprint' => $fingerprint, 'error_type' => $type, 'message' => $message, 'stack' => $stack,
                'fatal' => $data['fatal'] ?? false, 'app_version' => $data['app_version'] ?? null, 'platform' => mb_substr((string) request()->header('X-Platform', ''), 0, 20) ?: null,
                'last_user_id' => $user?->id, 'first_seen_at' => $now, 'last_seen_at' => $now,
            ]);
        });

        return response()->noContent();
    }

    public static function scrub(string $text): string
    {
        return preg_replace([
            '/\b(?:Bearer\s+)?(?=[A-Za-z_\-|]*\d)[A-Za-z0-9_\-|]{24,}\b/u', // tokens, keys, ids (with a digit, so long class names survive)
            '/(?<!\d)(?:\+98|0098|0)?9\d{9}(?!\d)/u',   // Iranian mobile numbers
            '/\b[\w.+-]+@[\w-]+\.[\w.]+\b/u',          // e-mail addresses
            '/\b\d{5,}\b/u',                            // codes, card or order numbers
        ], ['[redacted]', '[phone]', '[email]', '[n]'], $text) ?? '';
    }
}
