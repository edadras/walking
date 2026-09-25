@extends('landing.layout')
@section('title', $inviter ? $inviter.' تو را به گام‌یار دعوت کرده' : 'دعوت به گام‌یار')
@section('og', 'با کد دعوت ثبت‌نام کن و امتیاز هدیه بگیر.')
@section('content')
<div class="card">
  @if ($code)
    <h1>{{ $inviter }} تو را به گام‌یار دعوت کرده</h1>
    <p>با این کد ثبت‌نام کن تا هر دو امتیاز هدیه بگیرید:</p>
    <div class="code" id="code" title="برای کپی بزن">{{ $code }}</div>
    <p id="copied" style="text-align:center;font-size:13px">برای کپی روی کد بزن؛ هنگام ثبت‌نام در بخش «کد دعوت» وارد کن.</p>
    <a class="btn primary" href="{{ $appLink }}">اپ را نصب کرده‌ام — باز کن</a>
  @else
    <h1>دعوت به گام‌یار</h1>
    <p>این لینک دعوت معتبر نیست، اما هنوز می‌توانی گام‌یار را نصب کنی.</p>
  @endif
  @include('landing.stores')
</div>
@if ($code)
<script>
  document.getElementById('code').addEventListener('click', function () {
    navigator.clipboard && navigator.clipboard.writeText('{{ $code }}').then(function () {
      document.getElementById('copied').textContent = 'کپی شد ✓';
    });
  });
</script>
@endif
@endsection
