// Nettlesertest for admin-flatene: ting PHP-testene ikke kan se -
// at stilarket faktisk lastes, at den klebrige lagre-raden fester seg,
// at repeateren legger til rader med unike indekser, og at ingen flate
// gir horisontal scroll ved 320 px (WCAG 1.4.10 Reflow).
//
// Kjøring: sett opp riggen (bin/testrigg.sh), seed demodata, start
// serveren på 127.0.0.1:8890 og kjør:
//   node test-admin-flyt.js
// Krever playwright-pakken og Chromium (executablePath under). Logger
// inn med rigg-brukeren admin/admin.
const { chromium } = require('playwright');

const BASE = process.env.SAMLAB_BASE || 'http://127.0.0.1:8890';
const SIDER = [
  ['kontrollpanelet', '/wp-admin/admin.php?page=samlab-kontrollpanel'],
  ['rapporten', '/wp-admin/admin.php?page=samlab-rapport'],
  ['innstillingene', '/wp-admin/options-general.php?page=samlab'],
];

(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  let feil = 0;
  const sjekk = (t, ok) => { console.log((ok ? 'OK   ' : 'FEIL ') + t); if (!ok) feil = 1; };

  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();

  await page.goto(BASE + '/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await page.click('#wp-submit');
  await page.waitForLoadState('networkidle');
  sjekk('innlogget i wp-admin', page.url().includes('wp-admin'));

  // Stilark og kroppsklasse - bekreftet i nettleseren, ikke bare i kroker.
  for (const [navn, sti] of SIDER) {
    await page.goto(BASE + sti);
    await page.waitForLoadState('networkidle');
    const lastet = await page.evaluate(() =>
      [...document.querySelectorAll('link[rel=stylesheet]')].some((l) => l.href.includes('assets/css/admin.css')));
    const kropp = await page.evaluate(() => document.body.classList.contains('samlab-admin-side'));
    sjekk(`${navn}: stilarket lastet og kroppsklassen satt`, lastet && kropp);
  }

  // Den klebrige lagre-raden skal faktisk feste seg til bunnen mens man
  // scroller gjennom skjemaet. Et fullside-skjermbilde flater ut sticky
  // til naturlig posisjon og kan ikke brukes til å avgjøre dette.
  await page.goto(BASE + '/wp-admin/options-general.php?page=samlab');
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => window.scrollTo(0, 1200));
  await page.waitForTimeout(200);
  const festet = await page.evaluate(() => {
    const r = document.querySelector('p.submit').getBoundingClientRect();
    return Math.abs(r.bottom - window.innerHeight) < 3;
  });
  sjekk('lagre-raden fester seg til bunnen midt i skjemaet', festet);

  // ... og den skal ikke dekke feltet som får fokus (WCAG 2.4.11).
  await page.locator('form input[type=text], form textarea, form select').last().focus();
  await page.waitForTimeout(300);
  const dekket = await page.evaluate(() => {
    const s = document.querySelector('p.submit').getBoundingClientRect();
    const f = document.activeElement.getBoundingClientRect();
    return f.bottom > s.top && f.top < s.bottom;
  });
  sjekk('lagre-raden dekker ikke det fokuserte feltet', !dekket);

  // Rapporten: .subsubsub er float: left i core, så alt under den må
  // tømme floaten. Uten det klemmes sammendragsraden ned til null
  // bredde ved siden av periodevalget - noe bare en nettleser ser.
  await page.goto(BASE + '/wp-admin/admin.php?page=samlab-rapport');
  await page.waitForSelector('.subsubsub');
  await page.waitForSelector('.samlab-sammendrag');
  const layout = await page.evaluate(() => {
    const per = document.querySelector('.subsubsub').getBoundingClientRect();
    const ul = document.querySelector('.samlab-sammendrag').getBoundingClientRect();
    const wrap = document.querySelector('.wrap').getBoundingClientRect();
    return { under: ul.top >= per.bottom - 1, andel: ul.width / wrap.width };
  });
  sjekk('sammendraget ligger under periodevalget, ikke ved siden av', layout.under);
  sjekk('sammendraget bruker hele bredden', layout.andel > 0.9);

  // Tjeneste-repeateren i bedrifts-metaboksen.
  await page.goto(BASE + '/wp-admin/edit.php?post_type=samlab_bedrift');
  const forste = page.locator('a.row-title').first();
  if (await forste.count()) {
    await forste.click();
    await page.waitForLoadState('networkidle');
    const før = await page.locator('#samlab-tjenester .samlab-tjeneste').count();
    await page.click('#samlab-legg-til-tjeneste');
    const indekser = await page.evaluate(() =>
      [...document.querySelectorAll('#samlab-tjenester .samlab-tjeneste')].map((d) => d.dataset.samlabIndeks));
    sjekk('repeateren legger til en rad', indekser.length === før + 1);
    sjekk('radindeksene er fortsatt unike', new Set(indekser).size === indekser.length);
  }

  // WCAG 1.4.10 Reflow: ingen flate skal gi horisontal scroll ved 320 px.
  // De brede tabellene scroller i sin egen region i stedet.
  await page.setViewportSize({ width: 320, height: 800 });
  for (const [navn, sti] of SIDER) {
    await page.goto(BASE + sti);
    await page.waitForLoadState('networkidle');
    const scroll = await page.evaluate(() => {
      const e = document.documentElement;
      return e.scrollWidth > e.clientWidth + 1;
    });
    sjekk(`${navn}: ingen horisontal scroll ved 320 px`, !scroll);
  }

  await browser.close();
  process.exit(feil);
})();
