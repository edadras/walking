<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'گام‌یار — پیاده‌روی کن، امتیاز بگیر')</title>
<meta name="description" content="گام‌یار: قدم‌هایت را بشمار، امتیاز بگیر و با آن از فروشگاه خرید کن یا به حسابت برداشت کن.">
<meta property="og:title" content="@yield('title', 'گام‌یار')">
<meta property="og:description" content="@yield('og', 'پیاده‌روی کن، امتیاز بگیر.')">
<link rel="stylesheet" href="/fonts/vazirmatn/font.css">
<style>
  :root { --green:#1A7F4B; --green-soft:#E6F2EB; --ink:#1C2B23; --muted:#55675D; --bg:#F4F7F5; --gold:#E8A400; --card:#fff; }
  @media (prefers-color-scheme: dark) { :root { --ink:#E7EFEA; --muted:#A7B7AE; --bg:#0F1512; --card:#18211C; --green-soft:#16342A; } }
  * { box-sizing:border-box; }
  body { margin:0; background:var(--bg); color:var(--ink); font-family:Vazirmatn, Tahoma, sans-serif; line-height:1.9; }
  main { max-width:560px; margin:0 auto; padding:32px 16px 48px; }
  .card { background:var(--card); border-radius:20px; padding:28px 22px; box-shadow:0 10px 30px rgba(15,94,55,.08); }
  h1 { font-size:26px; line-height:1.5; margin:0 0 8px; }
  p { color:var(--muted); margin:6px 0; }
  .brand { display:flex; align-items:center; gap:10px; font-weight:800; font-size:20px; color:var(--green); margin-bottom:24px; }
  .dot { width:14px; height:14px; border-radius:50%; background:var(--gold); }
  .btn { display:block; text-align:center; text-decoration:none; padding:14px; border-radius:12px; font-weight:700; margin-top:10px; border:1px solid transparent; }
  .primary { background:var(--green); color:#fff; }
  .secondary { background:transparent; color:var(--ink); border-color:rgba(85,103,93,.35); }
  .code { direction:ltr; letter-spacing:4px; font-size:30px; font-weight:800; text-align:center; background:var(--green-soft); color:var(--green); border-radius:14px; padding:14px; margin:16px 0 6px; cursor:pointer; user-select:all; }
  ul { padding-inline-start:20px; color:var(--muted); }
  footer { text-align:center; font-size:13px; color:var(--muted); margin-top:24px; }
  footer a { color:var(--muted); }
</style>
</head>
<body>
<main>
  <div class="brand"><span class="dot"></span>گام‌یار</div>
  @yield('content')
  <footer>© گام‌یار — قوانین و حریم خصوصی داخل اپ در دسترس است.</footer>
</main>
</body>
</html>
