<?php

namespace App\Domain\Ops;

use App\Models\Admin;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Operational alerts: panel bell for admins who can act on them, plus the ops
 * e-mail and an optional chat webhook (Slack/Mattermost/Rocket.Chat-compatible
 * `{"text": …}`). The same alert key is sent at most once per `$quietMinutes`.
 */
class OpsAlerter
{
    public function alert(string $key, string $title, string $body, string $ability = 'ops.view', int $quietMinutes = 60): bool
    {
        if (! Cache::add('ops-alert:'.$key, 1, now()->addMinutes($quietMinutes))) {
            return false;
        }
        Log::warning('ops alert: '.$title, ['key' => $key, 'body' => $body]);

        $admins = Admin::query()->where('is_active', true)->get()->filter(fn (Admin $a) => $a->hasAbility($ability));
        if ($admins->isNotEmpty()) {
            Notification::make()->title($title)->body($body)->danger()->sendToDatabase($admins);
        }
        if ($email = config('walk.ops.alert_email')) {
            rescue(fn () => Mail::raw($title."\n\n".$body, fn ($m) => $m->to($email)->subject('[گام‌یار] '.$title)), report: false);
        }
        if ($url = config('walk.ops.alert_webhook')) {
            try {
                Http::timeout(5)->post($url, ['text' => "⚠️ {$title}\n{$body}"]);
            } catch (Throwable $e) {
                Log::error('ops webhook failed', ['error' => $e->getMessage()]);
            }
        }

        return true;
    }
}
