# Samlab

WordPress-plugin: en intern community-portal for coworking-hus og
kontorfellesskap - bedriftskatalog med profiler, behov og tilbud,
vegg med reaksjoner, kommentarer, avstemninger og lesebekreftelser,
arrangementer, håndbok og globalt søk. Fasiliteringslaget gir
koblinger/introduksjoner med kontrollpanel for verten, in-app-
varsler, regelbasert matching, ukesbrev på e-post og en infoskjerm
med nøkkel-URL. Portalen bor bak innlogging på en egen sti
(standard `/portal/`), kler seg i temaets designtokens, og alt av
navn, stier og farger er innstillinger - ingen kundeverdier i kode.
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
  (regelbasert matching av behov mot bedrifter) og
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
   wp samlab seed        # 4 bedrifter, 5 behov, vegg og håndbok
   wp samlab seed --slett  # fjerner alt demoinnhold sporløst
   ```

Distribusjon skjer via egen oppdaterings-URL, ikke wordpress.org
(ennå).

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

Røyk-testene i `tests/rigg/` kjøres mot riggen med
`$WP eval-file tests/rigg/test-b3.php` (osv. for b4, b5, b6). CI
(GitHub Actions) kjører `php -l` og `composer lint` på hver push og
PR.

## Dokumentasjon

- [docs/hooks.md](docs/hooks.md) - REST-endepunkter, actions og
  filters (API-flaten for integrasjoner)
- [docs/sikkerhet.md](docs/sikkerhet.md) - sikkerhetsgjennomgangen:
  alle flater med bekreftet status og trusselmodellens aksepterte
  restrisikoer
- [docs/tema-test.md](docs/tema-test.md) - temakompatibilitet med
  skjermbilder og kontrastmålinger
- [AVKLARINGER.md](AVKLARINGER.md) - beslutningslogg og åpne
  veivalg
