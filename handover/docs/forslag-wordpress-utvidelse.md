# Plan: Samlab - community-portal for norske coworking- og kontorfellesskap som WordPress-plugin

*Implementeringsplan, august 2026. Retningen er avklart: dette bygges
som en selvstendig WordPress-utvidelse, uavhengig av Lius. Astro- og
Supabase-sporet legges bort for nå (en frittstående versjon kan
vurderes senere). Bygger på
[forslag-b2b-community-os.md](./forslag-b2b-community-os.md)
(konseptet), [forslag-chatbot-lius-intern.md](./forslag-chatbot-lius-intern.md)
(chatboten) og den klikkbare prototypen under `/intern/`.*

---

## 1. Hva produktet er

**Samlab** er en WordPress-plugin som gir et hvilket som helst
WordPress-nettsted en komplett intern community-portal:

- **Nisjen**: norske coworking-hus og kontorfellesskap - bedriftsprofiler
  med intensjon, «Jeg trenger / jeg tilbyr», varme introduksjoner,
  community-managerens kontrollpanel, håndbok og intern vegg.
- **Men generisk i bunn**: ingenting i kjernen krever et coworking-hus.
  Foreninger, klynger, næringshager og organisasjoner kan bruke den som
  ren community-plugin. Nisjen ligger i standardtekster, maler og
  markedsføring - ikke i koden.
- **Lius er første kunde og utstillingsvindu** - ikke eier av koden.
  Ingen Lius-hardkoding noe sted: ikke i navn, tekster, farger, logoer
  eller eksempeldata. Alt Lius-spesifikt er innhold og innstillinger
  lagt inn i én installasjon.

Produktet heter **Samlab**. Samlab er satt sammen av «sam-» og «lab». Sam-forstavelsen er den
norske kjernen i alt produktet handler om: samarbeid, samhandling,
samlokalisering. Lab peker på at huset er et sted der noe utvikles,
ikke bare et sted der folk sitter. Navnet sier med to stavelser det
pitchen bruker tolv sider på: fellesskapet i huset er et sted der
forretning skapes.

Plugin-slug, tekstdomene og REST-navnerom følger navnet: `samlab`.

## 2. Designprinsipp: temaets design er standard

Prototypen under `/intern/` er **fasit for struktur og UX**: hvilke
flater som finnes, hvordan kort, chips, statuskjeder, profiler og
kontrollpanel er bygget opp, og hvordan flyten henger sammen.

Men **utseendet arver fra nettstedets tema**, ikke fra Lius:

- Pluginens CSS bygges på designtokens som leser WordPress' egne
  preset-variabler der de finnes (`--wp--preset--color--*`,
  `--wp--preset--font-family--*` fra theme.json), med nøytrale
  fallbacks for klassiske temaer uten theme.json. Fonter, farger,
  knappestil og avrunding følger dermed temaet automatisk.
- Egne innstillinger (aksentfarge, portalnavn, logo) kan overstyre
  temaet ved behov - men standarden er alltid «se ut som nettstedet
  den bor på».
- Lius-paletten (grønn `#a5c23e`, navy, Inter) blir bare verdiene
  Lius' egen installasjon ender opp med via sitt tema/innstillinger -
  ikke pluginens standard.
- Strukturell CSS (grid, kort-layout, avstander) porteres fra
  prototypen; fargede og typografiske verdier byttes ut med
  token-variablene.

## 3. Plugin-arkitektur

Én selvstendig plugin uten betal-avhengigheter (ingen ACF Pro, ingen
Elementor- eller temakobling):

```
samlab/
├── samlab.php       (bootstrap, aktivering, versjon)
├── includes/
│   ├── post-types.php         (bedrift, behov, innlegg, kobling, arrangement)
│   ├── roles.php              (medlem, bedriftsredaktør, moderator)
│   ├── rewrites.php           (portal-rutene + app-skall)
│   ├── rest-api.php           (samlab/v1: behov, reaksjoner, koblinger, assistent)
│   ├── access.php             (innloggingsport for alt under portal-stien)
│   ├── matching.php           (cron: matchforslag behov <-> intensjoner)
│   ├── digest.php             (cron: ukesbrev via wp_mail)
│   └── assistant.php          (Claude API-proxy med kunnskapsgrunnlag)
├── templates/                 (app-skallet + sidene, struktur fra prototypen)
├── assets/                    (token-basert CSS + litt vanilla JS)
├── languages/                 (norsk bokmål først, oversettbar)
└── admin/                     (kontrollpanel + innstillingsside)
```

Portal-stien er en innstilling (standard f.eks. `/portal/`; Lius
setter `/intern/`).

### 3.1 Datamodell: Custom Post Types

| Prototypen | WordPress |
| --- | --- |
| Bedriftsprofil | CPT: logo = fremhevet bilde, galleri = mediebibliotek, kategori = taksonomi, øvrige felter (plass, nettside, tjenester, intensjonene «Dette ser vi etter», kontaktperson) = post-meta med egne metabokser |
| Behov & tilbud | CPT: trenger/tilbyr + type = taksonomier; frist, budsjett, kompetanse, kontaktform = meta; kobles til bedriften |
| Vegg-innlegg (Pulsen) | CPT: WordPress-kommentarer gjenbrukes rett ut av boksen, reaksjoner = meta-teller via REST. Festing = meta som kun moderator+ kan sette |
| Introduksjoner/koblinger | CPT (ikke offentlig): parter, begrunnelse, statuskjeden som meta - synlig kun for community-manager og partene |
| Arrangementer | CPT med dato/sted/arrangør - eller kobling til en arrangements-plugin nettstedet allerede bruker |
| Håndboken («Praktisk») | Vanlige WordPress-sider i en intern gruppe - redigeres i Gutenberg av vertskapet |

Navn på flatene («Pulsen», «Behov & tilbud», «Praktisk») er
standardtekster som kan endres per installasjon - Lius beholder sine,
andre velger sine egne.

Eksempeldata følger med som en WP-CLI-kommando (`wp samlab seed`) med
nøytrale demobedrifter, så et demomiljø kan reises på minutter.
Lius-prototypens eksempeldata legges kun inn i Lius' installasjon.

### 3.2 Roller og rettigheter

Rollemodellen fra B2B-forslaget mapper direkte til WordPress'
capability-system:

- Medlem - kan lese alt internt, poste på veggen, opprette behov.
- Bedriftsredaktør - i tillegg redigere *egen* bedriftsprofil
  (håndheves med `map_meta_cap` mot bedriftens kontaktperson).
- Moderator - godkjenne medlemmer, skjule innhold, feste oppslag.
- Hovedadministrator = eksisterende administrator/redaktør-rolle.

Innmelding: vertskapet inviterer via e-post (WordPress' innebygde
brukeropprettelse), eller et søknadsskjema som havner i
godkjenningskøen. «Glemt passord», sesjoner og sikkerhet er WordPress
sitt bord - ferdig bygget og velprøvd. Dette er den største
enkeltgevinsten ved WordPress-sporet: auth og brukeradmin må ikke
bygges.

### 3.3 Frontend: eget app-skall på portal-rutene

Nøkkelgrepet: **ikke slåss med temaet om layout.** Pluginen
registrerer egne ruter (`add_rewrite_rule`) og svarer med sitt eget
fullstendige sideskall - som InternLayout i prototypen - men altså
kledd i temaets designtokens (kap. 2). Temaet og eventuelle
sidebyggere (Elementor, Gutenberg-temaer) fortsetter å eie resten av
nettstedet; pluginen eier alt under portal-stien.

- Interaktivitet (reaksjoner, filtre, statusknapper) løses med små
  vanilla JS-kall mot REST-endepunktene med WordPress' innebygde
  cookie+nonce-autentisering. Ingen React-bygg, ingen bundler.
- Alt under portal-stien krever innlogging: en
  `template_redirect`-sjekk sender uinnloggede til WordPress'
  innloggingsskjema og tilbake etterpå.
- Sidene settes med noindex; portal-innhold holdes ute av offentlige
  sitemaps og søk.

### 3.4 Kontrollpanelet: en wp-admin-side

Community-managerens kontrollpanel bygges som en admin-side i
wp-admin: foreslåtte koblinger med godkjenningsflyt, introduksjoner
med statuskjeden, og «trenger oppmerksomhet»-listene (nye uten
introduksjon, ubesvarte behov, ufullstendige profiler, stille
medlemmer).

Matchforslagene lages av en cron-jobb - regelbasert matching i første
versjon (behovstype/kompetanse mot intensjonsfeltene), LLM-assistert
scoring senere via samme Claude-integrasjon som assistenten.

### 3.5 Assistenten (hos Lius: «Kimma»)

Chatbot-forslaget blir enklere i WordPress, fordi innholdet allerede
bor der - og assistenten gjøres generisk:

- **Navn, tone og velkomstmelding er innstillinger.** «Kimma» er Lius'
  konfigurasjon, ikke pluginens.
- **Kunnskapsgrunnlaget** bygges av en cron-jobb direkte fra databasen
  (portalinnholdet + valgte offentlige sider). Eksterne kilder (for
  Lius: lorenskogiutvikling.no) legges inn som URL-liste i
  innstillingene og hentes via sitemap/REST.
- **Endepunktet** kaller Claude API server-side. API-nøkkelen legges
  som konstant i `wp-config.php` (aldri i databasen). Prompt-caching
  fungerer identisk (samme HTTP-API). Rate-limiting per bruker.
- **Streaming**: SSE fra PHP fungerer, men er skjør bak enkelte
  webhoteller (output-buffering/FastCGI). Plan B som er nesten like
  god på korte svar: vis «skriver ...» og lever hele svaret på én
  gang. Avklares med én test på det aktuelle webhotellet tidlig.
- Hemmelighetsprinsippet fra Kimma-forslaget videreføres: passord og
  sensitive detaljer holdes utenfor kunnskapsgrunnlaget; assistenten
  henviser til de innloggede sidene.
- Assistenten er en valgfri modul - portalen fungerer fullt ut uten,
  for kunder som ikke vil ha AI eller API-kostnad.

## 4. Sikkerhet og drift

Interne medlemsdata i samme installasjon som et offentlig nettsted
skjerper kravene:

- Nonces og capability-sjekker på alle REST-endepunkter, escaping og
  prepared statements overalt (WordPress-kodestandard, WPCS i CI).
- Oppdateringsregime for kjerne/tema/plugins og 2FA for
  administratorkontoer - WordPress-sikkerhet er 90 % vedlikehold.
  Dokumenteres som krav i installasjonsveiledningen.
- Backup må dekke portal-dataene; verifiseres per installasjon.
- Pluginen holdes selvstendig (ingen tredjeparts betal-plugins) så
  angrepsflate og lisenskostnad ikke vokser.

## 5. Gjenbruk fra det som allerede er bygget

| Bygget i denne sesjonen | Rolle i plugin-sporet |
| --- | --- |
| Prototypen `/intern/` | Struktur- og UX-fasit: flater, komponenter og flyt porteres; farger/fonter byttes til temaets tokens |
| `global.css` designtokens | Mønster for token-arkitekturen; Lius-verdiene blir Lius-installasjonens innstillinger |
| Eksempeldataene (`intern.ts`) | Konverteres til nøytral `wp samlab seed`; Lius-varianten legges kun i Lius' installasjon |
| B2B-forslaget | Kravspesifikasjon for CPT-er, roller og kontrollpanel |
| Kimma-forslaget | Implementeres som assistent-modulen; «Kimma» blir Lius' konfigurasjon |
| Pitch-decken | Selger konseptet; oppdateres med produktvinkelen når navnet er valgt |

## 6. Produktpakketering

- **All tekst i språkfiler** - norsk bokmål først, klargjort for
  oversettelse.
- **Innstillingsside**: portalnavn, portal-sti, logo, aksentfarge
  (valgfri overstyring av temaet), navnene på flatene,
  assistent-modul av/på med navn og kilder.
- **Distribusjon**: egen oppdaterings-URL i første omgang (Digitelle
  beholder kontroll og kundeliste), ikke wordpress.org. Lisensmodell
  avklares (årlig per installasjon er vanlig i WP-markedet).
- **Demomiljø**: én WordPress-installasjon med `wp samlab seed` og et
  standardtema - viser at portalen ser riktig ut uten Lius-design.
- Lius kjører produksjonspiloten og blir referansekunden.

## 7. Faseplan og estimat

| Fase | Innhold | Estimat |
| --- | --- | --- |
| 0. Avklaringer | Lisensmodell, test av SSE/cron på Lius' webhotell, WordPress-/PHP-versjonskrav | 2-3 dager |
| 1. Fundament | Plugin-skjelett, CPT-er, roller, portal-ruter med app-skall, token-CSS som arver temaet, innloggingsport, innstillingsside | 1-2 uker |
| 2. Kjernen | Bedriftsprofiler med redigering, Behov & tilbud, veggen med kommentarer/reaksjoner, håndbok-sider, `wp samlab seed` | 2-3 uker |
| 3. Fasilitering | Kontrollpanel i wp-admin, koblinger med statuskjede, regelbasert matching, ukesbrev | 1-2 uker |
| 4. Assistenten | Kunnskaps-cron, REST-endepunkt, widget - som valgfri modul | 1 uke |

MVP (fase 0-2) er 3-5 ukers utvikling; hele pakken 5-8 uker. Piloten
fra B2B-forslaget (5 bedrifter hos Lius, community-manager i
førersetet) kjøres etter fase 2 - fase 3-4 bygges mens piloten pågår.
Underveis testes pluginen mot minst ett annet tema enn Lius' eget,
så tema-arven bevises tidlig.

## 8. Utenfor scope nå

- **Astro + Supabase-sporet** er lagt bort. En frittstående versjon
  (SaaS utenfor WordPress) kan vurderes senere hvis produktet får
  fotfeste - datamodellen og REST-API-et designes så en slik portering
  er mulig, men det brukes ikke tid på det nå.
- Betaling/fakturering i portalen, møteromsbooking og adgangssystemer:
  finnes som egne systemer og holdes utenfor (som i B2B-forslaget).
- wordpress.org-katalogen: vurderes først når produktet er modent.
