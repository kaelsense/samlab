# CLAUDE.md - Samlab (WordPress-plugin)

Dette er **Samlab** - en WordPress-plugin under utvikling: en intern
community-portal for norske coworking-hus og kontorfellesskap, generisk
nok til ren community-bruk. Plugin-slug og tekstdomene: `samlab`. Digitelle AS eier produktet; Lius
kunnskaps- og gründerhus er første kunde og referanse.

Les `handover/HANDOVER.md` først, deretter
`handover/docs/forslag-wordpress-utvidelse.md` (den vedtatte planen).
Konsept og kravspesifikasjon ligger i
`handover/docs/forslag-b2b-community-os.md`, assistent-modulen i
`handover/docs/forslag-chatbot-lius-intern.md`.

## Ufravikelige prinsipper

- **Ingen Lius-hardkoding.** Ikke i slug, tekstdomene, navn, tekster,
  farger, logoer eller seed-data. «Pulsen», «Kimma», `/intern/` og
  grønnfargen `#a5c23e` er Lius-innstillinger, ikke standarder.
  Flatenavn, portal-sti, portalnavn og aksentfarge er innstillinger.
- **Temaets design er standard.** CSS bygges på tokens som leser
  WordPress' preset-variabler (`--wp--preset--color--*`,
  `--wp--preset--font-family--*`) med nøytrale fallbacks. Prototypen i
  `handover/referanse/prototype-demo.html` er fasit for struktur og UX - ikke
  for farger og fonter.
- **Ingen betal-avhengigheter.** Ikke ACF Pro, ingen tema- eller
  Elementor-kobling. Egne metabokser, eget app-skall på portal-rutene
  (`add_rewrite_rule` + `template_redirect`-innloggingsport).
- **Norsk bokmål først**, all brukervendt tekst via språkfiler
  (`__()`/`_e()` med pluginens tekstdomene).
- **Vanilla frontend.** Små JS-kall mot REST (`cookie + nonce`), ingen
  React/bundler.

## Sikkerhet

- Capability-sjekk og nonce på alle REST-endepunkter og skjemaer.
  Escaping ved output, prepared statements ved SQL. WPCS-standard.
- Hemmeligheter aldri i repo eller database. Claude API-nøkkelen leses
  fra en konstant i `wp-config.php`. Assistentens kunnskapsgrunnlag
  skal aldri inneholde passord eller sensitive detaljer.
- Bedriftsredaktører kan kun redigere egen bedrift - håndhev med
  `map_meta_cap`, ikke bare skjult UI.
- Portalinnhold er noindex og utenfor offentlige sitemaps/søk.

## Skrivestil og arbeidsform

- Tankestreker skrives som ` - ` (bindestrek med mellomrom). Aldri
  em-dash (U+2014) i kode, dokumenter eller brukertekster.
- Dokumentasjon på norsk bokmål. Kode og identifikatorer på engelsk,
  kommentarer gjerne på norsk.
- **API-dokumentasjonen følger koden.** `docs/hooks.md` er fasit for
  REST-endepunkter, actions og filtre, og oppdateres i samme endring
  som koden - ikke etterpå. Relevante endringer er: ny, endret eller
  fjernet rute; endret sti, parameter, svarform, feilkode eller
  capability-krav; ny, endret eller fjernet `do_action`/
  `apply_filters`, inkludert parametrene deres; og endret oppførsel
  som dokumentet beskriver (grenser, avgrensninger, standardverdier).
  Rører endringen auth, escaping, hemmeligheter eller restrisiko,
  oppdateres `docs/sikkerhet.md` på samme måte.
- Spør før: nye avhengigheter, force-push/rebase, endring av slug
  eller tekstdomene, og alt som rører lisens- eller prismodell.
- Test mot minst ett standardtema (f.eks. Twenty Twenty-Four) i
  tillegg til kundens tema før noe erklæres ferdig - tema-arven er et
  produktløfte.

## Avklart scope

- WordPress-plugin er sporet. Astro/Supabase og SaaS er lagt bort for
  nå (design datamodell og REST-API så portering forblir mulig).
- Betaling, møteromsbooking og adgangssystemer er utenfor scope -
  finnes som egne systemer.
- Distribusjon via egen oppdaterings-URL, ikke wordpress.org (ennå).
- Assistenten er en valgfri modul - portalen skal fungere fullt ut
  uten den.
