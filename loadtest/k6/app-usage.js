// Typical app traffic: open app (config, home), sync a session, look at the
// wallet / rewards, browse the store. Every VU is a separate signed-in device.
//
//   k6 run -e BASE_URL=https://staging.gamyar.ir/api/v1 -e LOADTEST_OTP_CODE=11111 k6/app-usage.js
import { check, sleep } from 'k6';
import { Device } from './lib/gamyar.js';

export const options = {
  scenarios: {
    daily_users: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '2m', target: Number(__ENV.PEAK_VUS || 200) },
        { duration: '6m', target: Number(__ENV.PEAK_VUS || 200) },
        { duration: '2m', target: 0 },
      ],
      gracefulRampDown: '30s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{name:home}': ['p(95)<400'],
    'http_req_duration{name:config}': ['p(95)<200'],
    'http_req_duration{name:session_batch}': ['p(95)<600'],
    'http_req_duration{name:wallet}': ['p(95)<300'],
    'http_req_duration{name:products}': ['p(95)<400'],
    checks: ['rate>0.99'],
  },
};

const devices = {};

export default async function () {
  let d = devices[__VU];
  if (!d) {
    d = new Device();
    await d.login(__VU);
    devices[__VU] = d;
  }

  check(d.get('/config', { name: 'config' }), { 'config 200': (r) => r.status === 200 });
  check(d.get('/home', { name: 'home' }), { 'home 200': (r) => r.status === 200 });
  sleep(1);

  const batch = await d.post('/walking-sessions/batch', { sessions: [d.nextSession()] }, { signed: true, tags: { name: 'session_batch' } });
  check(batch, { 'batch accepted': (r) => r.status === 200 || r.status === 201 || r.status === 207 });
  sleep(2);

  check(d.get('/wallet', { name: 'wallet' }), { 'wallet 200': (r) => r.status === 200 });
  if (Math.random() < 0.3) check(d.get('/rewards', { name: 'rewards' }), { 'rewards 200': (r) => r.status === 200 });
  if (Math.random() < 0.2) check(d.get('/store/products', { name: 'products' }), { 'products 200': (r) => r.status === 200 });
  if (Math.random() < 0.2) check(d.get('/leaderboard?period=week', { name: 'leaderboard' }), { 'leaderboard 200': (r) => r.status === 200 });
  sleep(3 + Math.random() * 5);
}
