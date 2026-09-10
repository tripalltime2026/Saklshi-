import { chromium } from 'playwright';
import assert from 'node:assert/strict';
const browser = await chromium.launch({headless:true});
const context = await browser.newContext({baseURL:'http://localhost:8001'});
const admin = await context.newPage();
const guest = await context.newPage();
try {
  const unauthorized = await context.request.get('/admin/export/backup',{maxRedirects:0});
  assert.equal(unauthorized.status(),302);
  await admin.goto('/admin/login');
  await admin.locator('input[name="login"]').fill('test-admin');
  await admin.locator('input[name="password"]').fill('ci-only-password');
  await admin.getByRole('button',{name:'შესვლა →'}).click();
  await admin.locator('.admin-app').waitFor();
  // Previously any typing permanently froze polling, even without applying a filter.
  await admin.locator('.top-search input').fill('unsent search text');
  await guest.goto('/');
  await guest.locator('[data-menu-category]').selectOption({label:'ხინკალი (5)'});
  await guest.getByRole('button',{name:'ქალაქური დამატება',exact:true}).click();
  await guest.getByRole('button',{name:'ქალაქური დამატება',exact:true}).click();
  await guest.locator('[data-menu-category]').selectOption({label:'მაყალი (5)'});
  await guest.getByRole('button',{name:'ღორის მწვადი დამატება',exact:true}).click();
  assert.equal(await guest.locator('[data-menu-total]').innerText(),'31.80 ₾');
  await guest.locator('[name="first_name"]').fill('CI Guest');
  await guest.locator('[name="last_name"]').fill('Preorder Test');
  await guest.locator('[name="phone"]').fill('+995555000987');
  await guest.locator('[name="birth_date"]').fill('1990-01-01');
  await guest.waitForFunction(()=>!document.querySelector('.summary-submit').disabled);
  await guest.locator('.summary-submit').click();
  await guest.waitForURL('**/reservation/*');
  const confirmation = await guest.locator('.confirmation-box').innerText();
  assert.match(confirmation,/ქალაქური × 2/);
  assert.match(confirmation,/ღორის მწვადი × 1/);
  assert.match(confirmation,/31\.80/);
  // Admin must get the booking without reload or applying the unfinished search.
  await admin.locator('[data-live-part="booking-rows"]').getByText('CI Guest Preorder Test',{exact:true}).waitFor({timeout:15000});
  assert.equal(await admin.locator('.top-search input').inputValue(),'unsent search text');
  const row = admin.locator('[data-live-part="booking-rows"] tr').filter({hasText:'CI Guest Preorder Test'});
  await row.locator('[data-order-detail] summary').click();
  assert.match(await row.innerText(),/ქალაქური/);
  assert.match(await row.innerText(),/31\.80/);
  assert.match(await row.locator('.table-pill').innerText(),/მაგიდა/);
  // Unchanged open menu detail survives another poll.
  await admin.waitForResponse(r=>r.url().includes('/admin/live') && r.status()===200);
  assert.equal(await row.locator('[data-order-detail]').getAttribute('open'),'');
  const csv = await context.request.get('/admin/export/reservations');
  assert.equal(csv.status(),200);
  assert.match(csv.headers()['content-disposition'],/attachment/);
  assert.match(await csv.text(),/ქალაქური × 2/);
  const backup = await context.request.get('/admin/export/backup');
  assert.equal(backup.status(),200);
  const data = await backup.json();
  const saved = data.tables.reservations.find(r=>r.phone==='+995555000987');
  assert.ok(saved);
  const lines = data.tables.reservation_items.filter(i=>i.reservation_id===saved.id);
  assert.equal(lines.length,2);
  assert.equal(lines.reduce((sum,i)=>sum+i.unit_price*i.quantity,0),3180);
  console.log('PASS: browser selection -> HTTP booking -> confirmation -> automatic admin refresh -> full export');
} catch (error) {
  console.error('Admin page:', await admin.locator('body').innerText());
  console.error('Guest page:', await guest.locator('body').innerText());
  throw error;
} finally { await browser.close(); }
