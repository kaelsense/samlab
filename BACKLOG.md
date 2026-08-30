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
- [x] **B5. CPT: behov.** `samlab_behov` med taksonomier for
  trenger/tilbyr og behovstype, meta for frist, budsjett, kompetanse
  og kontaktform, kobling til bedrift.
  *Ferdig når:* som B3, for behov.
  *Notat (2026-08-29):* samlab_behov i post-types.php med
  samlab_retning (termene trenger/tilbyr seedes ved aktivering) og
  samlab_behovstype; metaboks med frist, budsjett, kontaktform,
  kompetanse-liste og bedriftsvelger. Kobling valideres mot
  post-type (ikke-bedrift nulles). 17 tester grønne i riggen
  (tests/rigg/test-b5.php), tom debug.log, WPCS grønn.
- [x] **B6. Egne tabeller: vegg og reaksjoner.** dbDelta-skjema for
  vegginnlegg og reaksjoner (hybridmodellen fra
  FluentCommunity-analysen) med modellklasser for CRUD.
  *Ferdig når:* tabellene opprettes ved aktivering, CRUD-metodene har
  enkle wp-cli-røyk-tester, prepared statements overalt.
  *Notat (2026-08-29):* includes/database.php (dbDelta, versjonert
  med samlab_db_version + admin_init-oppgradering) og klassene
  Samlab_Innlegg/Samlab_Reaksjon med prepared statements overalt
  (wpdb::insert/update/delete + prepare på alle SELECT-er).
  wp_kses_post på innhold, festet-først-sortering, idempotente
  reaksjoner med unik-indeks, kaskadesletting. 26 røyk-tester
  grønne i riggen (tests/rigg/test-b6.php), tom debug.log,
  WPCS grønn.
- [x] **B7. Portal-ruter og app-skall.** `add_rewrite_rule` for
  portal-stien (innstilling, standard `/portal/`) med eget komplett
  sideskall (egen `<html>`, ikke temaets template), noindex-meta, og
  ruting til undersider (vegg, behov, bedrifter, håndbok).
  *Ferdig når:* alle portal-ruter svarer 200 i wp-env med app-skallet,
  og resten av nettstedet bruker temaet som før.
  *Notat (2026-08-29):* includes/rewrites.php (regler, query-vars,
  pre_handle_404, ruting) + templates/skall.php og flater/-plasshol-
  dere. Portal-sti, portalnavn og flatenavn leses fra
  samlab_settings med nøytrale standarder. HTTP-verifisert i riggen
  (php -S + router): alle 5 ruter 200 med eget skall og noindex,
  ukjent rute 404, tema uberørt på resten, og sti/navn-innstilling
  bekreftet med /intern/-test. WPCS grønn.
- [x] **B8. Innloggingsport.** `template_redirect`-sjekk: uinnloggede
  på portal-ruter sendes til wp-login med redirect tilbake.
  *Ferdig når:* utlogget curl mot portal-rute gir redirect til
  innlogging; innlogget bruker ser siden.
  *Notat (2026-08-29):* includes/access.php med
  samlab_portal_login_gate på template_redirect prioritet 9 (før
  rutingen), wp_safe_redirect til wp_login_url med retur-URL fra
  $wp->request. HTTP-verifisert: utlogget 302 med korrekt
  redirect_to, innlogget (logged_in-cookie) 200 med skallet,
  resten av nettstedet åpent. WPCS grønn.
- [x] **B9. Token-CSS.** Portalens stilark bygget på
  `--wp--preset--*`-variabler med nøytrale fallbacks, portert
  strukturelt fra `prototype-kilde/styles/global.css` (kort, chips,
  avatarer, statuskjede - fargene fra temaet).
  *Ferdig når:* portalen skifter farger/fonter når temaet byttes
  mellom Twenty Twenty-Four og ett annet theme.json-tema i wp-env.
  *Notat (2026-08-29):* assets/css/portal.css med samlab-tokens på
  preset-variabler + struktur (topp/nav, kort, chips, avatar,
  statuskjede, knapper, festet-ramme). Skallet skriver ut temaets
  variabler (wp_get_global_stylesheet) + bro fra wp_get_global_styles
  for font/farge uansett preset-slugs, og aksent-overstyring fra
  innstilling (sanitize_hex_color). Verifisert i riggen: TT24 vs
  TT25 gir ulike fonter (body/heading vs manrope) og farger
  (#cfcabe vs #FFEE58). WPCS grønn.
- [x] **B10. Innstillingsside.** wp-admin-side for portalnavn,
  portal-sti, flatenavn (vegg/behov/håndbok), valgfri
  aksentfarge-overstyring og logo.
  *Ferdig når:* endring av portal-sti og flatenavn slår gjennom på
  frontend uten manuell flush.
  *Notat (2026-08-29):* admin/settings.php med Settings API
  (manage_options), feltvis sanitering (sanitize_title/hex_color/
  esc_url_raw) og auto-flush på update_option når sti/slugs endres.
  Logo vises i skallet. HTTP-verifisert i riggen: siden 200 i
  wp-admin, XSS/ugyldig farge/js-URL forkastes, ny sti+slug svarer
  200 uten manuell flush og gammel gir 404. WPCS grønn.
- [x] **B11. REST-navnerom.** `samlab/v1` registrert med første
  endepunkt (`/reaksjoner`, POST med cookie+nonce og
  capability-sjekk), og en `docs/hooks.md` som starter
  dokumentasjonen av actions/filters.
  *Ferdig når:* endepunktet fungerer via nettleser-konsollen i
  wp-env, avviser uinnloggede, WPCS grønn.
  *Notat (2026-08-29):* includes/rest-api.php: POST /reaksjoner som
  toggler via Samlab_Reaksjon, med permission_callback
  (innlogget + samlab_read_portal), objektvalidering og
  samlab_reaksjon_endret-action. docs/hooks.md startet med
  navnekonvensjon, endepunkt- og hook-dokumentasjon.
  HTTP-verifisert i riggen med ekte cookie+nonce-flyt: 401
  utlogget/uten nonce, toggle på/av med counts, 404 ukjent objekt,
  403 uten capability. WPCS grønn (custom caps registrert i
  phpcs-regelsettet).

## Fase C: Kjerneflatene

*Porter markup fra `prototype-kilde/` og `referanse/prototype-demo.html`
- strukturen er fasit, fargene kommer fra temaet.*

- [x] **C1. Bedriftskatalogen.** Portal-siden med kort-grid, kategori-
  chips og søk (WP_Query-basert).
  *Ferdig når:* katalogen viser seed-bedrifter (se C6) korrekt i
  wp-env og matcher prototypens struktur.
  *Notat (2026-08-29):* templates/flater/bedrifter.php: WP_Query
  med søk (?sok=) og kategorifilter (?kategori=), chips fra
  taksonomien med «Alle», kort med logo/initial-avatar, kategori,
  kort tekst og plass, lenket til C2-profilen. HTTP-verifisert i
  riggen med tre manuelle testbedrifter: visning, filter, søk,
  tom-tilstand. Kjøres på nytt mot ekte seed-data i C6-runden.
  WPCS grønn.
- [x] **C2. Bedriftsprofilen.** Full profilside: logo, intensjonene
  («Dette ser vi etter»), tjenester, folkene, galleri, aktive behov,
  kontaktperson.
  *Ferdig når:* profilen viser alle felter fra B3/B5-data og
  ingenting hardkodet Lius.
  *Notat (2026-08-29):* flater/bedrift-profil.php rutes fra
  bedrifter.php på undersides-slug; ukjent slug gir ekte 404 fra
  rutingen. Hode med logo/avatar, om-tekst (the_content-filteret),
  intensjons-dl med «Åpne for»-chips, tjenestekort, kontaktperson
  under Folkene, galleri fra vedlagte bilder, aktive behov via
  bedriftskoblingen. HTTP-verifisert i riggen med fullt utfylt
  testbedrift: alle seksjoner, 404 for ukjent slug, null
  Lius-forekomster. Galleri-seksjonen er betinget og verifiseres
  med bilder i C6-runden. WPCS grønn.
- [x] **C3. Behov & tilbud.** Kortene med trenger/tilbyr-merker,
  filtre og «nytt behov»-skjema (frontend-innsending med nonce).
  *Ferdig når:* et medlem kan opprette et behov fra portalen, og det
  vises korrekt med alle metafelter.
  *Notat (2026-08-29):* flater/behov.php (kort med retning/type-
  merker, kompetanse-chips, metalinje, bedriftslenke; filtre for
  retning og behovstype; skjema kun for samlab_create_behov) +
  includes/forms.php (post/redirect/get på template_redirect,
  nonce + capability, feltvis sanitering, bedriftskobling begrenset
  til bedrifter brukeren er kontaktperson for,
  samlab_behov_opprettet-action - dokumentert i docs/hooks.md).
  HTTP-verifisert som medlem i riggen: opprettelse ende til ende
  med alle felter, XSS strippet, filtre riktige, 403 ved ugyldig
  nonce og for abonnent, feilmelding uten tittel. WPCS grønn.
- [x] **C4. Veggen.** Feed fra B6-tabellene: innlegg med tekst/bilde,
  reaksjoner via REST, WordPress-kommentarer, festede oppslag med
  hel ramme (kun moderator+ kan feste).
  *Ferdig når:* innlegg, reaksjon og kommentar fungerer ende til ende
  i wp-env for en medlem-bruker.
  *Notat (2026-08-29):* flater/vegg.php (feed fra Samlab_Innlegg,
  like-knapp mot samlab/v1/reaksjoner med vanilla JS og
  X-WP-Nonce fra skallet, kommentarer som WP-kommentarer av type
  samlab_innlegg via comment-meta, festet-ramme og -merke) +
  handlere i forms.php for innlegg (tekst + bildeopplasting),
  kommentar og moderering (fest/løsne/skjul med
  samlab_pin_posts/samlab_hide_content). Ny action
  samlab_innlegg_opprettet dokumentert. HTTP-verifisert som medlem:
  innlegg med og uten bilde, XSS strippet, reaksjon telte og vistes,
  kommentar synlig, 403 for medlems festeforsøk, moderator festet
  med hel ramme. WPCS grønn.
- [x] **C5. Håndboken.** Sidegruppe under portalen (vanlige
  WordPress-sider merket som portal-innhold) med ankernavigasjon og
  FAQ-blokk (details/summary-mønsteret fra prototypen).
  *Ferdig når:* en håndbok-side opprettet i Gutenberg vises i
  portal-skallet med navigasjon.
  *Notat (2026-08-29):* Metaboks «Vis i portalens håndbok» på
  sider (_samlab_handbok, rekkefølge via menu_order) +
  flater/handbok.php med sidenav, ankernavigasjon (h2-er får
  id-er automatisk) og details/summary-styling for Gutenbergs
  details-blokk som FAQ. Kun merkede sider nås via håndboken;
  umerkede og ukjente slugs gir 404 fra rutingen. HTTP-verifisert
  i riggen med to Gutenberg-sider inkl. details-blokk. WPCS grønn.
- [x] **C6. Seed-kommando.** `wp samlab seed` med nøytrale
  demobedrifter, behov, vegginnlegg og en håndbok-side (mal:
  `prototype-kilde/data/intern.ts`, men uten Lius-navn).
  *Ferdig når:* kommandoen fyller en tom installasjon slik at C1-C5
  kan demonstreres umiddelbart; `wp samlab seed --slett` rydder.
  *Notat (2026-08-29):* includes/class-samlab-cli-command.php:
  4 fiktive bedrifter med GD-genererte logoer og galleri, 4
  demo-brukere, 3+3 termer, 5 behov, 4 vegginnlegg (ett festet,
  reaksjoner + kommentar) og 2 håndbok-sider med FAQ. Alt merket
  _samlab_seed; --slett fjerner alt sporløst, dobbel seed avvises.
  Verifisert i riggen: C1 (katalog med logoer), C2 (profil med
  galleri), C3, C4 (festet + kommentar), C5 vises korrekt fra
  seed-data; slett ga 0 rester. WPCS grønn.
- [x] **C7. Mentions og globalt søk.** @navn-forslag i vegg-innlegg og
  et søk som dekker bedrifter, behov og håndbok (fra
  FluentCommunity-analysens MVP-liste).
  *Ferdig når:* mention lagres og rendres som lenke; søket gir treff
  på seed-data fra alle tre innholdstypene.
  *Notat (2026-08-29):* includes/search.php (samlab_global_search
  over tre innholdstyper, mention-rendring som lenker til
  bedriftsprofil eller søk) + GET samlab/v1/brukere for
  @-forslag (dokumentert i docs/hooks.md) + vanilla JS-autocomplete
  i vegg-skjemaet + søkefelt i skallet med resultater gruppert på
  hjem-flaten. HTTP-verifisert mot seed-data: mention @kari.demo
  rendret som lenke til bedriftsprofilen, forslag-endepunktet
  auth-beskyttet (401 utlogget), og søk traff bedrifter
  (regnskap), behov (fotograf) og håndbok (møterom). WPCS grønn.

## Fase D: Kvalitet og MVP-lukking

- [x] **D1. Temakompatibilitet.** Full gjennomgang mot to temaer
  (Twenty Twenty-Four + ett klassisk tema uten theme.json) med
  skjermbilder i `docs/tema-test.md`.
  *Ferdig når:* ingen uleselige kontraster eller ødelagt layout i
  noen av temaene; avvik er rettet.
  *Notat (2026-08-29):* Testet TT24, Twenty Twenty (klassisk) og
  TT25 med Chromium-skjermbilder (15 stk i docs/tema-test/) og
  målte kontraster via getComputedStyle. Tre avvik funnet og
  rettet: luminansbasert aksent-kontrast (samlab_portal_accent),
  nytt --samlab-aksent-tekst-token for aksent som ren tekst, og
  stilrekkefølgen i skallet (portal.css lenkes før
  overstyringene). Alle kontraster nå over WCAG AA; rapport i
  docs/tema-test.md. WPCS grønn.
- [x] **D2. i18n-gjennomgang.** Alle brukertekster gjennom
  `__()`/`_e()`, POT-fil generert.
  *Ferdig når:* `wp i18n make-pot` kjører rent og stikkprøver i koden
  finner ingen hardkodede strenger.
  *Notat (2026-08-29):* `wp i18n make-pot` kjørte rent uten
  advarsler - 136 strenger i samlab/languages/samlab.pot med
  riktig tekstdomene. Stikkprøver (grep etter norsk tekst utenfor
  oversettelsesfunksjoner, placeholders/labels uten esc_attr_e):
  null funn; eneste rå echo-er er ren markup. Bevisst unntak:
  wp-cli-meldingene i seed-kommandoen (utviklerflate) og
  seed-demoinnholdet (data, ikke UI). WPCS grønn.
- [x] **D3. Sikkerhetsgjennomgang.** Systematisk sjekk av alle
  endepunkter og skjemaer: nonce, capabilities, escaping, prepared
  statements. Funn rettes; oppsummering i `docs/sikkerhet.md`.
  *Ferdig når:* dokumentet lister hver flate med bekreftet status.
  *Notat (2026-08-29):* Adversariell gjennomgang av hele pluginen
  med uavhengig verifisering per funn. Ingen høyalvorlige funn;
  tre rettet: håndbok-sider var offentlige utenfor portalen
  (301 til portalruten + ute av sitemap/søk/anonym REST -
  HTTP-verifisert), brukeroppramsing i /brukere (capability-
  avgrenset), uescaped behovstittel (esc_html). Uploads-
  offentlighet og navn-i-meny dokumentert som akseptert restrisiko.
  docs/sikkerhet.md lister alle 17 flater med bekreftet status.
  WPCS grønn.
- [x] **D4. Driftskrav-dokumentasjon.** `README.md` med krav
  (WordPress/PHP-versjon, cron), installasjon, wp-env-oppskrift og
  lenke til hooks-dokumentasjonen (Infohub-analysens
  driftsdokumentasjons-lærdom).
  *Ferdig når:* en utvikler som ikke kjenner prosjektet kan gå fra
  klone til kjørende portal med seed-data kun via README.
  *Notat (2026-08-29):* README omskrevet: krav (WP 6.4+/PHP 8.2+,
  cron-notat), produksjonsinstallasjon steg for steg, klone-til-
  portal via bin/testrigg.sh (som nå også setter permalenker,
  router og låst site-URL og skriver neste-steg til terminalen),
  wp-env-alternativ, verifisering (lint + røyk-tester) og lenker
  til hooks/sikkerhet/tema-test. Løypa verifisert ende til ende i
  helt fersk rigg: klone → script → seed → server → portal 200 med
  demodata, håndbok-vern aktivt. MVP-MILEPÆLEN ER NÅDD.

**Milepæl: MVP - NÅDD 2026-08-29.** Fase 1-2 fra planen er levert
(PR #1, merget til main) og Lius-piloten kan settes opp. Fase 3-4
under ble planlagt interaktivt med Kay 2026-08-29: fullt fase
3-scope inkludert alle analysenes tillegg, hele assistentmodulen,
samme loop-regime som MVP-en.

## Fase E: Fasilitering (planens fase 3)

*Kjernen fra planens kap. 3.4 pluss tilleggene fra FluentCommunity-,
Infohub- og B2B-analysene. Rekkefølgen er avhengighetsstyrt:
koblinger og varsler først (kontrollpanelet og matchingen bygger på
dem). Verifisering skjer i testriggen som før; cron-jobber testes
med `wp cron event run`.*

- [x] **E1. CPT: kobling/introduksjon.** `samlab_kobling` (ikke
  offentlig, ingen egen portalflate): to parter (bedrift og/eller
  bruker), begrunnelse, kilde (manuell/matching) og statuskjeden som
  meta (foreslått → godkjent → introdusert → fulgt opp). Synlig kun
  for moderator+ og partene selv (capability-håndhevet, som B4).
  *Ferdig når:* koblinger kan opprettes og flyttes gjennom
  statuskjeden via wp-admin/wp-cli, og en part kan lese men ikke
  endre andres koblinger - verifisert med røyk-test i riggen.
  *Notat (2026-08-29):* includes/koblinger.php: CPT med egne
  capability-primitiver (edit_samlab_koblinger m.fl. til
  moderator+/admin via roles.php - aldri vanlige post-caps),
  statuskjede med avvist-terminal, statuslogg og
  samlab_kobling_status_endret-action (dokumentert), part-helpere
  med validering, metaboks med status/kilde/parter, og
  map_meta_cap-lesetilgang for parter (bruker direkte eller
  kontaktperson for part-bedrift). 27 røyk-tester grønne i riggen
  (tests/rigg/test-e1.php) etter reaktivering, tom debug.log,
  sikkerhetstabellen oppdatert. WPCS grønn.
- [x] **E2. In-app-varsler.** Egen tabell (hybridmodellen) med
  modellklasse, varsler ved mention, kommentar/reaksjon på eget
  innlegg, svar på eget behov og kobling der en er part.
  REST: `GET samlab/v1/varsler` + markér-som-lest. Bjelle med
  uleste-teller i skallet, enkel varselliste.
  *Ferdig når:* hver utløser gir varsel i riggen, lest-markering
  virker, og andres varsler er utilgjengelige (403). WPCS grønn.
  *Notat (2026-08-29):* samlab_varsler-tabell (DB v2 med
  oppgraderingssti), Samlab_Varsel (dedup mot uleste, aldri
  selv-varsel, kaskade fra innlegg- og koblingssletting) og
  includes/varsler.php med utløsere (mention, kommentar, reaksjon,
  koblingsstatus godkjent+ til partene), tekst/lenke-rendring og
  REST-rutene (dokumentert). Bjelle med teller og panel i skallet.
  Behov-svar-utløseren aktiveres når svar-funksjon finnes (ingen i
  MVP). 17 modell-tester + HTTP-verifisering (401 utlogget,
  isolasjon per bruker - egne varsler hentes fra sesjonen så
  andres er per konstruksjon utilgjengelige, lest-markering,
  teller i skallet). Tom debug.log, WPCS grønn, sikkerhetstabellen
  oppdatert.
- [x] **E3. Kontrollpanelet.** wp-admin-side for community-manageren
  (planens kap. 3.4): koblingskø med godkjenn/avvis og statuskjede,
  og «trenger oppmerksomhet»-listene (nye medlemmer uten
  introduksjon, ubesvarte behov eldre enn X dager, ufullstendige
  bedriftsprofiler, stille medlemmer).
  *Ferdig når:* alle listene viser riktige treff mot seed-data
  pluss konstruerte kanttilfeller, og godkjenning av en foreslått
  kobling utløser varsel til partene.
  *Notat (2026-08-29):* admin/kontrollpanel.php: menyside bak
  edit_samlab_koblinger med koblingskø (godkjenn/avvis), aktive
  koblinger med neste-steg-knapper, og de fire listene (nye uten
  kobling siste 30 d, behov eldre enn 14 d, profiler med manglende
  felter/logo, medlemmer uten aktivitet i 30 d - nyregistrerte
  unntatt). Handlinger via admin-post med nonce; utfør-funksjonen
  returnerer WP_Error og er testbar. 23 tester grønne mot seed +
  konstruerte kanttilfeller, inkl. at godkjenning varslet begge
  parter (E2-integrasjonen). HTTP: moderator 200 med alle
  seksjoner, medlem 403. Tom debug.log, WPCS grønn,
  sikkerhetstabellen oppdatert.
- [x] **E4. Regelbasert matching.** Cron-jobb (`wp_schedule_event`,
  daglig) som matcher åpne behov mot bedriftenes intensjonsfelter
  og kompetanse/tjenester (tekstlig overlapp, terskel), og
  oppretter foreslåtte koblinger i kontrollpanelet - aldri
  automatiske introduksjoner. Dedupliserer mot eksisterende
  koblinger. (LLM-assistert scoring er en senere utvidelse via
  assistentens integrasjon - egen oppgave når F-fasen er levert.)
  *Ferdig når:* kjøring mot seed-data gir forutsigbare forslag
  (dokumentert i testen), ingen duplikater ved gjentatt kjøring.
  *Notat (2026-08-29):* includes/matching.php: lett norsk stemming
  med stoppord, behovskorpus (tittel+kompetanse) mot
  bedriftskorpus per retning (trenger→leverer/kort/tjenester,
  tilbyr→kjøper/trenger nå), terskel 2 felles stammer, hopper over
  egen bedrift og samme kontaktperson. Forslag med kilde matching
  og begrunnelse med felles nøkkelord i kontrollpanelets kø; dedup
  via match-meta med post_status any så avviste aldri gjenoppstår.
  Cron planlegges ved aktivering og ryddes ved deaktivering; `wp
  samlab match` for manuell kjøring. Deterministisk fasit mot seed
  dokumentert i tests/rigg/test-e4.php (nøyaktig ett forslag:
  Tallknuserne-behovet ↔ Brygga Design på nettsid+design) - 17
  tester grønne inkl. idempotens og selv-match-eksklusjon. Tom
  debug.log, WPCS grønn, hooks- og sikkerhetsdocs oppdatert.
  Action: samlab_matching_kjort.
- [x] **E5. Ukesbrev.** Cron-jobb som sender digest via `wp_mail`:
  nye behov, nye innlegg, kommende arrangementer og nye medlemmer
  siste uke. Innstillinger: av/på, ukedag, avsendernavn. Medlemmer
  kan reservere seg (profilinnstilling). E-posten er ren tekst/enkel
  HTML uten temaavhengighet.
  *Ferdig når:* generert e-post inneholder riktig innhold mot
  seed-data (fanget med mail-mock i riggen), reservasjon
  respekteres, og jobben planlegges/avplanlegges ved
  aktivering/deaktivering.
  *Notat (2026-08-29):* includes/ukesbrev.php: ren tekst-digest
  (nye behov, nytt på veggen, nye medlemmer) med portallenker og
  reservasjonsforklaring; daglig cron samlab_ukesbrev som selv
  sjekker ukedag + minst 6 dager siden sist (robust mot hoppede
  dager), tomt brev sendes ikke. Innstillinger med nye felttyper
  (avkryssing, ukedag-nedtrekk) + avsendernavn via
  wp_mail_from_name kun under utsending. Reservasjon som
  profil-avkryssing (kjernens nonce + edit_user). Kommende
  arrangementer kobles på av E6 via nytt filter
  samlab_ukesbrev_seksjoner; action samlab_ukesbrev_sendt. `wp
  samlab ukesbrev [--vis]`. 22 tester grønne med
  pre_wp_mail-mock (innhold mot seed, reservasjon, alle
  tick-vakter, av-/planlegging ved deaktivering/aktivering).
  Funn rettet i samme runde: eval-file kjører testfilene i
  funksjons-scope, så exit-koden var alltid 0 - alle riggtester
  binder nå $fail globalt (verifisert med negativ probe). Tom
  debug.log, WPCS grønn, hooks- og sikkerhetsdocs oppdatert.
- [x] **E6. CPT: arrangement.** `samlab_arrangement` med dato/tid,
  sted, arrangør (bedriftskobling valgfri) og beskrivelse; egen
  portalflate (kommende først) som ny standardflate i nav og søk;
  medlemmer med egen capability kan opprette fra portalen (som C3).
  *Ferdig når:* som B5/C3: komplett arrangement fra wp-admin og
  portal, listet riktig, i globalt søk, WPCS grønn.
  *Notat (2026-08-29):* includes/arrangementer.php: CPT med
  metaboks (start/slutt som datetime-local mot strengt lagret
  «Y-m-d H:i»-format, sted, arrangør-bedrift), hjelpere for
  kommende (nærmeste først) og tidligere, tidsvisning med
  intervall. Ny flate arrangementer i nav/hjem/søk (navn og slug
  er innstillinger som de andre flatene), template med kommende-
  kort, tidligere-liste og skjema bak ny cap
  samlab_create_arrangement (alle samlab-roller). Skjema-handler i
  forms.php med arrangør validert mot kontaktperson-eierskap;
  action samlab_arrangement_opprettet. Kommende arrangementer inn
  i ukesbrevet via E5-filteret. 16 tester grønne + HTTP: anonym
  til innlogging, innlogget flate 200 med skjema, POST oppretter
  med riktig meta/forfatter og vises på flate + i globalt søk,
  feil nonce 403. Tom debug.log, WPCS grønn, hooks- og
  sikkerhetsdocs oppdatert.
- [x] **E7. Avstemninger.** Enkel avstemning på vegginnlegg
  (spørsmål + 2-5 alternativer, egen tabell for stemmer, én stemme
  per medlem, endring tillatt), stemming via REST med
  resultatvisning etter avgitt stemme.
  *Ferdig når:* opprette-stemme-endre-flyt virker ende til ende i
  riggen, stemmetallene er riktige, uinnloggede avvises.
  *Notat (2026-08-29):* DB v3: poll-kolonner på innlegg-tabellen +
  ny samlab_stemmer-tabell med UNIQUE(innlegg_id, user_id) - én
  stemme per medlem håndhevet i skjemaet, ny stemme oppdaterer
  raden. Samlab_Stemme (vote/user_choice/counts/kaskade ved
  innleggssletting), avstemningsfelter i Samlab_Innlegg::create
  (2-5 alternativer validert, ellers stille uten avstemning) og i
  veggskjemaet (details-seksjon; ugyldig antall gir feilmelding).
  REST POST /stemmer med samme auth som reaksjoner; action
  samlab_stemme_avgitt. Veggen viser alternativknapper, og tall +
  status først etter avgitt stemme (server-rendret for de som har
  stemt, JS-oppdatert ved stemming/endring). 10 modelltester
  grønne + HTTP ende til ende: skjema oppretter avstemning (og
  avviser 1 alternativ), 401 utlogget, stemme/endring gir riktige
  tall, 400 ugyldig indeks, 404 uten avstemning, veggen viser
  status. Tom debug.log, WPCS grønn, hooks- og sikkerhetsdocs
  oppdatert.
- [x] **E8. Lesebekreftelser.** «Bekreft lest» på festede oppslag
  (moderator velger per innlegg), bekreftelse via REST, og
  moderatoroversikt i kontrollpanelet over hvem som har/ikke har
  bekreftet.
  *Ferdig når:* medlem kan bekrefte én gang, oversikten stemmer,
  kun moderator+ ser den.
  *Notat (2026-08-29):* DB v4: confirm_read-flagg på
  innlegg-tabellen; bekreftelsene bor i reaksjonstabellen med
  reservert nøkkel «lest» (UNIQUE gir én per medlem) - nøkkelen er
  sperret i toggle-endepunktet (400) så bekreftelser aldri kan
  slås av. Moderator setter/fjerner lest-krav fra veggen
  (samlab_pin_posts, kun festede; kravet fjernes ved løsning).
  REST POST /lest er idempotent (allerede-flagg i svaret); action
  samlab_lest_bekreftet fyrer kun første gang. «Bekreft
  lest»-knapp med teller på veggen (deaktivert etter bekreftelse),
  og kontrollpanel-seksjon med «X av N har bekreftet» + navnelister
  (bak edit_samlab_koblinger). 12 tester grønne + HTTP: 404 uten
  krav, moderator setter krav (medlem 403), 401 utlogget, bekreft
  + idempotent gjenkall, toggle-vakt 400, oversikten viser riktige
  navn og medlem får 403 på siden. Tom debug.log, WPCS grønn,
  hooks- og sikkerhetsdocs oppdatert.
- [x] **E9. Infoskjerm.** Read-only rute (`/portal-sti/skjerm/` e.l.)
  med hemmelig nøkkel i URL-en (innstilling, regenererbar) som
  viser festede oppslag, siste vegginnlegg og kommende
  arrangementer i storskjerm-layout med auto-oppdatering.
  Ingen innlogging - nøkkelen er porten; ingen persondata utover
  det veggen viser.
  *Ferdig når:* riktig nøkkel gir 200 med innhold og auto-refresh,
  feil/manglende nøkkel gir 404, og flaten er noindex.
  *Notat (2026-08-29):* includes/skjerm.php: rute
  /portal-sti/skjerm/<nøkkel>/ håndteres FØR innloggingsporten
  (prioritet 7) og svarer selv - riktig nøkkel (hash_equals mot
  24-tegns lagret nøkkel) gir skjermen, alt annet 404, aldri
  videresending til innlogging. Av som standard; generer/regenerer/
  fjern-knapper på innstillingssiden bak manage_options + nonce
  (admin-post), og slug for ruten er innstilling (standard
  «skjerm»). templates/skjerm.php: standalone storskjerm-layout
  (festede oppslag, siste 6 fra veggen, 6 kommende arrangementer)
  med meta-refresh 60 s, noindex som meta + X-Robots-Tag, og
  tokens med nøytrale fallbacks. 6 helpertester grønne + HTTP:
  404 uten/med feil nøkkel, 200 med innhold/refresh/noindex,
  regenerering dreper gammel URL umiddelbart, fjerning slår
  skjermen av, seksjonen vises på innstillingssiden. Restrisikoen
  (nøkkel i URL) dokumentert i sikkerhetsdocs. Tom debug.log,
  WPCS grønn.
- [x] **E10. Seed og dokumentasjon for fase E.** Seed-kommandoen
  utvides med arrangementer, koblinger i ulike statuser, varsler og
  en avstemning; docs/hooks.md og docs/sikkerhet.md-tabellen
  oppdateres med alle nye endepunkter/flater; README nevner
  cron-kravene.
  *Ferdig når:* fersk rigg + seed demonstrerer E1-E9, og
  sikkerhetstabellen dekker alle nye flater med bekreftet status.
  *Notat (2026-08-29):* Seed gir nå også 3 arrangementer (2
  kommende + 1 tidligere), 4 koblinger i statusene foreslått/
  godkjent/introdusert/avvist (statusløftene utløser E2-varsler til
  partene) og en avstemning med stemmer fra tre medlemmer;
  --slett rydder de nye typene (kaskadene tar varsler/stemmer).
  Hooks- og sikkerhetsdocs var ført løpende per runde og dekker
  E1-E9; README-kravene beskriver nå de to daglige cron-jobbene,
  ekte cron-anbefaling for stille nettsteder, wp samlab-kommandoene
  og utgående e-post. Verifisert i HELT fersk rigg
  (bin/testrigg.sh, som nå også lager testbrukerne): seed + alle
  13 riggtester grønne (225 sjekker) to ganger på rad, og
  E-flatene demonstrert over HTTP (avstemning og festet oppslag på
  veggen, arrangementer med tidligere-seksjon, varsler via REST,
  koblingskø, skjerm av som standard). Fersk-rigg-kjøringen
  avdekket og rettet tre skjøre tester: b3/b5 tålte ikke at termer
  alt fantes (seed/gjentatt kjøring), b4 forutsatte en manuelt
  opprettet bruker, e2 hardkodet db-versjonen. Tom debug.log,
  WPCS grønn. Fase E komplett.

## Fase F: Assistenten (planens fase 4)

*Valgfri modul - portalen fungerer fullt ut uten. API-nøkkelen leses
fra konstanten `SAMLAB_CLAUDE_API_KEY` i wp-config.php, aldri fra
databasen. Kall går server-side via WordPress' HTTP-API mot Claude
Messages API (pluginen shipper uten composer-runtime). Modell er en
innstilling med standard `claude-opus-5`. Verifisering i riggen
mocker API-et med `pre_http_request`-filteret, så ingen nøkkel
trengs i test.*

- [x] **F1. Modulinnstillinger.** Egen seksjon på innstillingssiden:
  modul av/på (standard av), assistentnavn, velkomstmelding,
  toneinstruks, modell (standard `claude-opus-5`), eksterne kilder
  (URL-liste), og statusvisning (nøkkel funnet i wp-config: ja/nei -
  aldri selve nøkkelen). Ingen assistent-kode lastes når modulen er
  av.
  *Ferdig når:* innstillingene lagres sanitert, av/på styrer
  faktisk lasting, status vises riktig med/uten konstant i riggen.
  *Notat (2026-08-29):* includes/assistent.php (bootstrap som
  alltid lastes: helpers med nøytrale standarder + av/på-bryteren)
  og includes/assistent/modul.php (lastes KUN når modulen er på -
  hjemmet for F2-F4). Innstillingssiden fikk fire nye felttyper:
  overskrift (seksjonsrad), status (visning via callback, tar
  aldri imot POST), tekstfelt (textarea) og urlliste (kun
  http(s)-URL-er overlever, javascript:/ftp: forkastes); modell-ID
  vaskes til [a-z0-9.-]. Nøkkelhelperen leser kun konstanten og
  statusteksten røper aldri verdien. 21 tester grønne (sanitering,
  standarder, nøkkelstatus med/uten konstant, modul ikke lastet
  når av) + prosess-verifisering av at av/på faktisk styrer
  lastingen (function_exists false/true/false over tre wp-kall) og
  HTTP: seksjonen rendres, «Ikke funnet» uten konstant, «Funnet» med
  konstant satt via wp config set - og aldri nøkkelverdien i
  HTML-en. Tom debug.log, WPCS grønn, sikkerhetstabellen
  oppdatert.
- [x] **F2. Kunnskaps-cron.** Cron-jobb som bygger kunnskapsgrunnlag
  fra portalinnholdet (bedrifter med intensjoner, behov, håndbok,
  arrangementer) pluss de eksterne URL-ene (hentet og strippet til
  tekst). Hemmelighetsprinsippet: aldri passord/sensitive detaljer;
  grunnlaget viser til innloggede sider for detaljer. Lagres
  versjonert (option/fil) med tidsstempel og størrelse synlig i
  innstillingene; manuell «bygg nå»-knapp.
  *Ferdig når:* grunnlaget bygges korrekt fra seed-data i riggen,
  eksterne kilder hentes (mocket), og innhold fra ikke-portal-sider
  havner ikke i grunnlaget.
  *Notat (2026-08-29):* includes/assistent/kunnskap.php (lastes kun
  via modulen): seksjoner for bedrifter med intensjoner/tjenester/
  kontaktperson, behov med retning og detaljer, kommende
  arrangementer og håndbok-merkede sider - alle med portallenker
  for detaljer. Hemmelighetsprinsippet håndhevet: kun
  håndbok-MERKEDE sider, passordbeskyttet innhold hoppes alltid
  over. Eksterne kilder via wp_remote_get, strippet (inkl.
  script/style) med 20 000-tegns tak per kilde; feilede kilder
  registreres og navngis i statusen. Lagres i samlab_kunnskap-
  option (autoload av) med inkrementell versjon, tidsstempel og
  størrelse; status på innstillingssiden (vises også når modulen
  er av) + «Bygg nå»-knapp bak manage_options + nonce. Daglig cron
  samlab_assistent_kunnskap planlegges når modulen er på og ryddes
  ved av-slåing og deaktivering; `wp samlab kunnskap [--vis]` med
  vakt når modulen er av. Action samlab_kunnskap_bygget. 17 tester
  grønne med pre_http_request-mock (portalinnhold med, tidligere
  arrangement/ikke-portal-side/passordside IKKE med, HTML-fritt,
  404-kilde registrert, versjonstelling) + HTTP: status og knapp
  rendres kun riktig, bygg via knappen 302 og versjonen teller
  opp, 403 uten nonce. Tom debug.log, WPCS grønn, hooks- og
  sikkerhetsdocs oppdatert.
- [x] **F3. REST: assistent-endepunktet.** `POST samlab/v1/assistent`
  (innlogget + portal-capability): server-side kall til Messages
  API via `wp_remote_post` med system-prompt (navn/tone +
  kunnskapsgrunnlag) markert for prompt-caching (`cache_control`
  på systemblokken), samtalehistorikk fra klienten (avgrenset
  lengde), rate-limiting per bruker (transient-basert) og ryddige
  feilsvar (503 uten nøkkel/modul av, 429 over grensen). Ingen
  logging av spørsmål/svar utover feilsøking (av som standard).
  *Ferdig når:* endepunktet svarer korrekt mot mocket API i riggen,
  avviser uinnloggede (401), håndhever rate-limit (429), og gir
  503 uten nøkkel - uten å lekke konfigurasjonsdetaljer.
  *Notat (2026-08-29):* includes/assistent/api.php (kun lastet med
  modulen på - av gir 404 på ruten, dokumentert valg i tråd med
  «ingen assistent-kode når av»; 503 gjelder manglende nøkkel).
  Systemprompt i to blokker: instruks (navn, portalnavn, bokmål,
  «kun grunnlaget, ikke gjett», henvis til portalsidene, aldri
  passord/persondetaljer + valgfri toneinstruks) og
  kunnskapsgrunnlaget med cache_control ephemeral. Historikken
  vaskes: rolle-whitelist (system-rollen avvises som
  injeksjonsvern), sanitert og kappet tekst, maks 10 siste
  innslag. Rate: 15 kall per 5 min per bruker via transient.
  API-feil gir generisk 502; kun statuskoden logges, kun med
  WP_DEBUG. Action samlab_assistent_svarte (bevisst uten innhold).
  17 tester grønne mot pre_http_request-mock (riktig header/modell/
  systemblokker/avgrensning, 401/503/429/502, ingen konfiglekkasje)
  + HTTP med mu-plugin-mock og nøkkel via wp config set:
  401 uinnlogget, 503 uten nøkkel, 200 med svar og navn, 429 over
  grensen, 404 med modul av. Debug-loggens ene linje var den
  bevisste statuskode-loggingen under WP_DEBUG. WPCS grønn,
  hooks- og sikkerhetsdocs oppdatert.
- [x] **F4. Chat-widgeten.** Flytende assistentknapp i portalskallet
  (kun når modulen er på): panel med velkomstmelding, meldingsliste,
  «skriver …»-indikator og hel-svar-levering (planens plan B).
  SSE-streaming dokumenteres som mulig oppgradering i docs/hooks.md
  med testoppskrift for webhotellet - bygges ikke nå.
  *Ferdig når:* hele samtaleflyten virker i riggen mot mocket API,
  widgeten er fraværende for utloggede og når modulen er av,
  og all output escapes.
  *Notat (2026-08-29):* includes/assistent/widget.php hektet på ny
  action samlab_portal_bunn i skallet (dokumentert); rendres kun
  for innloggede med modulen på. Panel med velkomst fra
  innstillingen, bobler, skriver-indikator og skjema; JS holder
  historikk (siste 10) og poster mot F3-endepunktet; all
  DOM-skriving via textContent, PHP-output escaped. Widget-CSS på
  portal-tokens. SSE dokumentert i docs/hooks.md med
  bufring-testoppskrift for webhotellet. 9 eval-tester grønne
  (hekting, fravær utlogget, escaped navn/velkomst, ingen
  innerHTML) + HELE samtaleflyten verifisert i ekte nettleser
  (Playwright/Chromium mot riggen med mu-plugin-mock,
  tests/rigg/test-f4-flyt.js): åpne/lukke, velkomst, melding →
  skriver-indikator → mock-svar i boble, HTML i svar og
  XSS-forsøk i melding vises som ren tekst og tolkes aldri, andre
  melding bærer historikken (API-et mottok 3 meldinger).
  Nettlesertesten fanget en ekte feil: panel-CSS-ens display:flex
  overstyrte hidden-attributtet - rettet med [hidden]-regel.
  Widget fraværende over HTTP med modul av. Tom debug.log, WPCS
  grønn, sikkerhetstabellen oppdatert.
- [x] **F5. Verifisering og dokumentasjon for fase F.** Røyk-test
  (tests/rigg/) som dekker F1-F4 med mock; docs/sikkerhet.md
  utvides med assistentflatene (inkl. trusselnotat om
  prompt-injeksjon fra portalinnhold i kunnskapsgrunnlaget og at
  assistenten aldri får skrivetilgang); README får
  installasjonsavsnitt for modulen (konstanten, kostnad, av/på).
  *Ferdig når:* fersk rigg demonstrerer assistenten mot mock kun
  via README-stegene, og sikkerhetstabellen dekker alle nye flater.
  *Notat (2026-08-29):* Nytt tests/rigg/kjor-alle.sh kjører alle
  17 riggtestene samlet og orkestrerer modultilstanden selv (f1
  krever av, f2-f4 på) - 271 sjekker grønne i helt fersk rigg.
  README fikk assistent-avsnitt (konstanten i wp-config, av/på,
  kunnskapsbygging, kostnad med prompt-caching og rate-grense, og
  mock-mu-plugin-oppskrift for verifisering uten nøkkel/nett) og
  oppdatert verifiseringsseksjon. Sikkerhetsdocs fikk trusselnotat:
  prompt-injeksjon fra portalinnhold kan ikke filtreres bort, så
  vernet er konsekvensbegrensning - assistenten har aldri
  skrivetilgang (ingen verktøy/API-er, kun tekst), svar escapes i
  widgeten, verste utfall er et synlig, rettbart villedende svar.
  Hovedverifisering i helt fersk rigg KUN via README-stegene:
  mock-mu-pluginen trukket ordrett ut av README (syntaks-sjekket),
  konstant med mock-verdi, modul på, wp samlab kunnskap, chat-knapp
  til stede i portalen og spørsmål besvart med mock-svar over REST.
  Tom debug.log, WPCS grønn. Fase F - og hele backloggen - komplett.

**Milepæl: Full pakke.** Når F5 er krysset av er hele planens fase
0-4 levert. Neste beslutningspunkt (interaktivt): LLM-assistert
matching-scoring, SSE-streaming etter webhotell-test, og
erfaringene fra Lius-piloten.

## Fase G: Pitch-løftene (samtykke, utfall/rapport, ubesvart-kø)

*Gjennomgangen 2026-08-30 av `samlabpitch.pptx` mot koden fant tre
løfter decket gir som fase A-F ikke dekker: parts-samtykke i
introduksjonsflyten (slide 6, steg 4: «ingen kontaktes uten å ha
sagt ja»), utfallsregistrering og rapport (slide 6 steg 6 og hele
slide 7), og assistentens ubesvart-løkke (slide 10). Fase G lukker
dem. Rekkefølgen er bevisst: G1-G3 er samtykkeflyten, G4-G5 bygger
på den, G6-G7 er uavhengige av resten. Veivalgene bak fasen er
avgjort av Kay 2026-08-30 - se punkt 5-8 i AVKLARINGER.md.*

- [x] **G1. Samtykke-datamodell og statuskjede.** Ny status
  `forespurt` mellom foreslått og godkjent, slik at
  `samlab_kobling_statuser()` blir foreslått → forespurt → godkjent
  → introdusert → fulgt opp (avvist fortsatt terminal sidegren).
  «Godkjent» endrer betydning til «begge parter har takket ja»;
  kontrollpanelets godkjenn-handling settes om til å sette
  forespurt (knappetekst «Godkjenn og spør partene»). Samtykke per
  part i meta `_samlab_samtykke_a`/`_samlab_samtykke_b`
  (venter|ja|nei), nullstilt til venter når forespurt settes. Ny
  funksjon `samlab_kobling_svar( $kobling_id, $part, $svar,
  $user_id )` som fører samtykket, logger i statusloggen og løfter
  status selv: begge ja → godkjent, ett nei → avvist. Ny action
  `samlab_kobling_besvart`. Eksisterende koblinger i godkjent/
  introdusert/fulgt_opp beholdes uendret og regnes som samtykket
  (historikk fra før kravet). Metaboksen og kontrollpanelet viser
  samtykkestatus per part.
  *Ferdig når:* ingen kobling kan nå godkjent uten to ja (håndhevet
  i `samlab_kobling_svar`, ikke bare i UI), riggtest dekker begge
  ja / ett nei / svar i feil status, og WPCS er grønn.
  *Notat (2026-08-30):* Statuskjeden utvidet med forespurt;
  samtykke-meta med lat historikk-tolkning (godkjent+ uten meta =
  to ja), samlab_kobling_svar() med vakter for part/svar/status og
  statusløft ført som system (0) - ellers hadde varselsystemets
  aktør-hopp latt den som svarte sist stå uten godkjent-varsel.
  Logg-uttrekk i samlab_kobling_logg() med samtykke_ja/nei-innslag
  og egne etiketter; forespurt nullstiller samtykkene (også ved
  re-forespørsel). Kontrollpanelet fikk «Venter på partene»-seksjon
  med samtykkekolonne og trekk tilbake-knapp; metaboksen viser
  samtykke per part, og manuell overstyring til godkjent+ fører
  samtykkene som ja. Manglende forespurt-varsel til partene er
  G2s jobb. Ny riggtest test-g1.php (22 sjekker) dekker kontrakten;
  test-e3 omskrevet til to-parts-flyten. docs/hooks.md dokumenterer
  samlab_kobling_besvart og den nye kjeden. Alle 18 riggtester
  grønne to ganger på rad (329 sjekker), admin-flatene røyk-rendret
  uten warnings, WPCS grønn. POT-fila var utdatert (38 av 254
  strenger) og ble regenerert med wp i18n make-pot.
- [x] **G2. Forespørsel-varsler og svar-endepunkt.** Varseltype
  `kobling_forespurt` til begge parter når status settes til
  forespurt - med begrunnelsen (koblingens brødtekst), uten
  motpartens kontaktdetaljer; kontaktinfo deles først fra godkjent.
  Varsel til moderatorene når begge har svart (begge ja eller ett
  nei), og nøytralt varsel til motparten ved nei i tråd med
  avklaring 5. REST: `POST samlab/v1/koblinger/<id>/svar` med
  `{ svar: ja|nei }`, permission = `samlab_er_kobling_part` +
  nonce; 403 for ikke-parter, 409 når koblingen ikke står i
  forespurt. `GET samlab/v1/koblinger` (kun egne koblinger) som
  datagrunnlag for G3. docs/hooks.md og sikkerhetstabellen føres i
  samme endring.
  *Ferdig når:* riggtest viser hele flyten over REST (forespurt →
  varsler → to ja → godkjent → varsel), 401/403/409-vaktene
  holder, og forespurt-varselet aldri inneholder kontaktinfo.
  *Notat (2026-08-30):* Varsleren utvidet: kobling_forespurt til
  begge parter (aktør 0 så en kontaktperson som selv er moderator
  også får den; tekst = tittel + begrunnelse, aldri kontaktinfo),
  kobling_ikke_noe nøytralt til motparten ved nei (aktør 0, sier
  aldri hvem - avklaring 5), kobling_besvart til alle med
  edit_samlab_koblinger når begge har svart (lenke til
  kontrollpanelet). REST: GET /koblinger (egne koblinger via
  partskap-filter, motpart_kontakt null frem til godkjent) og
  POST /koblinger/<id>/svar (partskap i permission-callbacken,
  404/403, samlab_feil_status mappet til 409). Nye helpere
  samlab_kobling_bruker_part/part_navn/part_bruker i koblinger.php
  (samlab_er_kobling_part gjenbruker den første). Riggtest
  test-g2.php (23 sjekker) kjører hele flyten over REST inkl.
  nei-grenen og varsel-tekstenes kontaktinfo-/anonymitetskrav;
  test-e3 justert (uleste = forespørsel + godkjent). hooks.md
  (endepunkter + varseltyper) og sikkerhetstabellen ført; POT
  regenerert. Alle 19 riggtester grønne to ganger på rad, WPCS
  grønn. Varsel-lenken for forespørsler settes når G3-flaten
  finnes.
- [ ] **G3. Portalflate for koblinger.** Ny flate «Koblinger» i
  portalen (bak innloggingsporten, alltid i nav, med tom-tilstand):
  åpne forespørsler øverst med begrunnelse og Takk ja / Nei
  takk-knapper (JS mot G2-endepunktet), deretter aktive og
  historiske koblinger med statuskjede-visning som i prototypen.
  Ukesbrevet får en seksjon for ubesvarte forespørsler (filteret
  `samlab_ukesbrev_seksjoner`). Seed utvides med en forespurt
  kobling så flaten kan demonstreres.
  *Ferdig når:* flyten kan gjennomføres i nettleser i riggen
  (forespørsel synlig → ja fra begge parter → status godkjent
  synlig), utloggede møter innloggingsporten, og all output
  escapes.
- [ ] **G4. Utfallsregistrering («ble det noe?»).** Meta
  `_samlab_utfall` (mote|avtale|henvisning|ingenting) pluss
  valgfritt kort notat på koblinger. Settes av community-manageren
  i kontrollpanelet (fulgt opp-handlingen utvides med utfallsvalg)
  og av partene fra G3-flaten (`POST
  samlab/v1/koblinger/<id>/utfall`, samme partsvakt som G2).
  Påminnelsesvarsel til partene 14 dager etter introdusert, hektet
  på den daglige matching-cronen - sendes én gang per kobling.
  Prinsipp fra decket: aggregert, aldri salgsdetaljer - kun
  kategori og notat, aldri beløp.
  *Ferdig når:* utfall kan settes fra begge flater med riktige
  tilganger, påminnelsen sendes nøyaktig én gang per kobling i
  riggen, og utfallet vises i kontrollpanelet.
- [ ] **G5. Rapportflate.** Undermeny «Rapport» under
  kontrollpanelet (samme capability, `edit_samlab_koblinger`):
  valgbar periode (30/90/365 dager) med aggregerte tall - nye
  behov, matchforslag, forespurte, godkjente, avviste,
  introduserte, utfall per kategori, lesebekreftelsesgrad på
  festede oppslag, arrangementer avholdt og aktive medlemmer
  (minst én registrert hendelse: innlegg, kommentar, reaksjon,
  stemme eller lesebekreftelse). Tidsgrunnlaget finnes allerede i
  statusloggene og egentabellene - ingen nye tabeller.
  CSV-eksport av tallene (admin-post + nonce). Kun aggregater -
  rapporten lister aldri hvem som gjorde hva. Gårdeier-metrikkene
  fra slide 7 (fornyelsesgrad, frafall, lokalbruk) er utenfor
  datagrunnlaget - se avklaring 8.
  *Ferdig når:* tallene stemmer mot seed-dataene i riggen for alle
  tre periodene, CSV-en åpner i regneark, og siden svarer 403 uten
  koblings-capability.
- [ ] **G6. Ubesvart-deteksjon i assistenten.**
  Systemprompten instruerer modellen til å starte svaret med
  markøren `[UBESVART]` når kunnskapsgrunnlaget ikke holder;
  api.php stripper markøren før svaret går til medlemmet og legger
  spørsmålet i køen: option `samlab_ubesvart` (autoload av) med
  tak på 200 innslag (FIFO), kun spørsmålstekst, dato og teller -
  aldri bruker-ID og aldri svaret. Dedupe på normalisert tekst
  (telleren økes). Egen innstilling av/på i assistent-seksjonen
  med klartekst om nøyaktig hva som lagres; README og
  docs/sikkerhet.md omformulerer «spørsmål og svar logges aldri»
  til å beskrive den anonyme køen.
  *Ferdig når:* mocket API-svar med markør havner anonymt i køen
  (og medlemmet ser svaret uten markør), svar uten markør lagres
  aldri, tak og dedupe holder i riggtest, og innstillingen av
  stopper all lagring.
- [ ] **G7. Ubesvart-køen i kontrollpanelet.** Seksjon i
  kontrollpanelet (CM-ens flate) som lister køen med
  antall-per-spørsmål, «håndtert»-knapp (fjerner innslaget) og
  «legg til i håndboken»-lenke som oppretter et håndbok-utkast
  forhåndsutfylt med spørsmålet som tittel. Løkken fra slide 10
  lukkes av F2: neste kunnskapsbygg tar med den nye siden, og
  assistenten kan svare.
  *Ferdig når:* riggtest viser hele løkken - ubesvart spørsmål i
  kø → håndbok-side publisert → `wp samlab kunnskap` → grunnlaget
  inneholder svaret - og køen er tom etter håndtering.
- [ ] **G8. Seed, docs og samlet verifisering for fase G.** Seed
  gir forespurte koblinger og et utfall på en fulgt opp-kobling;
  docs/hooks.md, docs/sikkerhet.md og README dekker alle nye
  endepunkter og flater; kjor-alle.sh kjører de nye riggtestene.
  Til slutt noteres her hvilke deck-formuleringer som fortsatt må
  justeres av et menneske (minst «møte bookes direkte» og
  gårdeier-metrikkene - se avklaring 8).
  *Ferdig når:* fersk rigg + seed demonstrerer G1-G7, alle
  riggtester er grønne to ganger på rad, og dokumentasjonen dekker
  de nye flatene.
