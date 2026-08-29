# Konkurrentanalyse: FluentCommunity

*August 2026. Grunnlag: fluentcommunity.co (produkt-, funksjons- og
sammenligningssider), dev.fluentcommunity.co (utviklerdokumentasjonen)
og uavhengige omtaler. Formål: lærdom vi kan implementere i vår
community-portal for norske coworking- og kontorfellesskap - uten å
kopiere dem og uten å skifte retning.*

---

## 1. Hva FluentCommunity er

- WordPress-plugin fra WPManageNinja (Fluent-familien: FluentCRM,
  Fluent Forms, FluentSupport m.fl.) - lansert 2024, posisjonert som
  «The Community & LMS Plugin for Creators»: rask, lettvekts,
  selvhostet alternativ til BuddyBoss og hostede tjenester som Circle.
- Målgruppen er bred: kursskapere, coacher, merkevarer, foreninger,
  interne arbeidsplasser - alle som vil ha «Circle på eget domene».
- Freemium: gratis kjerneversjon + Pro fra 159 USD/år (ett nettsted)
  eller 399 USD lifetime. Alle nivåer lover ubegrensede spaces og
  medlemmer.
- Hovedargumentet mot SaaS: eierskap («You Own It All») og pris -
  Circle koster fra 89 USD/mnd, og de markedsfører «spar 4 869 USD+».

## 2. Funksjonsbildet

**Community-kjernen:** spaces (grupper med public/private/secret),
aktivitetsfeed, personprofiler, innlegg med kommentarer/reaksjoner/
emoji/GIF, direktemeldinger, medlemskatalog, varsler, e-postdigest,
leaderboard, mentions, hashtags, avstemninger, bokmerker, globalt søk,
mørk/lys modus, brukergodkjenning og moderering, rolle-styring.

**LMS-modulen:** kursbygger (Gutenberg), leksjoner/moduler,
fremdriftssporing, leksjonsdiskusjoner - enklere enn LearnDash, men
«god nok» for videokurs.

**Plattform:** medielagring lokalt/S3/Cloudflare R2, egendefinerte
slugs, ettklikks migrering fra BuddyBoss/BuddyPress, innkommende
webhooks.

## 3. Teknisk arkitektur (fra utviklerdokumentasjonen)

Dette er den mest lærerike delen:

- **Egne databasetabeller, ikke post types**: 21 modeller på 17 egne
  tabeller (Eloquent-stil via WPFluent-rammeverket). Det er slik de
  innfrir ytelsesløftet - feed, reaksjoner og meldinger går utenom
  wp_posts/wp_postmeta, som skalerer dårlig for høyfrekvent innhold.
- **REST-først**: 248 ruter under eget navnerom (`fluent-community/v2`
  - merk versjoneringen), cookie+nonce for portalen og Application
  Passwords for server-til-server.
- **Portalen eier sin egen flate** frakoblet temaets rendering - samme
  app-skall-grep som i vår plan.
- **466 dokumenterte hooks** (181 actions, 285 filters) og en egen
  utviklerportal (dev.fluentcommunity.co) med databaseskjema, API-
  referanse, guider og driftsdokumentasjon (serverkrav, caching,
  benchmarks). Utvidbarhet er behandlet som en produktflate.

## 4. Key takeaways - lærdom vi implementerer

Nummerert etter hvor de treffer i vår faseplan:

1. **Hybrid datamodell - juster planen vår (fase 1).** Vår plan sier
   CPT-er for alt. FluentCommunitys erfaring peker på et bedre skille:
   CPT-er for lavvolum strukturinnhold der wp-admin-redigering er
   verdifull (bedriftsprofiler, håndbok, arrangementer), men **egne
   tabeller for høyfrekvent innhold** (vegg-innlegg, reaksjoner,
   koblinger/statuslogg, varsler). Det gir ytelse uten å miste
   WordPress-fordelene der de faktisk hjelper.
2. **Versjonert REST-navnerom og stabile hooks fra dag én (fase 1).**
   `portal/v1` med bevisst navngitte actions/filters som dokumenteres
   løpende. Billig å gjøre fra start, umulig å ettermontere pent -
   og det er dette som gjør pluginen til en plattform andre kan bygge
   på (integrasjoner mot booking-/adgangssystemer er vår fremtid).
3. **Utviklerdokumentasjon som produktflate (fase 2-3).** En enkel
   docs-side med datamodell, hooks og REST-referanse. For vår nisje
   er kjøperen ofte et byrå eller en driftspartner for huset - de
   velger produktet med dokumentasjon.
4. **Varsler + e-postdigest er engasjementsmotoren (løft til fase 2/3).**
   Vi har ukesbrev i planen; FluentCommunity viser at in-app-varsler
   («noen svarte på behovet ditt», «introduksjon foreslått») og digest
   er det som drar folk tilbake. For vårt konsept er varselet ved
   behov-svar og introduksjoner selve produktverdien - prioriter det
   over generelle feed-varsler.
5. **Table-stakes for community-følelse (fase 2, resten backlog):**
   mentions (@navn), globalt søk og reaksjoner bør inn i MVP; emoji/
   GIF, hashtags, bokmerker, leaderboard og mørk modus legges i
   backlog. Uten et minimum av dette føles portalen «død» mot det
   folk kjenner fra andre verktøy.
6. **Avstemninger (fase 3).** Allerede nevnt i B2B-forslagets
   samholdskapittel - FluentCommunity bekrefter at polls er et enkelt,
   høyt verdsatt engasjementsverktøy. Passer perfekt for vertskapet
   («hvilket tema for neste frokostmøte?»).
7. **Lav terskel inn: medlemsimport (fase 2).** Deres ettklikks
   BuddyBoss-migrering er et salgsverktøy. Vår variant er enklere og
   viktigere: CSV/regneark-import av medlemmer og bedrifter, så et
   coworking-hus er i gang på en time. (Import *fra* FluentCommunity/
   BuddyPress kan bli et byttetriks senere.)
8. **Privacy-nivåer på grupper - når vi får grupper.** Spaces med
   public/private/secret er en gjennomprøvd modell. Vår portal er
   helt lukket i v1, men faggrupper/interessegrupper (B2B-forslagets
   kap. 7) bør arve denne tredeling når de kommer.
9. **Driftsdokumentasjon og benchmarks (fase 0/4).** De publiserer
   serverkrav, cache-anbefalinger og ytelsestall. Vi gjør det samme i
   liten skala: dokumenterte krav (WordPress/PHP-versjon, cron, SSE)
   og en målt referanseinstallasjon - det bygger tillit hos byråene.
10. **Sammenlignings- og kalkulatormarkedsføring (når produktet
    lanseres).** «Circle vs oss»-siden med konkrete besparelser er
    effektiv. Vår variant: mot OfficeRnD/Nexudus community-moduler og
    hostede community-tjenester, regnet i norske kroner - og mot
    FluentCommunity selv: «generisk community vs. forretningsverdi
    for kontorfellesskap».
11. **Freemium-spørsmålet inn i fase 0-agendaen.** Deres gratis kjerne
    på wordpress.org er vekstmotoren deres. Vår plan sier egen
    oppdaterings-URL - det står seg for en nisjeprodukt-start, men
    beslutningen bør tas bevisst med dette datapunktet på bordet
    (alternativ: gratis «katalog + håndbok»-kjerne, betalt
    matching/kontrollpanel/assistent).
12. **Medialagring utenfor databasen (backlog).** S3/R2-støtte
    trengs ikke i v1, men galleriene våre bør skrives med et
    lagringsabstraksjonslag så det kan legges til uten ombygging.

## 5. Hva vi bevisst IKKE gjør

- **Ikke LMS/kurs.** Deres hovedmodul, irrelevant for vår nisje.
- **Ikke direktemeldinger i v1.** Introduksjonsflyten vår er poenget -
  kuraterte koblinger fremfor fri chat. Chat kan vurderes langt senere.
- **Ikke generisk Circle-klone.** Det markedet tar FluentCommunity og
  BuddyBoss allerede, til priser vi ikke vil under.

## 6. Vår differensiering - bekreftet av analysen

Det viktigste funnet: **FluentCommunity har ingen bedrifter.** Hele
modellen deres er person-sentrisk (profiler, feed, kurs). De mangler
alt som er vår kjerne:

- Bedriftsprofiler som egen enhet, med intensjoner («Dette ser vi
  etter») og kontaktpersoner
- Strukturerte behov/tilbud med frist, budsjett og kompetanse
- Varme introduksjoner med godkjenning og statuskjede
- Community-managerens kontrollpanel og «trenger oppmerksomhet»
- Måling av forretningsverdi (matcher, møter, avtaler) - ikke likes
- Norsk språk, norske maler (håndbok, Onsdagspitch-mønsteret) og
  norsk marked
- AI-assistent med kildehenvisninger over husets eget innhold

Trusselvurderingen er dermed klar: et coworking-hus *kan* bruke
FluentCommunity som sosial vegg - men da har de fortsatt ingen
B2B-matching, og det er den som skaper målbar verdi for gårdeier og
leietakere. Vår markedsføring bør si akkurat det: «community-plugins
gir dere en vegg; vi gir dere forretning mellom medlemmene».

## 7. Oppsummert: endringer inn i planen

| Endring | Fase |
| --- | --- |
| Hybrid datamodell (egne tabeller for vegg/reaksjoner/koblinger/varsler) | 1 |
| Versjonert REST-navnerom + navngitte, dokumenterte hooks | 1 |
| Mentions, søk og reaksjoner inn i MVP | 2 |
| CSV-import av medlemmer og bedrifter | 2 |
| In-app-varsler for behov-svar og introduksjoner, + digest | 3 |
| Avstemninger | 3 |
| Driftskrav-dokumentasjon og referansebenchmark | 0/4 |
| Freemium-beslutning som eksplisitt fase 0-punkt | 0 |
| Utviklerdocs, sammenligningsmarkedsføring, S3-abstraksjon | Senere |

## Kilder

- [fluentcommunity.co](https://fluentcommunity.co/) (produkt, funksjoner, prising)
- [fluentcommunity.co/features](https://fluentcommunity.co/features/)
- [fluentcommunity.co/circle-vs-fluentcommunity](https://fluentcommunity.co/circle-vs-fluentcommunity/)
- [dev.fluentcommunity.co](https://dev.fluentcommunity.co/) (arkitektur, datamodell, hooks, REST, drift)
- [BuddyBoss vs FluentCommunity (BuddyBoss)](https://buddyboss.com/blog/buddyboss-vs-fluentcommunity/)
- [FluentCommunity Review (Blog Marketing Academy)](https://www.blogmarketingacademy.com/fluentcommunity-review/)
