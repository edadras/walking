<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>نقشه گام‌یار — مسیرهای ۲۴ ساعت گذشته</title>
<meta name="description" content="مسیرهای پیاده‌روی و دوچرخه‌سواری کاربران گام‌یار در ۲۴ ساعت گذشته، هر نفر با یک رنگ. با قدم‌هایت روی شهر نقاشی کن!">
<link rel="stylesheet" href="/fonts/vazirmatn/font.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
  :root { --green:#1A7F4B; --ink:#1C2B23; --muted:#55675D; --card:rgba(255,255,255,.94); }
  @media (prefers-color-scheme: dark) { :root { --ink:#E7EFEA; --muted:#A7B7AE; --card:rgba(24,33,28,.94); } }
  html, body { margin:0; height:100%; font-family:Vazirmatn, Tahoma, sans-serif; color:var(--ink); }
  #map { position:fixed; inset:0; background:#dfe7e2; }
  .panel { position:fixed; top:12px; inset-inline-start:12px; inset-inline-end:12px; max-width:420px; z-index:1000; background:var(--card);
           border-radius:16px; padding:12px 16px; box-shadow:0 8px 24px rgba(0,0,0,.15); line-height:1.8; }
  .panel h1 { font-size:17px; margin:0; display:flex; align-items:center; gap:8px; }
  .dot { width:12px; height:12px; border-radius:50%; background:#E8A400; display:inline-block; }
  .panel p { margin:4px 0 0; font-size:13px; color:var(--muted); }
  .count { font-weight:700; color:var(--green); }
  .leaflet-container { font-family:Vazirmatn, Tahoma, sans-serif; }
</style>
</head>
<body>
<div id="map" role="application" aria-label="نقشه مسیرها"></div>
<div class="panel">
  <h1><span class="dot"></span>نقشه گام‌یار</h1>
  <p>مسیرهای پیاده‌روی و دوچرخه‌سواری ۲۴ ساعت گذشته؛ هر نفر یک رنگ. با قدم‌هایت روی شهر نقاشی کن!</p>
  <p><span class="count" id="count">…</span> — فقط کسانی که در اپ «نمایش مسیر روی نقشه» را روشن کرده‌اند، بدون نام، و ابتدا و انتهای هر مسیر حذف شده است.</p>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
  const map = L.map('map', { zoomControl: true }).setView([35.7, 51.39], 12);
  L.tileLayer(@json($tileUrl), { maxZoom: @json($maxZoom), attribution: @json($attribution) }).addTo(map);
  const layer = L.layerGroup().addTo(map);
  const fa = n => n.toLocaleString('fa-IR');
  let timer = null;

  async function load() {
    const b = map.getBounds();
    const bbox = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].map(v => v.toFixed(4)).join(',');
    try {
      const res = await fetch('/api/v1/public/map/tracks?bbox=' + bbox, { headers: { Accept: 'application/json' } });
      if (!res.ok) { document.getElementById('count').textContent = 'این بخش از نقشه خیلی بزرگ است؛ نزدیک‌تر شوید'; return; }
      const { data } = await res.json();
      layer.clearLayers();
      for (const t of data) {
        // Older lines fade out as their 24 hours run down.
        const opacity = 0.35 + 0.6 * Math.min(1, t.expires_in / 86400);
        L.polyline(t.points, { color: t.color, weight: 4, opacity, lineCap: 'round', lineJoin: 'round' }).addTo(layer);
      }
      document.getElementById('count').textContent = fa(data.length) + ' مسیر در این محدوده';
    } catch (e) {
      document.getElementById('count').textContent = 'اتصال برقرار نشد';
    }
  }
  map.on('moveend', () => { clearTimeout(timer); timer = setTimeout(load, 250); });
  load();
  setInterval(load, 60000);
</script>
</body>
</html>
