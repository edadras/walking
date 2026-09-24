# Load tests (k6)

Scripts in `k6/` act like real app installs: each virtual user generates its own
P-256 key with WebCrypto, registers the device, logs in and signs requests exactly
like the app (`METHOD\nPATH\nTIMESTAMP\nNONCE\nsha256(body)`, DER ECDSA).

## Login without SMS

Load tests can't receive SMS. **Outside production only**, numbers in the reserved
range `0999xxxxxxx` receive the fixed code from `LOADTEST_OTP_CODE`, and skip the
per-IP / per-device OTP limits (the per-phone limit still applies). In production
the code path is disabled regardless of configuration (`OtpService::loadTestCode`).

On the staging server:

```
LOADTEST_OTP_CODE=11111   # .env, then php artisan config:cache
```

Remove it after the run.

## Scenarios

| Script | What it checks |
|--------|----------------|
| `k6/app-usage.js` | Typical daily use: config, home, signed session batch, wallet, rewards, store, leaderboard. Thresholds: error rate < 1%, p95 home < 400 ms, batch < 600 ms. |
| `k6/purchase-contention.js` | N buyers race for a low-stock product. Expect `orders_ok` = stock and zero oversell (verify stock and ledger afterwards). |

```
k6 run -e BASE_URL=https://staging.example/api/v1 -e LOADTEST_OTP_CODE=11111 -e PEAK_VUS=300 k6/app-usage.js
```

Requires k6 ≥ 1.0 (global WebCrypto; verified with k6 1.3). Run against staging with production-like
data volume, Horizon running and `php artisan optimize` applied. Watch Horizon
wait times (fraud queue) and MySQL slow log during the run.

## After a run

- Load-test users are ordinary users with `+98999…` numbers: delete them from staging.
- Check `failed_jobs` and Horizon for backlogs; the `fraud` queue should drain within its 120 s wait threshold.
