// Nettlesertest for F4: hele samtaleflyten mot riggen med mocket
// Claude-API. Kjøring: sett opp riggen med assistenten på, mu-plugin-
// mock og nokkel (se BACKLOG F4-notatet), lag innloggingscookie med
// lag-restlegitimasjon.php til en fil, og kjor:
//   LEGIT=<cookiefil> node test-f4-flyt.js
// Krever playwright-pakken og Chromium (executablePath under).
const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const [navn, verdi] = fs.readFileSync(process.env.LEGIT, 'utf8').split('\n');
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const ctx = await browser.newContext();
  await ctx.addCookies([{ name: navn, value: verdi, domain: '127.0.0.1', path: '/' }]);
  const side = await ctx.newPage();
  let feil = 0;
  const sjekk = (t, ok) => { console.log((ok ? 'OK   ' : 'FEIL ') + t); if (!ok) feil = 1; };

  await side.goto('http://127.0.0.1:8890/portal/', { waitUntil: 'networkidle' });
  sjekk('portalen lastes innlogget', (await side.title()).includes('Portalen'));
  sjekk('assistentknappen finnes', await side.locator('#samlab-assistent-knapp').count() === 1);
  sjekk('panelet er skjult før klikk', await side.locator('#samlab-assistent-panel').isHidden());

  await side.click('#samlab-assistent-knapp');
  sjekk('panelet åpnes ved klikk', await side.locator('#samlab-assistent-panel').isVisible());
  sjekk('velkomstmeldingen vises', (await side.locator('.samlab-assistent-boble.er-assistent').first().textContent()).includes('Spør meg om huset'));

  // Første melding - med XSS-forsøk som skal vises som ren tekst.
  await side.fill('#samlab-assistent-felt', 'Hvem lager nettsider? <script>alert(1)</script>');
  await side.click('.samlab-assistent-skjema button');
  await side.waitForSelector('.samlab-assistent-boble.er-medlem');
  sjekk('medlemmets melding vises som boble', await side.locator('.samlab-assistent-boble.er-medlem').count() === 1);
  await side.waitForFunction(() => document.querySelectorAll('.samlab-assistent-boble.er-assistent').length === 2);
  const svar1 = await side.locator('.samlab-assistent-boble.er-assistent').nth(1).textContent();
  sjekk('mock-svaret leveres i panelet', svar1.includes('Mock-svar nr. 1'));
  sjekk('svarets HTML vises som ren tekst (escaped)', svar1.includes('<em>escaping-test</em>')
    && await side.locator('.samlab-assistent-boble em').count() === 0);
  sjekk('XSS-forsøket i meldingen ble aldri tolket', await side.locator('.samlab-assistent-boble script').count() === 0);
  sjekk('skriver-indikatoren er skjult etter svar', await side.locator('#samlab-assistent-skriver').isHidden());

  // Andre melding - historikken skal være med (mocken teller meldinger).
  await side.fill('#samlab-assistent-felt', 'Og hvem tar regnskap?');
  await side.click('.samlab-assistent-skjema button');
  await side.waitForFunction(() => document.querySelectorAll('.samlab-assistent-boble.er-assistent').length === 3);
  const svar2 = await side.locator('.samlab-assistent-boble.er-assistent').nth(2).textContent();
  sjekk('andre svar bærer historikken (3 meldinger hos API-et)', svar2.includes('Mock-svar nr. 3'));

  await side.click('#samlab-assistent-lukk');
  sjekk('lukkeknappen skjuler panelet', await side.locator('#samlab-assistent-panel').isHidden());

  await browser.close();
  process.exit(feil);
})().catch((e) => { console.error('FEIL uventet: ' + e.message); process.exit(1); });
