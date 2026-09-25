<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>نتیجه پرداخت — گام‌یار</title>
<style>
  :root { --green:#1A7F4B; --ink:#1C2B23; --muted:#55675D; --bg:#F4F7F5; --danger:#C3362B; --gold:#E8A400; }
  * { box-sizing: border-box; }
  body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--bg);
         font-family: Vazirmatn, Tahoma, sans-serif; color:var(--ink); padding:16px; }
  .card { background:#fff; border-radius:20px; padding:32px 24px; max-width:420px; width:100%; text-align:center;
          box-shadow:0 10px 30px rgba(15,94,55,.08); }
  .icon { width:72px; height:72px; border-radius:50%; margin:0 auto 16px; display:flex; align-items:center; justify-content:center; font-size:36px; color:#fff; }
  h1 { font-size:20px; margin:0 0 8px; }
  p { color:var(--muted); line-height:1.9; margin:4px 0; font-size:15px; }
  .ref { direction:ltr; font-weight:700; color:var(--ink); letter-spacing:1px; }
  a.btn { display:block; margin-top:24px; background:var(--green); color:#fff; text-decoration:none; padding:14px; border-radius:12px; font-weight:700; }
</style>
</head>
<body>
<div class="card">
  @if ($paid)
    <div class="icon" style="background:var(--green)">✓</div>
    <h1>پرداخت با موفقیت انجام شد</h1>
    <p>سفارش {{ $number }} — {{ number_format($amount) }} ریال</p>
    <p>کد پیگیری: <span class="ref">{{ $refId }}</span></p>
    @if ($needsSupport)
      <p style="color:var(--danger)">این سفارش پیش‌تر لغو شده بود؛ مبلغ توسط پشتیبانی بازگردانده می‌شود.</p>
    @endif
  @elseif ($pending)
    <div class="icon" style="background:var(--gold)">…</div>
    <h1>در حال بررسی پرداخت</h1>
    <p>ارتباط با درگاه برقرار نشد. نتیجه تا چند دقیقه دیگر در بخش سفارش‌ها نمایش داده می‌شود؛ اگر مبلغی کسر شده باشد از بین نمی‌رود.</p>
  @else
    <div class="icon" style="background:var(--danger)">✕</div>
    <h1>پرداخت انجام نشد</h1>
    <p>سفارش {{ $number }} لغو شد و مبلغی کسر نشده است. اگر مبلغی کسر شده، طبق قوانین بانکی حداکثر تا ۷۲ ساعت به حسابت برمی‌گردد.</p>
  @endif
  <a class="btn" href="{{ $appLink }}">بازگشت به گام‌یار</a>
</div>
</body>
</html>
