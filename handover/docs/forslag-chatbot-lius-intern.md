# Forslag: «Kimma» - chatbot på internsidene

*Implementeringsplan og grundig forslag til en lett implementasjon,
august 2026. Hører sammen med chat-mocken i internprototypen
(den grønne knappen nede til høyre på alle `/intern/`-sider).*

---

## 1. Hva vi skal bygge

En chatbot på internsidene som svarer på spørsmål basert på innholdet
fra tre kilder:

1. **lius.no** - den offentlige nettsiden (tilbud, priser, møterom,
   partnere, arrangementer)
2. **lorenskogiutvikling.no** - Lørenskog i Utviklings nettsted
3. **Internsidene** - håndboken («Praktisk»), bedriftskatalogen,
   behov & tilbud og arrangementene

Typiske spørsmål den skal svare på: «Hvordan booker jeg møterom?»,
«Hva koster fast parkeringsplass?», «Hvem i huset kan hjelpe meg med
regnskap?», «Når er neste Onsdagspitch?», «Hva er wifi-passordet?».

Navnet er **Kimma**.

## 2. Viktig begrepsavklaring: «trent på» betyr RAG, ikke trening

Boten skal ikke *trenes* (fintunes) på innholdet - det er dyrt, tregt
å oppdatere og unødvendig. Standardmetoden er at modellen får
innholdet som kontekst når den svarer, og instrueres om å kun svare
ut fra det. Da er boten alltid like oppdatert som kildene, og
oppdatering er å regenerere en tekstfil - ikke å trene en modell.

Det finnes to nivåer av dette:

| Nivå | Metode | Når |
| --- | --- | --- |
| **Nivå 0: Alt-i-kontekst** | Hele kunnskapsgrunnlaget sendes med i hver samtale (med prompt-caching så det er billig) | Innholdet er lite nok - vårt er det, anslagsvis 50-100 sider ≈ 40-80 000 tokens. **Anbefalt start.** |
| **Nivå 1: RAG med vektorsøk** | Innholdet deles i biter, indekseres med embeddings, og bare de mest relevante bitene hentes per spørsmål | Når innholdet vokser forbi ~150 000 tokens, eller når tilgangsstyring per bedrift trengs |

Hele poenget med den lette implementasjonen er å starte på nivå 0:
ingen vektordatabase, ingen indeksering, ingen ekstra infrastruktur -
bare en tekstfil, ett API-endepunkt og en widget.

## 3. Arkitektur for den lette implementasjonen

```
lius.no ────────┐
                │  (1) innholdsscript          (2) API-endepunkt
lorenskog- ─────┼──> kunnskap.md ──────> /api/chat (Vercel-funksjon)
iutvikling.no   │    (regenereres            │  systemprompt + kunnskap
                │     hver natt)             │  (prompt-cachet, 1 time)
internsidene ───┘                            v
(fra repoet)                          Claude API (streaming)
                                             │
                                     (3) chat-widget på /intern/
                                         svar + kildelenker
```

Tre byggeklosser, alle små:

### 3.1 Kunnskapsgrunnlaget: én generert markdown-fil

Et script (`scripts/bygg-kunnskap.ts`, kjøres i CI) bygger
`kunnskap.md`:

- **lius.no og lorenskogiutvikling.no**: hentes via sitemap.xml,
  HTML konverteres til ren tekst/markdown, meny og footer strippes.
- **Internsidene**: leses rett fra repoet (datafilene for bedrifter,
  behov, arrangementer og håndboken) - ingen crawling, alltid presist.
- Hver seksjon merkes med kilde-URL, slik at boten kan lenke dit:

  ```
  ## [Praktisk: Parkering](https://intern.lius.no/intern/praktisk/#parkering)
  Fast parkeringsplass i parkeringshuset kan bestilles. Pris: 1 286,- ...
  ```

- **Hemmeligheter går aldri inn i grunnlaget.** Wifi-passord og
  lignende holdes utenfor (internsidene bruker allerede plassholdere
  for dette). Boten svarer i stedet «passordet finner du under
  Praktisk når du er innlogget». Grunnlaget kan dermed behandles som
  ikke-sensitivt.

En natt-jobb (GitHub Action) regenererer filen og deployer, så
endringer på nettsidene plukkes opp automatisk. Endringer på
internsidene følger vanlige deploys.

### 3.2 API-endepunktet: én serverless-funksjon

Siden ligger allerede på Vercel, så endepunktet er én fil:
`/api/chat.ts` (100-150 linjer). Den:

1. Sjekker at brukeren er innlogget medlem (i piloten: en enkel
   delt token/cookie; i full løsning: sesjonen fra innloggingen).
2. Tar imot samtalehistorikken fra widgeten (historikk lagres kun i
   nettleseren - ingenting lagres på server i den lette versjonen).
3. Kaller Claude API med systemprompt + kunnskapsgrunnlag som cachet
   prefiks, og streamer svaret tilbake.

Kjernen, omtrentlig:

```typescript
import Anthropic from "@anthropic-ai/sdk";
const client = new Anthropic(); // ANTHROPIC_API_KEY i Vercel env

const stream = client.messages.stream({
  model: "claude-opus-5",
  max_tokens: 1024,
  system: [
    { type: "text", text: SYSTEMPROMPT },
    {
      type: "text",
      text: KUNNSKAP, // innholdet i kunnskap.md
      cache_control: { type: "ephemeral", ttl: "1h" },
    },
  ],
  messages: samtale, // historikk fra widgeten
});
```

`cache_control` gjør at kunnskapsgrunnlaget bare koster full pris
første gang i timen - alle påfølgende spørsmål leser det fra cache
til ca. 10 % av prisen. Det er dette som gjør alt-i-kontekst billig.

### 3.3 Widgeten: en liten øy på internsidene

- Flytende knapp nede til høyre på alle `/intern/`-sider (mocken i
  prototypen viser utseendet).
- Panel med samtale, streaming av svar, og **kildelenker** under hvert
  svar («Praktisk», «Bedrifter», «lius.no») så folk kan verifisere.
- Tre-fire forslagsknapper for vanlige spørsmål, så terskelen er lav.
- Historikk kun i nettleseren (sessionStorage); «ny samtale»-knapp.

### 3.4 Systemprompt (utkast)

> Du er Kimma, assistenten til Lius kunnskaps- og gründerhus i
> Lørenskog. Du svarer på norsk (bokmål), kort og vennlig.
>
> Du svarer KUN basert på kunnskapsgrunnlaget under. Hvis svaret ikke
> finnes der, si det ærlig og henvis til vertskapet
> (post@lius.no / +47 934 99 014). Ikke gjett på priser, datoer
> eller regler.
>
> Avslutt svar med kilden(e) du brukte, som lenke.
>
> Passord og innloggingsdetaljer står ikke i kunnskapsgrunnlaget -
> henvis til «Praktisk»-siden der medlemmer ser dem innlogget.
>
> Spørsmål som ikke handler om Lius, huset, medlemmene eller
> Lørenskog i Utvikling avviser du høflig.

## 4. Modellvalg og kostnad

Volumet er lite (rundt 42 medlemmer). Anslag: 500 spørsmål per måned,
kunnskapsgrunnlag på ~60 000 tokens, ~500 tokens per svar.

| Modell | Kvalitet | Ca. kost per spørsmål* | Ca. per måned |
| --- | --- | --- | --- |
| Claude Opus 5 (`claude-opus-5`) | Best - anbefalt i pilot | ~0,5 kr | ~250 kr |
| Claude Haiku 4.5 (`claude-haiku-4-5`) | God for faktaoppslag - vurderes etter piloten | ~0,1 kr | ~50 kr |

*Med prompt-caching (cache-lesing av grunnlaget + svar). I tillegg
kommer cache-skriving noen ganger per dag - småpenger på dette
volumet. Vercel-funksjonen ligger innenfor eksisterende plan.

Anbefaling: kjør piloten på Opus 5 så svarene er best mulige mens
folk danner seg et førsteinntrykk, og mål deretter om Haiku 4.5
holder samme opplevde kvalitet til en femtedel av prisen. Kostnaden
er uansett ikke driveren her - selv Opus-nivå koster mindre enn én
kaffe om dagen.

## 5. Personvern og sikkerhet

- **Bak innlogging**: Boten er kun tilgjengelig på internsidene.
- **Ingen sensitive data i grunnlaget**: passord/IP-er holdes utenfor
  (se 3.1). Grunnlaget består av innhold som enten er offentlig
  (nettsidene) eller synlig for alle i huset (internsidene).
- **Claude API bruker ikke innsendte data til å trene modeller.**
  Databehandleravtale (DPA) inngås med Anthropic; data behandles med
  30 dagers oppbevaring hos Anthropic.
- **Ingen samtalelagring på server** i den lette versjonen. Hvis vi
  senere vil logge spørsmål (anonymisert) for å forbedre FAQ-en, gjøres
  det med tydelig merking i widgeten.
- **Prompt injection**: lav risiko siden kunnskapsgrunnlaget bygges
  fra egne kilder, men systemprompten instruerer boten om å ignorere
  instruksjoner som står i innholdet.
- Boten skal aldri få verktøy som *endrer* noe (booking, innlegg) i
  den lette versjonen - den bare svarer og lenker.

## 6. Implementeringsplan

| Fase | Innhold | Tid |
| --- | --- | --- |
| **1. Kunnskapsgrunnlag** | Script som bygger `kunnskap.md` fra de tre kildene, manuell kvalitetssjekk av innholdet, natt-jobb i CI | 1-2 dager |
| **2. Endepunkt + widget** | `/api/chat` med innloggingssjekk og streaming, widget på internsidene, systemprompt | 2-3 dager |
| **3. Pilot** | 5-10 medlemmer tester i to uker. Alle svar har tommel opp/ned; vi samler spørsmålene boten ikke kunne svare på | 2 uker |
| **4. Justering** | Ubesvarte spørsmål mates inn i håndboken/FAQ-en (som igjen mater boten - en selvforsterkende løkke), systemprompt finpusses, modellvalg evalueres | 2-3 dager |
| **5. Lansering + drift** | Åpnes for alle medlemmer. Drift = natt-jobben går av seg selv; månedlig titt på tommel ned-svar | løpende |

Totalt utviklingsarbeid for pilot: **omtrent en ukes arbeid**.

## 7. Veien videre (ikke i den lette versjonen)

- **Nivå 1: RAG med vektorsøk** (Supabase pgvector) når innholdet
  vokser forbi konteksten, eller når internplattformen får innhold
  med tilgangsstyring per bedrift. Naturlig sammen med
  Supabase-sporet fra B2B Community OS-forslaget.
- **Handlinger**: «book møterom 4 i morgen kl. 10» → boten åpner
  forhåndsutfylt booking; «legg ut dette som behov» → utkast i
  Behov & tilbud. Krever verktøybruk og godkjenningssteg.
- **Flere flater**: samme endepunkt kan svare i Teams eller e-post
  (ukesbrevet), siden kunnskapsgrunnlaget og prompten gjenbrukes.
- **Åpen variant for lius.no**: samme opplegg minus interninnholdet
  kan svare besøkende på den offentlige siden.

## 8. Alternativ: hyllevare

Tjenester som Chatbase o.l. gir en «tren på nettsiden din»-bot på en
ettermiddag. Vurdert og ikke anbefalt her, fordi: månedsprisen ligger
fort på nivå med hele API-kostnaden vår, interninnholdet må lastes
opp til en tredjepart, skillet innlogget/åpent innhold blir vanskelig,
og widgeten kan ikke følge Lius-designet fullt ut. Den lette
egenimplementasjonen er liten nok (én tekstfil, én funksjon, én
widget) til at hyllevare ikke sparer nevneverdig tid.

## 9. Hva mocken i prototypen viser

Chat-knappen nede til høyre på alle `/intern/`-sider åpner et panel
som viser: velkomstmelding med forslagsknapper, et eksempelsvar med
kildelenke, og hvordan boten håndterer passordspørsmålet (henviser til
Praktisk-siden i stedet for å oppgi det). Alt er statisk eksempel -
ingen ekte modell er koblet til ennå.
