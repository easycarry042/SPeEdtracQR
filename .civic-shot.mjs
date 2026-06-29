import pw from '/home/andrewpc/.npm/_npx/e41f203b7505f1fb/node_modules/playwright/index.js';
const { chromium } = pw;

const base = 'http://127.0.0.1:8000';
const out = '/tmp/civic';
const b = await chromium.launch({
  executablePath: '/home/andrewpc/.cache/ms-playwright/chromium-1223/chrome-linux64/chrome',
});
const ctx = await b.newContext({ viewport: { width: 1366, height: 900 } });
const p = await ctx.newPage();
const shot = async (n) => { await p.screenshot({ path: `${out}/${n}.png`, fullPage: true }); console.log('shot', n, p.url()); };

await p.goto(`${base}/login`, { waitUntil: 'networkidle' });
await p.fill('#email', 'admin@speedtraqr.com');
await p.fill('#password', 'password123');
await Promise.all([p.waitForLoadState('networkidle'), p.click('button[type=submit].auth-button')]);

for (const [n, path] of [
  ['r-dashboard', '/dashboard'],
  ['r-history', '/history'],
  ['r-users', '/admin/users'],
  ['r-movements', '/movements'],
]) {
  await p.goto(`${base}${path}`, { waitUntil: 'networkidle' }).catch(()=>{});
  await shot(n);
}
await p.goto(`${base}/track/SPD-20260613-D3XG87`, { waitUntil: 'networkidle' }).catch(()=>{});
await shot('r-track');

await b.close();
console.log('done');
