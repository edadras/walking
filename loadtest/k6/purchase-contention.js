// Many buyers race for one low-stock product: verifies there is no oversell
// and measures purchase latency under lock contention.
//
//   k6 run -e BASE_URL=... -e LOADTEST_OTP_CODE=11111 -e PRODUCT_ID=<public id> k6/purchase-contention.js
// Prepare: a service/physical product with small stock, and fund the load-test users
// (php artisan tinker, or an admin adjustment) — see loadtest/README.md.
import { check } from 'k6';
import { Counter } from 'k6/metrics';
import { Device } from './lib/gamyar.js';

const bought = new Counter('orders_ok');
const soldOut = new Counter('orders_out_of_stock');

export const options = {
  scenarios: { rush: { executor: 'per-vu-iterations', vus: Number(__ENV.VUS || 100), iterations: 1, maxDuration: '2m' } },
  thresholds: { 'http_req_duration{name:order}': ['p(95)<1500'] },
};

export default async function () {
  const d = new Device();
  await d.login(__VU + 50000);
  const key = `k6-${__VU}-${Date.now()}-xxxxxxxx`;
  const r = await d.post('/orders', { items: [{ product_id: __ENV.PRODUCT_ID, quantity: 1 }], address_id: __ENV.ADDRESS_ID || null }, { signed: true, tags: { name: 'order' }, extraHeaders: { 'Idempotency-Key': key } });
  if (r.status === 201) bought.add(1);
  else if (r.json('error.code') === 'out_of_stock') soldOut.add(1);
  check(r, { 'bought or cleanly refused': (x) => x.status === 201 || x.status === 409 || x.status === 422 });
}
