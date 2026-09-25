<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Refuses a production deploy whose configuration would be unsafe. Run in the deploy script before `migrate`. */
class Preflight extends Command
{
    protected $signature = 'ops:preflight';

    protected $description = 'Check production-critical configuration';

    public function handle(): int
    {
        $problems = [];
        $prod = app()->isProduction();
        $check = function (bool $ok, string $problem) use (&$problems) {
            if (! $ok) {
                $problems[] = $problem;
            }
        };

        $check((string) config('app.key') !== '', 'APP_KEY is not set.');
        $check((string) config('walk.security.pii_hash_key') !== '', 'PII_HASH_KEY is not set (falls back to APP_KEY; rotating APP_KEY would break national-code/Sheba uniqueness).');
        $check(config('walk.security.pii_hash_key') !== config('app.key'), 'PII_HASH_KEY must differ from APP_KEY.');
        if ($prod) {
            $check(! config('app.debug'), 'APP_DEBUG must be false.');
            $check((bool) config('walk.security.admin_mfa_required'), 'ADMIN_MFA_REQUIRED must be true.');
            $check(config('walk.sms.driver') !== 'log', 'SMS_DRIVER is "log": OTP codes would not be delivered.');
            $check(config('queue.default') !== 'sync', 'QUEUE_CONNECTION must not be sync.');
            $check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL must be https.');
        }

        foreach ($problems as $p) {
            $this->error('✗ '.$p);
        }
        if ($problems === []) {
            $this->info('Preflight OK');
        }

        return $problems === [] || ! $prod ? self::SUCCESS : self::FAILURE;
    }
}
