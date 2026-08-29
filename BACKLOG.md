# Samlab - backlog for /loop

Denne filen er arbeidslisten for autonome loop-runder i Claude Code.
Tilstanden bor HER, ikke i chat-historikken: hver runde krysser av
det den fullfører og skriver et kort notat bak oppgaven.

## Slik startes loopen

Kjør i Claude Code i dette repoet (branch `utvikling`, ikke main):

```
/loop 15m Les BACKLOG.md og ta den øverste uavkryssede oppgaven.
Implementer den etter reglene i CLAUDE.md, verifiser mot oppgavens
«Ferdig når», commit med beskrivende melding, og kryss av oppgaven
med ett kort notat og commit-hash. Én oppgave per runde. Er noe
uklart, eller krever oppgaven et veivalg utenfor planen: skriv
spørsmålet i AVKLARINGER.md, merk oppgaven [BLOKKERT], og ta neste
oppgave i stedet for å gjette.
```

## Regler for hver runde

1. Én oppgave per runde - ferdig verifisert og committet, eller
   merket [BLOKKERT] med spørsmål i AVKLARINGER.md.
2. Oppgavene tas ovenfra og ned, med mindre en er [BLOKKERT].
3. «Ferdig når» er kontrakten - ikke kryss av uten at den er innfridd.
4. Aldri push til main. Aldri endre slug, tekstdomene eller lisensvalg
   (det er fase 0-beslutninger som tas av mennesker).
5. Følg CLAUDE.md: ingen Lius-hardkoding, temaets design som standard,
   ingen betal-avhengigheter, all brukertekst via `__()`/`_e()` med
   tekstdomene `samlab`, capability-sjekk + nonce på alt, escaping ved
   output, ` - ` i stedet for tankestrek.

---

## Fase A: Fundament og verktøy

*Verifiseringsgrunnlaget bygges først - uten dette kan ingen senere
oppgave innfri sin «Ferdig når».*

- [x] **A1. Plugin-skjelett.** Opprett `samlab/samlab.php` med
  plugin-header (navn, versjon 0.1.0, tekstdomene `samlab`,
  lisensfelt TODO-merket), konstanter (versjon, sti, URL) og en tom
  `includes/`-struktur som i planens kapittel 3.
  *Ferdig når:* `php -l` er grønn på alle filer, og pluginen kan
  aktiveres i wp-env uten feil eller output.
  *Notat (2026-08-29):* Committet i eae15e1; `php -l` grønn og null
  output ved direkte kall. Aktivering verifisert i testriggen
  (`bin/testrigg.sh`, WordPress 7.1 + SQLite): aktiv uten feil,
  tom debug.log. Lisens satt til GPL-2.0-or-later i 9d6034d
  (avklaring 2). wp-env-krysstest gjøres lokalt ved anledning.
- [ ] **A2. wp-env.** [BLOKKERT] Legg til `.wp-env.json` med nyeste stabile
  WordPress, PHP 8.2, pluginen montert, og standardtemaet
  Twenty Twenty-Four. Dokumenter `npx wp-env start` i README.
  *Ferdig når:* `npx wp-env start` gir en kjørende side der Samlab
  kan aktiveres i wp-admin.
  *Notat (2026-08-29):* `.wp-env.json` og README med
  wp-env-oppskrift committet; JSON validert. Kjøretesten
  (`npx wp-env start`) må kjøres lokalt av et menneske - loopens
  kjøremiljø bruker i stedet testriggen (`bin/testrigg.sh`,
  se AVKLARINGER.md punkt 1). Kun selve wp-env-starten gjenstår.
- [x] **A3. Kodestandard.** Sett opp PHP_CodeSniffer med
  WordPress-Coding-Standards i composer (dev-avhengighet er lov -
  regelen gjelder betal-plugins i runtime), med `composer lint` og
  `composer lint:fix`.
  *Ferdig når:* `composer lint` kjører grønt på A1-skjelettet.
  *Notat (2026-08-29):* composer.json med WPCS 3.x,
  PHPCompatibilityWP og skriptene lint/lint:fix; phpcs.xml.dist med
  tekstdomene- og prefiks-regler. `composer lint` grønn på alle
  7 filer. Lisensfelt utelatt fra composer.json (fase 0-beslutning).
- [x] **A4. CI.** GitHub Action som kjører `php -l` og `composer lint`
  på push og PR.
  *Ferdig når:* workflow-filen er committet og syntaktisk gyldig
  (`act`-kjøring eller YAML-validering).
  *Notat (2026-08-29):* `.github/workflows/ci.yml` med php -l over
  `samlab/` og `composer lint` på push og PR (PHP 8.2). YAML
  validert og begge stegene kjørt grønt lokalt før push.
- [x] **A5. AVKLARINGER.md.** Opprett filen med en kort forklaring og
  tom liste.
  *Ferdig når:* filen finnes og er lenket fra README.
  *Notat (2026-08-29):* Filen fantes fra handover-committen og er
  allerede i bruk (3 punkter, 2 avgjort). README-omtalen gjort om
  til ekte markdown-lenke.

## Fase B: Plugin-kjerne

- [x] **B1. Aktivering/deaktivering.** Aktiveringshook som registrerer
  rewrite-regler og flusher én gang; deaktivering rydder.
  *Ferdig når:* aktivering/deaktivering i wp-env gir ingen feil i
  debug.log.
  *Notat (2026-08-29):* `samlab_activate()` (rewrites når B7 finnes,
  flush, versjons-option) og `samlab_deactivate()` (flush) i
  samlab.php per planens bootstrap-rolle. To fulle sykluser i
  testriggen: tom debug.log. WPCS grønn.
- [x] **B2. Roller og capabilities.** Roller for medlem,
  bedriftsredaktør og moderator med capabilities per planens
  kapittel 3.2, lagt til ved aktivering og fjernet ved avinstallering
  (`uninstall.php`).
  *Ferdig når:* rollene vises i wp-admin, og en testbruker per rolle
  kan opprettes via wp-cli uten feil.
  *Notat (2026-08-29):* includes/roles.php med samlab_member,
  samlab_company_editor, samlab_moderator og samlab_*-caps;
  admin/redaktør får alle. uninstall.php fjerner roller, caps og
  options (innhold beholdes). Verifisert i riggen: roller listet,
  én testbruker per rolle via wp-cli, caps riktige, uninstall
  rydder, tom debug.log. WPCS grønn.
- [x] **B3. CPT: bedrift.** `samlab_bedrift` med taksonomi
  `samlab_kategori`, fremhevet bilde, og metabokser for kort
  beskrivelse, plass, nettside, kontaktperson (brukervelger),
  tjenester og intensjonsfeltene («leverer», «kjøper», «trenger nå»,
  «ideelle kunder», «åpne for»). Egne metabokser, ikke ACF.
  *Ferdig når:* en bedrift kan opprettes komplett i wp-admin, alle
  felter lagres og escapes korrekt, WPCS grønn.
  *Notat (2026-08-29):* includes/post-types.php: CPT + taksonomi
  (ikke-offentlig, thumbnail-støtte), tre metabokser med alle
  felter, tjenester som vanilla-JS-repeater, nonce + capability +
  feltvis sanitering i save-handleren. 18 røyk-tester grønne i
  riggen (tests/rigg/test-b3.php): XSS strippet, javascript:-URL
  avvist, ugyldig nonce og manglende rettigheter avvist. WPCS grønn.
- [x] **B4. Bedriftsredaktør-avgrensning.** `map_meta_cap` slik at
  bedriftsredaktører kun kan redigere bedriften der de er
  kontaktperson.
  *Ferdig når:* wp-cli-test viser at redaktør A ikke kan redigere
  bedrift B (capability-sjekk, ikke bare skjult UI).
  *Notat (2026-08-29):* samlab_map_bedrift_caps i post-types.php:
  admin/redaktør beholder standardmapping, kontaktperson mappes til
  samlab_edit_bedrift, alle andre do_not_allow; sletting kun
  admin/redaktør. 13 tester grønne i riggen (tests/rigg/test-b4.php),
  inkl. at meta-lagring avvises på annens bedrift. WPCS grønn.
- [ ] **B5. CPT: behov.** `samlab_behov` med taksonomier for
  trenger/tilbyr og behovstype, meta for frist, budsjett, kompetanse
  og kontaktform, kobling til bedrift.
  *Ferdig når:* som B3, for behov.
- [ ] **B6. Egne tabeller: vegg og reaksjoner.** dbDelta-skjema for
  vegginnlegg og reaksjoner (hybridmodellen fra
  FluentCommunity-analysen) med modellklasser for CRUD.
  *Ferdig når:* tabellene opprettes ved aktivering, CRUD-metodene har
  enkle wp-cli-røyk-tester, prepared statements overalt.
- [ ] **B7. Portal-ruter og app-skall.** `add_rewrite_rule` for
  portal-stien (innstilling, standard `/portal/`) med eget komplett
  sideskall (egen `<html>`, ikke temaets template), noindex-meta, og
  ruting til undersider (vegg, behov, bedrifter, håndbok).
  *Ferdig når:* alle portal-ruter svarer 200 i wp-env med app-skallet,
  og resten av nettstedet bruker temaet som før.
- [ ] **B8. Innloggingsport.** `template_redirect`-sjekk: uinnloggede
  på portal-ruter sendes til wp-login med redirect tilbake.
  *Ferdig når:* utlogget curl mot portal-rute gir redirect til
  innlogging; innlogget bruker ser siden.
- [ ] **B9. Token-CSS.** Portalens stilark bygget på
  `--wp--preset--*`-variabler med nøytrale fallbacks, portert
  strukturelt fra `prototype-kilde/styles/global.css` (kort, chips,
  avatarer, statuskjede - fargene fra temaet).
  *Ferdig når:* portalen skifter farger/fonter når temaet byttes
  mellom Twenty Twenty-Four og ett annet theme.json-tema i wp-env.
- [ ] **B10. Innstillingsside.** wp-admin-side for portalnavn,
  portal-sti, flatenavn (vegg/behov/håndbok), valgfri
  aksentfarge-overstyring og logo.
  *Ferdig når:* endring av portal-sti og flatenavn slår gjennom på
  frontend uten manuell flush.
- [ ] **B11. REST-navnerom.** `samlab/v1` registrert med første
  endepunkt (`/reaksjoner`, POST med cookie+nonce og
  capability-sjekk), og en `docs/hooks.md` som starter
  dokumentasjonen av actions/filters.
  *Ferdig når:* endepunktet fungerer via nettleser-konsollen i
  wp-env, avviser uinnloggede, WPCS grønn.

## Fase C: Kjerneflatene

*Porter markup fra `prototype-kilde/` og `referanse/prototype-demo.html`
- strukturen er fasit, fargene kommer fra temaet.*

- [ ] **C1. Bedriftskatalogen.** Portal-siden med kort-grid, kategori-
  chips og søk (WP_Query-basert).
  *Ferdig når:* katalogen viser seed-bedrifter (se C6) korrekt i
  wp-env og matcher prototypens struktur.
- [ ] **C2. Bedriftsprofilen.** Full profilside: logo, intensjonene
  («Dette ser vi etter»), tjenester, folkene, galleri, aktive behov,
  kontaktperson.
  *Ferdig når:* profilen viser alle felter fra B3/B5-data og
  ingenting hardkodet Lius.
- [ ] **C3. Behov & tilbud.** Kortene med trenger/tilbyr-merker,
  filtre og «nytt behov»-skjema (frontend-innsending med nonce).
  *Ferdig når:* et medlem kan opprette et behov fra portalen, og det
  vises korrekt med alle metafelter.
- [ ] **C4. Veggen.** Feed fra B6-tabellene: innlegg med tekst/bilde,
  reaksjoner via REST, WordPress-kommentarer, festede oppslag med
  hel ramme (kun moderator+ kan feste).
  *Ferdig når:* innlegg, reaksjon og kommentar fungerer ende til ende
  i wp-env for en medlem-bruker.
- [ ] **C5. Håndboken.** Sidegruppe under portalen (vanlige
  WordPress-sider merket som portal-innhold) med ankernavigasjon og
  FAQ-blokk (details/summary-mønsteret fra prototypen).
  *Ferdig når:* en håndbok-side opprettet i Gutenberg vises i
  portal-skallet med navigasjon.
- [ ] **C6. Seed-kommando.** `wp samlab seed` med nøytrale
  demobedrifter, behov, vegginnlegg og en håndbok-side (mal:
  `prototype-kilde/data/intern.ts`, men uten Lius-navn).
  *Ferdig når:* kommandoen fyller en tom installasjon slik at C1-C5
  kan demonstreres umiddelbart; `wp samlab seed --slett` rydder.
- [ ] **C7. Mentions og globalt søk.** @navn-forslag i vegg-innlegg og
  et søk som dekker bedrifter, behov og håndbok (fra
  FluentCommunity-analysens MVP-liste).
  *Ferdig når:* mention lagres og rendres som lenke; søket gir treff
  på seed-data fra alle tre innholdstypene.

## Fase D: Kvalitet og MVP-lukking

- [ ] **D1. Temakompatibilitet.** Full gjennomgang mot to temaer
  (Twenty Twenty-Four + ett klassisk tema uten theme.json) med
  skjermbilder i `docs/tema-test.md`.
  *Ferdig når:* ingen uleselige kontraster eller ødelagt layout i
  noen av temaene; avvik er rettet.
- [ ] **D2. i18n-gjennomgang.** Alle brukertekster gjennom
  `__()`/`_e()`, POT-fil generert.
  *Ferdig når:* `wp i18n make-pot` kjører rent og stikkprøver i koden
  finner ingen hardkodede strenger.
- [ ] **D3. Sikkerhetsgjennomgang.** Systematisk sjekk av alle
  endepunkter og skjemaer: nonce, capabilities, escaping, prepared
  statements. Funn rettes; oppsummering i `docs/sikkerhet.md`.
  *Ferdig når:* dokumentet lister hver flate med bekreftet status.
- [ ] **D4. Driftskrav-dokumentasjon.** `README.md` med krav
  (WordPress/PHP-versjon, cron), installasjon, wp-env-oppskrift og
  lenke til hooks-dokumentasjonen (Infohub-analysens
  driftsdokumentasjons-lærdom).
  *Ferdig når:* en utvikler som ikke kjenner prosjektet kan gå fra
  klone til kjørende portal med seed-data kun via README.

**Milepæl: MVP.** Når D4 er krysset av er fase 1-2 fra planen levert
og Lius-piloten kan settes opp. Fase 3-4 (kontrollpanel, matching,
varsler, lesebekreftelser, infoskjerm, assistent) planlegges i en
interaktiv økt sammen med erfaringene fra AVKLARINGER.md - de skal
ikke inn i denne backloggen før mennesker har prioritert dem.
