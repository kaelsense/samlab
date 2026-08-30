// Nettlesertest for G3: samtykkeflyten i portalflaten «Koblinger»
// mot riggen med seed-data (den forespurte koblingen Tallknuserne ↔
// Jonas Dal). Kjøring: sett opp riggen med fersk seed, start
// serveren på 127.0.0.1:8890, lag innloggingscookier for begge
// parter (LOGGED_IN_COOKIE-navnet og wp_generate_auth_cookie-verdien
// på hver sin linje) og kjør:
//   LEGIT_A=<cookiefil ingrid> LEGIT_B=<cookiefil jonas> node test-g3-flyt.js
// Krever playwright-pakken og Chromium (executablePath under).
const { chromium } = require('playwright');
const fs = require('fs');

const les = (fil) => fs.readFileSync(fil, 'utf8').split('\n');

(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  let feil = 0;
  const sjekk = (t, ok) => { console.log((ok ? 'OK   ' : 'FEIL ') + t); if (!ok) feil = 1; };

  const somBruker = async (cookiefil) => {
    const ctx = await browser.newContext();
    if (cookiefil) {
      const [navn, verdi] = les(cookiefil);
      await ctx.addCookies([{ name: navn, value: verdi, domain: '127.0.0.1', path: '/' }]);
    }
    return ctx.newPage();
  };

  // 1) Utlogget: innloggingsporten tar imot.
  const anonym = await somBruker(null);
  await anonym.goto('http://127.0.0.1:8890/portal/koblinger/', { waitUntil: 'networkidle' });
  sjekk('utlogget sendes til innlogging', anonym.url().includes('wp-login.php'));

  // 2) Jonas ser forespørselen og takker ja.
  const jonas = await somBruker(process.env.LEGIT_B);
  await jonas.goto('http://127.0.0.1:8890/portal/koblinger/', { waitUntil: 'networkidle' });
  sjekk('flaten lastes med forespørsel', (await jonas.textContent('body')).includes('Forespørsler til deg'));
  const kort = jonas.locator('.samlab-kort', { hasText: 'Tallknuserne' });
  sjekk('seed-forespørselen vises med begrunnelse', (await kort.textContent()).includes('regnskapet i oppstarten'));
  await Promise.all([
    jonas.waitForNavigation({ waitUntil: 'networkidle' }),
    kort.locator('button[data-svar="ja"]').click(),
  ]);
  sjekk('eget ja gir ventemelding', (await jonas.textContent('body')).includes('venter på svar fra motparten'));

  // 3) Ingrid (kontaktperson for Tallknuserne) takker også ja.
  const ingrid = await somBruker(process.env.LEGIT_A);
  await ingrid.goto('http://127.0.0.1:8890/portal/koblinger/', { waitUntil: 'networkidle' });
  // Ingrid er også part i andre seed-koblinger med «Tallknuserne» i
  // tittelen - forespørselen er kortet som fortsatt har svar-knapp.
  const kortA = ingrid.locator('.samlab-kort', { hasText: 'Tallknuserne' });
  sjekk('motparten ser samme forespørsel', await kortA.locator('button[data-svar="ja"]').count() === 1);
  await Promise.all([
    ingrid.waitForNavigation({ waitUntil: 'networkidle' }),
    kortA.locator('button[data-svar="ja"]').click(),
  ]);
  const etterpaa = await ingrid.textContent('body');
  sjekk('begge ja gir aktiv kobling', etterpaa.includes('Aktive koblinger'));
  sjekk('statuskjeden viser godkjent', await ingrid.locator('.samlab-status-kjede .er-naadd', { hasText: 'Godkjent' }).count() >= 1);
  sjekk('kontaktinfo deles etter godkjenning', etterpaa.includes('Ta kontakt:'));

  // 4) Jonas ser også den aktive koblingen med kontaktinfo.
  await jonas.reload({ waitUntil: 'networkidle' });
  sjekk('også motparten ser aktiv kobling med kontakt', (await jonas.textContent('body')).includes('Ta kontakt:'));

  await browser.close();
  process.exit(feil);
})().catch((e) => { console.error('FEIL uventet: ' + e.message); process.exit(1); });
