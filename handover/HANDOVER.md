# Handover: Samlab - community-portal for norske coworking- og kontorfellesskap

*Overleveringsdokument, august 2026. Skrevet for oppstarten av et
selvstendig prosjekt i eget GitHub-repo, med videre utforskning i en
ny Claude-chat. Alt under er destillert fra arbeidet i Lius-repoet
(`kaelsense/lius`) sommeren 2026.*

---

## 1. Hva prosjektet er

**Samlab** er en WordPress-plugin som gir et hvilket som helst
WordPress-nettsted en komplett intern community-portal. Nisjen er **norske coworking-hus
og kontorfellesskap**, men kjernen er generisk nok til at foreninger,
klynger og næringshager kan bruke den som ren community-plugin.

Kjernefunksjonene (fra B2B Community OS-konseptet):

- **Bedriftsprofiler med intensjon** - hva bedriften leverer, kjøper,
  trenger nå, hvem som er ideelle kunder, og hva de er åpne for
- **«Jeg trenger / jeg tilbyr»** - strukturerte behov med frist,
  budsjett, kompetanse og ønsket kontaktform (kjernen i produktet)
- **Varme introduksjoner** - systemet foreslår, community-manageren
  kvalitetssikrer, begge parter godkjenner; lettvekts statuskjede
  (foreslått → akseptert → møte → ... → avtale inngått)
- **Community-managerens kontrollpanel** - koblinger, oppfølging og
  «trenger oppmerksomhet»-lister i wp-admin
- **Intern vegg** («Pulsen» hos Lius) og **håndbok** («Praktisk») for
  samhold og praktisk info
- **AI-assistent** (valgfri modul; «Kimma» hos Lius) - RAG-chatbot
  over portalens og nettstedets innhold via Claude API

**Hvorfor navnet Samlab:** navnet er satt sammen av «sam-» og «lab». Sam-forstavelsen er den
norske kjernen i alt produktet handler om: samarbeid, samhandling,
samlokalisering. Lab peker på at huset er et sted der noe utvikles,
ikke bare et sted der folk sitter. Navnet sier med to stavelser det
pitchen bruker tolv sider på: fellesskapet i huset er et sted der
forretning skapes.

## 2. Avklarte beslutninger (ikke reforhandle uten grunn)

1. **WordPress-plugin, ikke Astro/Supabase.** Frittstående
   versjon/SaaS kan vurderes senere; datamodell og REST-API designes
   så portering forblir mulig, men det brukes ikke tid på det nå.
2. **Ingen Lius-hardkoding.** Ikke i navn, tekster, farger, logoer
   eller eksempeldata. Lius er første kunde og referanse - «Pulsen»,
   «Kimma», `/intern/`-stien og grønnfargen `#a5c23e` er innstillinger
   i Lius' installasjon, ikke pluginens standarder.
3. **Temaets design er standard.** Prototypen er fasit for struktur og
   UX, men farger/fonter arver fra nettstedets tema via WordPress'
   preset-tokens (`--wp--preset--color--*`,
   `--wp--preset--font-family--*` fra theme.json) med nøytrale
   fallbacks. Innstillinger kan overstyre (aksentfarge, logo,
   portalnavn, flatenavn, portal-sti).
4. **Ingen betal-avhengigheter.** Ikke ACF Pro, ingen tema- eller
   Elementor-kobling. Egne metabokser, eget app-skall på portal-rutene.
5. **Norsk bokmål først**, all tekst i språkfiler, klargjort for
   oversettelse.
6. **Assistenten er valgfri modul** - portalen fungerer fullt ut uten.
   API-nøkkel som konstant i `wp-config.php`, aldri i databasen.
7. **Distribusjon via egen oppdaterings-URL** i første omgang (eier
   kundelisten), ikke wordpress.org. Lisensmodell avklares i fase 0.
8. **Hemmeligheter aldri i repo, database eller kunnskapsgrunnlag** -
   wifi-passord-prinsippet fra Lius videreføres.

## 3. Innholdet i denne pakken

| Sti | Hva det er |
| --- | --- |
| `HANDOVER.md` | Dette dokumentet |
| `CLAUDE.md` | Ferdig prosjektinstruks for det nye repoet - legg den i rot, så har neste Claude-chat riktig kontekst og regler fra start |
| `BACKLOG.md` | **Loop-klar arbeidsliste** for fase 1-2: 26 oppgaver (A1-D4) med «Ferdig når»-kontrakt per oppgave, loop-prompten som skal limes inn, og rundereglene. Tilstanden bor i filen, ikke i chatten |
| `AVKLARINGER.md` | Tom liste der loop-rundene parkerer spørsmål og veivalg til menneskelig beslutning |
| `docs/forslag-wordpress-utvidelse.md` | **Hoveddokumentet**: den vedtatte planen - arkitektur, datamodell, roller, app-skall, kontrollpanel, assistent, sikkerhet, pakketering, faseplan |
| `docs/forslag-b2b-community-os.md` | Konseptet/kravspesifikasjonen: intensjonsprofiler, behov/tilbud, introduksjonsflyt, måleparametre, MVP-liste, forskningsgrunnlag |
| `docs/forslag-chatbot-lius-intern.md` | Assistent-modulen: RAG-arkitektur (alt-i-kontekst med prompt-caching), systemprompt-utkast, kostnadsestimat, personvern |
| `docs/forslag-lius-intern.md` | Versjon 1 av konseptet - fortsatt gyldig for roller, moderering, GDPR og utrullingsfaser |
| `docs/analyse-fluentcommunity.md` | Konkurrentanalyse av FluentCommunity med 12 key takeaways og konkrete planjusteringer (hybrid datamodell, hooks/REST-navnerom, varsler, import, freemium-spørsmålet) |
| `docs/analyse-infohub.md` | Analyse av norske Infohub (internkommunikasjon for feltarbeidere): tillitspakke for det norske markedet, prisbenchmark i NOK, lesebekreftelser, infoskjerm-visning, meldingsskjemaer og posisjoneringslærdom |
| `docs/analyse-markedslandskap.md` | Kartlegging av hele landskapet i fem kategorier (coworking-drift, tenant experience, generiske community-plattformer, B2B-matchmaking, uformelle alternativer) med gap-tabell som viser at posisjonen vår er ledig, trusselvurdering og strategi |
| `referanse/prototype-demo.html` | **Klikkbar prototype i én fil** - åpne i nettleser: alle 14 sidene (vegg, behov & tilbud, bedriftsprofiler, håndbok med FAQ, kontrollpanel, chat-mock) med fungerende navigasjon. Dette er struktur- og UX-fasiten |
| `referanse/pitch-deck-samlab.pptx` | **Samlab-produktpitchen** (13 slides, nøytral drakt): problemet, dokumentert verdi og betalingsvilje, produktet, introduksjonsflyten, gap-tabellen «posisjonen er ledig», hvorfor WordPress, assistenten, «hvorfor ikke bare ...»-svarene og veikartet - med innsiktene fra alle tre analysene innbakt |
| `referanse/pitch-deck-lius.pptx` | Den opprinnelige Lius-brandede decken (konseptsalg internt hos Lius) |
| `prototype-kilde/` | Kildefilene bak prototypen: layout (app-skallet), alle sidene, eksempeldataene og designtokens-CSS-en. Porteres til PHP-templates og token-CSS |

Om `prototype-kilde/`: filene er Astro/TypeScript og skal **ikke**
kjøres i det nye prosjektet - de er lesestoff. Markup-strukturen og
klassenavnene porteres tilnærmet 1:1 til PHP-templates;
`styles/global.css` viser token-arkitekturen (fargene byttes til
`--wp--preset-*`-variabler); `data/intern.ts` blir mal for
`wp samlab seed` (med nøytrale demobedrifter - Lius-dataene brukes kun
i Lius' installasjon).

## 4. Status: hva som er gjort og ikke

**Gjort:**
- Konseptet er gjennomarbeidet og validert mot Lius' faktiske hverdag
  (Onsdagspitch og «Bli kjent med ...» er fysiske forløpere til
  behov/tilbud og bedriftsprofiler - konseptet er bevist i praksis)
- Komplett klikkbar designprototype (struktur/UX-fasit)
- Vedtatt arkitekturplan for WordPress-pluginen
- Assistent-arkitektur med kostnadsestimat (~0,5 kr/spørsmål på
  Claude Opus 5 med prompt-caching, femtedelen på Haiku 4.5)
- Pitch-deck for konseptsalg og produktpitch for Samlab
- Produktnavnet er valgt: **Samlab** (slug og tekstdomene: `samlab`)

**Ikke gjort (starter i det nye repoet):**
- Ingen plugin-kode er skrevet ennå - fase 0 og 1 fra planen er neste
- Lisensmodell ikke avklart
- SSE/cron-oppførsel på aktuelle webhoteller ikke testet

## 5. Neste steg (fra planens faseplan)

1. **Fase 0 (2-3 dager):** lisensmodell, WordPress-/PHP-versjonskrav, test av SSE og wp-cron
   på Lius' webhotell (første kunde = første driftsmiljø)
2. **Fase 1 (1-2 uker):** plugin-skjelett, CPT-er, roller,
   portal-ruter med app-skall, token-CSS som arver temaet,
   innloggingsport, innstillingsside
3. **Fase 2 (2-3 uker):** bedriftsprofiler, behov & tilbud, veggen,
   håndbok-sider, `wp samlab seed` → **MVP, klar for Lius-piloten**
4. Fase 3 (kontrollpanel/matching/ukesbrev) og fase 4 (assistenten)
   bygges mens piloten kjører
5. Underveis: test mot minst ett annet tema enn Lius' eget, så
   tema-arven bevises tidlig

## 6. Åpne spørsmål til det nye prosjektet

- **Repo-navn** (forslag: `samlab`)
- **Lisens- og prismodell** (årlig per installasjon er vanlig i
  WP-markedet; skal det finnes en gratis kjerne?)
- **Kildekodelisens** (GPL-kompatibilitet er i praksis påkrevd for
  WordPress-plugins - avklar forholdet til kommersiell modell)
- **Demomiljø**: hvor skal demo-WordPress-installasjonen bo?
- **Lius-migrering**: når pluginen er klar, hva skjer med
  Astro-prototypen på Vercel og dørvakt-oppsettet der?

## 7. Bakgrunn og referanser

- **Opphavsrepo**: `kaelsense/lius` (Astro-redesign av lius.no +
  internprototypen). All historikk ligger i PR #5-#14 der.
- **Live prototype**: https://lius-digitelle-as.vercel.app/intern/
  (åpen; resten av det domenet er bak delt Digitelle-innlogging)
- **Lius** er kunnskaps- og gründerhuset på Skårersletta 65 i
  Lørenskog (1 700 m², åpnet desember 2025), drevet av Lørenskog i
  Utvikling og Romerike Sparebank. Community-manager-rollen er reell
  og bemannet - kontrollpanelet har en faktisk bruker fra dag én.
- **Forskningsgrunnlaget** for konseptet (Impact Hub-tallene og
  2025-studien om community-managere) er referert i
  `docs/forslag-b2b-community-os.md`.

## 8. Slik starter du den nye Claude-chatten

1. Opprett det nye GitHub-repoet og legg inn innholdet i denne pakken
   (`CLAUDE.md` i rot, resten som det ligger).
2. Start en ny Claude-chat/Claude Code-økt på repoet.
3. Første melding kan være så enkel som: *«Les HANDOVER.md og
   docs/forslag-wordpress-utvidelse.md. Vi starter med fase 0 -
   avklar lisensmodellen og sett opp plugin-skjelettet for Samlab.»*
4. For autonom bygging: opprett branchen `utvikling`, sørg for en
   tillatelsesmodus som lar Claude redigere og kjøre kommandoer uten
   å spørre, og start loopen med prompten som står øverst i
   `BACKLOG.md`. Du reviewer branchen og tømmer `AVKLARINGER.md` i
   vanlige økter.

`CLAUDE.md` i pakken bærer prinsippene (ingen Lius-hardkoding,
tema-design som standard, sikkerhetsregler, språkregler), så de
overlever selv når chat-historikken ikke følger med.
