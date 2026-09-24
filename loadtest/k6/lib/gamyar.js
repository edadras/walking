// Minimal Gamyar client for k6: a WebCrypto P-256 device key, the app's
// request-signing scheme and the reserved load-test login.
import http from 'k6/http';
import { check } from 'k6';
import encoding from 'k6/encoding';
import { sha256 } from 'k6/crypto';

export const BASE = __ENV.BASE_URL || 'http://localhost:8000/api/v1';
// Path part of BASE (k6 has no URL global): signatures cover the request path.
const BASE_PATH = BASE.replace(/^https?:\/\/[^/]+/, '');
const OTP = __ENV.LOADTEST_OTP_CODE || '11111';

const b64 = (buf) => encoding.b64encode(buf, 'std');
const hex = (s) => sha256(s, 'hex');
const uuid = () => 'xxxxxxxx-xxxx-4xxx-8xxx-xxxxxxxxxxxx'.replace(/x/g, () => ((Math.random() * 16) | 0).toString(16));

/** The canonical string is ASCII (method, path, digits, hex), so bytes = char codes. */
const ascii = (str) => {
  const out = new Uint8Array(str.length);
  for (let i = 0; i < str.length; i++) out[i] = str.charCodeAt(i);
  return out;
};

/** WebCrypto returns ECDSA signatures as r||s; the server (OpenSSL) expects DER. */
function rawToDer(raw) {
  const bytes = new Uint8Array(raw);
  const int = (b) => {
    let i = 0;
    while (i < b.length - 1 && b[i] === 0) i++;
    b = b.slice(i);
    return b[0] & 0x80 ? [0x02, b.length + 1, 0x00, ...b] : [0x02, b.length, ...b];
  };
  const r = int(bytes.slice(0, 32));
  const s = int(bytes.slice(32));
  return new Uint8Array([0x30, r.length + s.length, ...r, ...s]).buffer;
}

export class Device {
  constructor() {
    this.token = null;
    this.deviceId = null;
    this.sequence = 0;
  }

  async init() {
    this.keys = await crypto.subtle.generateKey({ name: 'ECDSA', namedCurve: 'P-256' }, true, ['sign', 'verify']);
    this.publicKey = b64(await crypto.subtle.exportKey('spki', this.keys.publicKey));
  }

  async signedHeaders(method, path, body) {
    const timestamp = `${Math.floor(Date.now() / 1000)}`;
    const nonce = uuid().replace(/-/g, '');
    const canonical = [method, path, timestamp, nonce, hex(body)].join('\n');
    const raw = await crypto.subtle.sign({ name: 'ECDSA', hash: 'SHA-256' }, this.keys.privateKey, ascii(canonical));
    return { 'X-Timestamp': timestamp, 'X-Nonce': nonce, 'X-Signature': b64(rawToDer(raw)) };
  }

  headers(extra = {}) {
    return {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-App-Version': '1.0.0',
      'X-Platform': 'android',
      ...(this.deviceId ? { 'X-Device-Id': this.deviceId } : {}),
      ...(this.token ? { Authorization: `Bearer ${this.token}` } : {}),
      ...extra,
    };
  }

  get(path, tags = {}) {
    return http.get(`${BASE}${path}`, { headers: this.headers(), tags });
  }

  async post(path, data, { signed = false, tags = {}, extraHeaders = {} } = {}) {
    const body = JSON.stringify(data);
    const urlPath = `${BASE_PATH}${path}`.split('?')[0];
    const sig = signed ? await this.signedHeaders('POST', urlPath, body) : {};
    return http.post(`${BASE}${path}`, body, { headers: this.headers({ ...sig, ...extraHeaders }), tags });
  }

  /** Registers the device and logs in a reserved +98999… number (fixed OTP, non-production only). */
  async login(vu) {
    await this.init();
    const reg = await this.post('/devices/register', { install_id: uuid(), platform: 'android', app_version: '1.0.0', os_version: '14', model: 'k6', manufacturer: 'loadtest', public_key: this.publicKey }, { signed: true, tags: { name: 'register' } });
    check(reg, { 'device registered': (r) => r.status === 200 || r.status === 201 });
    this.deviceId = reg.json('data.device_id');

    const phone = `0999${String(1000000 + vu).slice(-7)}`;
    const req = await this.post('/auth/otp/request', { phone }, { signed: true, tags: { name: 'otp_request' } });
    check(req, { 'otp requested': (r) => r.status === 200 });
    const ver = await this.post('/auth/otp/verify', { phone, code: OTP }, { signed: true, tags: { name: 'otp_verify' } });
    check(ver, { 'logged in': (r) => r.status === 200 });
    this.token = ver.json('data.token');
  }

  /** A plausible passive session ending a few minutes ago. */
  nextSession() {
    const end = Date.now() - 5 * 60 * 1000;
    const start = end - 20 * 60 * 1000;
    const steps = 900 + Math.floor(Math.random() * 600);
    return {
      client_session_id: uuid(),
      sequence: ++this.sequence,
      kind: 'passive',
      source: 'step_counter',
      started_at: new Date(start).toISOString(),
      ended_at: new Date(end).toISOString(),
      raw_steps: steps,
      buckets: [{ started_at: new Date(start).toISOString(), duration_s: 1200, steps, activity_type: 'walking' }],
    };
  }
}
