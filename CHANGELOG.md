# Endringslogg

Alle merkbare endringer i Samlab føres her.

Formatet følger [Keep a Changelog](https://keepachangelog.com/no/1.1.0/),
og versjonsnumrene følger [semantisk versjonering](https://semver.org/lang/no/).

## [Uutgitt]

Ingenting ennå.

## [0.5.0] - 2026-08-30

Første samlede utgivelse. Portalen og fasiliteringslaget er
funksjonelt komplett mot kravspekken, men ikke pilotert i drift -
derfor 0.5.0 og ikke 1.0.0.

Versjon 0.1.0 var det interne skjelettet fra oppstarten og ble aldri
distribuert eller tagget. Alt under er derfor ført som nytt.

### Lagt til

**Portalen** - bak innlogging på egen sti (standard `/portal/`), med
eget app-skall, noindex og utenfor offentlige sitemaps og søk.

- Bedriftskatalog med profiler, kategorier, tjenester og kontaktperson
- Vegg med innlegg, bilder, reaksjoner, kommentarer og @-mentions
- Avstemninger på veggen (2-5 alternativer, én stemme per medlem)
- Lesebekreftelser på festede oppslag, med oversikt for verten
- Behov og tilbud, med retning, behovstype og frist
- Arrangementer med tid, sted og arrangør
- Håndbok som merkede sider, holdt utenfor offentlige flater
- Globalt søk på tvers av flatene
- Profilside med reservasjon mot ukesbrev
- In-app-varsler

**Fasilitering** - vertens arbeidsflate.

- Koblinger med statuskjede: foreslått, forespurt, godkjent,
  introdusert, fulgt opp (avvist som terminal sidegren)
- To-parts samtykke: ingen introduseres uten at begge har takket ja,
  og partene svarer selv fra portalens koblingsflate
- Utfallsregistrering: møte, avtale, henvisning eller ble ikke noe av,
  med «ble det noe?»-påminnelse fra cron
- Kontrollpanel med koblingskø, samtykkestatus, aktive koblinger og
  «trenger oppmerksomhet»-lister
- Aggregert rapport med tre perioder og CSV-eksport - kun aggregater,
  aldri hvem som gjorde hva, og aldri beløp
- Regelbasert matching som foreslår koblinger (aldri automatisk
  introduksjon)
- Ukesbrev på e-post, med reservasjon per medlem
- Infoskjerm på nøkkel-URL for skjermenheter uten innlogging

**Assistenten** - valgfri modul, portalen fungerer fullt ut uten den.

- Chat-widget for innloggede medlemmer
- Kunnskapsgrunnlag bygget av publisert portalinnhold og valgfrie
  eksterne kilder, med prompt-caching og rate-grense per medlem
- Kø over ubesvarte spørsmål, lagret anonymt, med ett klikk til et
  håndbok-utkast

**Plattform**

- Egne roller og capabilities (medlem, bedriftsredaktør, moderator)
- `map_meta_cap` slik at bedriftsredaktører kun kan redigere egen
  bedrift - håndhevet i tillatelseslaget, ikke bare i skjult UI
- REST-API under `samlab/v1` med cookie og nonce, dokumentert i
  `docs/hooks.md`
- Egne tabeller for innlegg, reaksjoner og stemmer
- WP-CLI-kommandoer for seed og matching
- Testrigg uten Docker (`bin/testrigg.sh`) og 25 riggtestfiler
- Alle brukervendte tekster gjennom språkfiler, norsk bokmål

### Sikkerhet

Systematisk gjennomgang av alle endepunkter og flater, dokumentert i
`docs/sikkerhet.md`. Rettet i samme runde:

- Håndbok-sider var offentlige utenfor portalen - permalenken
  301-er nå til portalruten, og sidene er utelatt fra sitemap,
  offentlig søk og anonym REST
- `GET samlab/v1/brukere` returnerte brukernavn for alle kontoer, også
  admin-kontoer utenfor portalen - avgrenset til `samlab_read_portal`
- Kunnskapsbygget hentet eksterne kilder med `wp_remote_get`, som lot
  en URL mot loopback eller interne adresser bli hentet og lagret
  (SSRF) - bruker nå `wp_safe_remote_get`
- Chat-widgeten ble rendret for alle innloggede, også de som alltid
  ville fått 403 - gates nå på capability og at nøkkel er satt
- Rate-grensen falt åpen når `wp_cache_incr` feilet - faller nå ned på
  transient framfor å slippe kallet gjennom

API-nøkkelen leses kun fra `SAMLAB_CLAUDE_API_KEY` i `wp-config.php`,
aldri fra databasen, og forlater aldri serveren.

### Kjent begrensning

- Mediefiler serveres fra `wp-content/uploads/` på gjettbare URL-er
  utenfor innloggingsporten - iboende i WordPress' mediemodell. Ikke
  last opp sensitive dokumenter som bilder.
- Betaling, møteromsbooking og adgangssystemer er utenfor scope.

[Uutgitt]: https://github.com/kaelsense/samlab/compare/v0.5.0...HEAD
[0.5.0]: https://github.com/kaelsense/samlab/releases/tag/v0.5.0
