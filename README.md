# Samlab

WordPress-plugin: en intern community-portal for coworking-hus og
kontorfellesskap - bedriftskatalog med profiler, behov og tilbud,
vegg med reaksjoner, kommentarer, avstemninger og lesebekreftelser,
arrangementer, håndbok og globalt søk. Fasiliteringslaget gir
koblinger/introduksjoner med to-parts samtykke (ingen kobles uten
at begge har takket ja - partene svarer selv fra portalens
koblingsflate), utfallsregistrering («ble det noe?» - møte, avtale,
henvisning), kontrollpanel og aggregert rapport med CSV-eksport
for verten, in-app-varsler, regelbasert matching, ukesbrev på
e-post og en infoskjerm med nøkkel-URL. Portalen bor bak innlogging
på en egen sti (standard `/portal/`), kler seg i temaets
designtokens, og alt av navn, stier og farger er innstillinger -
ingen kundeverdier i kode. Admin-flatene i wp-admin bruker
WordPress' egne komponenter og maler ingen egen merkefarge, så de
følger fargeskjemaet den enkelte har valgt på profilen sin.
Digitelle AS eier produktet.

Se `handover/HANDOVER.md` og
`handover/docs/forslag-wordpress-utvidelse.md` for plan og konsept.
Arbeidslisten ligger i [BACKLOG.md](BACKLOG.md); åpne spørsmål i
[AVKLARINGER.md](AVKLARINGER.md).

## Krav

- WordPress 6.4 eller nyere (testet mot nyeste stabile, WordPress 7.1)
- PHP 8.2 eller nyere (utvidelsene som følger standard WP-drift;
  GD anbefales for seed-kommandoens demobilder)
- WP-Cron i normal drift. Pluginen planlegger to daglige jobber ved
  aktivering (og rydder dem ved deaktivering): `samlab_matching`
  (regelbasert matching av behov mot bedrifter, pluss «ble det
  noe?»-påminnelser til partene 14 dager etter en introduksjon) og
  `samlab_ukesbrev` (som selv sjekker innstilt ukedag før
  utsending). Standardoppsettet med WP-Cron holder så lenge
  nettstedet har jevnlig trafikk; på stille nettsteder anbefales
  ekte cron mot `wp-cron.php` (f.eks. hvert 15. minutt) med
  `DISABLE_WP_CRON` satt. Jobbene kan også kjøres manuelt med
  `wp samlab match` og `wp samlab ukesbrev [--vis]`.
- Utgående e-post (`wp_mail`) for ukesbrevet og eventuelle
  varslings-e-poster - sett opp SMTP der serveren ikke har sendmail
- Ingen betal-avhengigheter og ingen runtime-avhengigheter utover
  WordPress selv

## Installasjon i et eksisterende WordPress

1. Kopier `samlab/`-mappen til `wp-content/plugins/` (eller pakk
   den som zip og last opp i wp-admin).
2. Aktiver «Samlab» under Utvidelser. Aktiveringen registrerer
   roller, innholdstyper, databasetabeller og portal-rutene, og
   flusher permalenkene én gang.
3. Sørg for at nettstedet bruker «pene» permalenker
   (Innstillinger → Permalenker, f.eks. «Innleggsnavn»).
4. Portalen svarer nå på `/portal/` og krever innlogging. Tilpass
   portalnavn, sti, flatenavn, aksentfarge og logo under
   Innstillinger → Samlab.
5. Gi medlemmene en av rollene Medlem, Bedriftsredaktør eller
   Moderator. Administrator og redaktør har alle portal-rettigheter.
6. Valgfritt: fyll installasjonen med nøytrale demodata for å se
   flatene i bruk:

   ```
   wp samlab seed        # bedrifter, behov, vegg m/avstemning, håndbok, arrangementer, koblinger
   wp samlab seed --slett  # fjerner alt demoinnhold sporløst
   ```

Distribusjon skjer via egen oppdaterings-URL, ikke wordpress.org
(ennå).

### Assistenten (valgfri KI-modul)

Portalen fungerer fullt ut uten assistenten - modulen er av som
standard, og når den er av lastes ingen assistent-kode. Slik slås
den på:

1. Legg API-nøkkelen i `wp-config.php` (aldri i databasen - dette
   er eneste sted pluginen leser den fra):

   ```php
   define( 'SAMLAB_CLAUDE_API_KEY', 'sk-ant-…' );
   ```

   Nøkkel opprettes på [console.anthropic.com](https://console.anthropic.com).
2. Slå på modulen under Innstillinger → Samlab → «Assistenten», og
   sett gjerne navn, velkomstmelding, tone og eksterne kilder.
   Statusraden viser om nøkkelen er funnet - aldri selve verdien.
3. Bygg kunnskapsgrunnlaget med «Bygg nå»-knappen (eller
   `wp samlab kunnskap`). Det bygges deretter automatisk hver dag,
   fra portalinnholdet og kildene - aldri passord eller innhold
   utenfor portalen.
4. Chat-knappen dukker opp nede til høyre i portalen for innloggede
   medlemmer.

**Personvern og ubesvart-køen:** samtaler logges aldri. Spørsmål
assistenten ikke finner svar på i kunnskapsgrunnlaget, lagres
derimot anonymt i ubesvart-køen - kun spørsmålsteksten, datoen og
en teller, aldri hvem som spurte og aldri svaret - så verten kan
fylle håndboken og FAQ-en med det som mangler. Køen er på som
standard og slås av under Innstillinger → Samlab → «Ubesvart-kø»;
av betyr at ingenting lagres.

**Kostnad:** hvert spørsmål går mot Claude API og betales per bruk
hos Anthropic (modell velges i innstillingene, standard
`claude-opus-5`). Pluginen begrenser kostnadene med prompt-caching
av kunnskapsgrunnlaget og en rate-grense per medlem (15 spørsmål
per 5 minutter). Å slå av modulen stopper all bruk umiddelbart.

**Verifisering uten nøkkel og nett** (f.eks. i testriggen): legg en
mu-plugin som mocker API-et, så svarer assistenten uten at noe
forlater maskinen:

```php
<?php // wp-content/mu-plugins/mock-claude.php
add_filter( 'pre_http_request', function ( $ignorert, $args, $url ) {
    if ( false === strpos( $url, 'api.anthropic.com' ) ) {
        return $ignorert;
    }
    return array(
        'response' => array( 'code' => 200 ),
        'body'     => wp_json_encode( array( 'content' => array( array( 'type' => 'text', 'text' => 'Mock-svar.' ) ) ) ),
    );
}, 10, 3 );
```

Med mocken på plass holder en hvilken som helst verdi i konstanten
(f.eks. `define( 'SAMLAB_CLAUDE_API_KEY', 'mock' );`).

## Fra klone til kjørende portal (utviklere)

Det raskeste, uten docker:

```
git clone <repo-url> samlab && cd samlab
bin/testrigg.sh
```

Scriptet bygger en fullverdig WordPress med SQLite i
`/tmp/samlab-testrigg/wp`, lenker inn pluginen, aktiverer den og
setter opp permalenker og en router for PHPs innebygde server. Det
skriver de neste kommandoene til terminalen når det er ferdig:

```
php /tmp/samlab-testrigg/wp-cli.phar --allow-root --path=/tmp/samlab-testrigg/wp samlab seed
php -S 127.0.0.1:8890 -t /tmp/samlab-testrigg/wp /tmp/samlab-testrigg/wp/router.php
```

Åpne `http://127.0.0.1:8890/portal/` og logg inn som `admin` /
`admin`. Riggen er idempotent - kjør `bin/testrigg.sh` igjen etter
behov, og gi den gjerne en annen målmappe som argument.

### wp-env (docker)

Med Docker tilgjengelig gir `npx wp-env start` et miljø fra
`.wp-env.json` (nyeste stabile WordPress, PHP 8.2, Twenty
Twenty-Four). wp-admin: `http://localhost:8888/wp-admin` med
`admin` / `password`. Aktiver pluginen med
`npx wp-env run cli wp plugin activate samlab`. Stopp med
`npx wp-env stop`.

## Verifisering og kodestandard

```
composer install   # dev-verktøy (PHPCS med WordPress-standarder)
composer lint      # WPCS over samlab/
composer lint:fix  # autoretting
```

Røyk-testene i `tests/rigg/` kjøres samlet mot riggen med

```
tests/rigg/kjor-alle.sh [riggmappe]   # standard /tmp/samlab-testrigg
```

Scriptet orkestrerer modul-tilstanden selv (assistent-testene
trenger modulen henholdsvis av og på) og avslutter med samlet
exit-kode. Enkelttester kjøres med `$WP eval-file
tests/rigg/test-e4.php` (osv.); `tests/rigg/test-f4-flyt.js` er en
valgfri nettlesertest av chat-widgeten (Playwright - se
kommentaren i filen). CI (GitHub Actions) kjører `php -l` og
`composer lint` på hver push og PR.

## Dokumentasjon

- [CHANGELOG.md](CHANGELOG.md) - hva som er endret mellom versjoner
- [docs/hooks.md](docs/hooks.md) - REST-endepunkter, actions og
  filters (API-flaten for integrasjoner)
- [docs/api-dokumentasjon.html](docs/api-dokumentasjon.html) - den
  offentlige API-dokumentasjonssiden for integratorer, med
  interaktive endepunkt-testere; bygger på `docs/hooks.md` og
  føres i samme endring som API-flaten
- [docs/sikkerhet.md](docs/sikkerhet.md) - sikkerhetsgjennomgangen:
  alle flater med bekreftet status og trusselmodellens aksepterte
  restrisikoer
- [docs/tema-test.md](docs/tema-test.md) - temakompatibilitet med
  skjermbilder og kontrastmålinger
- [AVKLARINGER.md](AVKLARINGER.md) - beslutningslogg og åpne
  veivalg
