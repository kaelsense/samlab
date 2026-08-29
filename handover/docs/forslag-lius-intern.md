# Forslag: «Lius Intern» - community-plattform for huset

*Utkast til diskusjon, august 2026. Hører sammen med den klikkbare
designprototypen under `/intern/` i dette repoet.*

> **Merk:** Dette er versjon 1 av forslaget. Hovedretningen er
> videreutviklet i [forslag-b2b-community-os.md](./forslag-b2b-community-os.md),
> som dreier konseptet fra sosial feed til handlingsorientert
> B2B-matching. Kapitlene om roller, moderering, GDPR og utrulling
> under gjelder fortsatt.

---

## 1. Hva dette er

Lius trenger en intern side der leietakerne kan presentere seg selv og
tjenestene sine, og en community-funksjon som binder folkene i huset
tettere sammen. Dette dokumentet foreslår hvordan det kan fungere og
settes ut i praksis, og følges av en visuell prototype bygget 1:1 på
designsystemet til lius.no (samme farger, typografi og komponenter).

Prototypen består av fire sider:

| Side | URL i prototypen | Hva den viser |
| --- | --- | --- |
| Pulsen (feeden) | `/intern/` | Husets interne vegg: innlegg, bilder, arrangementer, nye medlemmer |
| Bedriftskatalog | `/intern/bedrifter/` | Alle bedriftene i huset med logo, kategori og kort presentasjon |
| Bedriftsprofil | `/intern/bedrifter/digitelle/` m.fl. | Én bedrifts egen seksjon: historie, tjenester, ansatte, bilder, siste innlegg |
| Administrasjon | `/intern/admin/` | Panelet for hovedadministrator og moderatorer |

Alt innhold i prototypen er eksempeldata: bedriftene er dels reelle
Lius-partnere (Digitelle, Advokatfirmaet Halvorsen & Co, Romerike
Sparebank, A til Å Regnskap, Mobit, Brectus, Lørenskog i Utvikling),
dels fiktive gründerbedrifter (Nabolab AS og Studio Nord) som er
tydelig merket. **Alle personer er fiktive.** Før noe publiseres må
hver bedrift levere og godkjenne sitt eget innhold.

## 2. Mål

1. **Synlighet innad:** Alle i huset skal lett kunne finne ut hvem de
   andre er, hva de tilbyr og hvem som jobber der. Naboene i huset er
   potensielle kunder, leverandører og samarbeidspartnere.
2. **Sosial samhørighet:** En felles vegg («Pulsen») der folk deler
   lanseringer, nyansettelser, arrangementer og hverdagsøyeblikk gjør
   huset til et fellesskap, ikke bare et kontorbygg.
3. **Lav terskel, lite vedlikehold:** Bedriftene eier sitt eget innhold,
   vertskapet modererer. Plattformen skal ikke bli enda et system som
   dør etter tre måneder - derfor er den bygget rundt få, enkle
   funksjoner.

## 3. Roller og rettigheter

| Rolle | Hvem | Kan |
| --- | --- | --- |
| **Hovedadministrator** | Lius vertskap (1-2 personer) | Alt: godkjenne medlemmer og bedrifter, utnevne moderatorer og redaktører, fjerne innhold, feste oppslag, endre innstillinger |
| **Moderator** | 2-4 frivillige fra bedriftene i huset | Godkjenne medlemmer, skjule rapportert innhold, feste oppslag, hjelpe nye i gang |
| **Bedriftsredaktør** | 1-2 per bedrift | Redigere egen bedriftsprofil (logo, historie, tjenester, ansatte, bilder), poste som bedriften |
| **Medlem** | Alle ansatte hos leietakerne | Se alt, poste på Pulsen, kommentere og reagere, redigere egen personprofil |

Prinsipper:

- Innmelding godkjennes manuelt (av hovedadministrator eller moderator),
  slik at plattformen garantert bare inneholder folk fra huset.
- Når en bedrift flytter ut, deaktiveres bedriftsprofilen og medlemmene
  dens. Innholdet slettes etter en karensperiode (forslag: 3 måneder).
- Moderatorer kan *skjule*, bare hovedadministrator kan *slette* -
  da er det alltid mulig å angre en for streng vurdering.

## 4. Funksjoner

### 4.1 Pulsen - den interne veggen (MVP)

- Innlegg med tekst og bilder (av ansatte, produkter, ting som skjer).
- Reaksjoner og kommentarer.
- Festede oppslag fra vertskapet (øverst, med gul markering).
- Sidekolonne med kommende arrangementer og nye medlemmer, slik at
  veggen også fungerer som husets oppslagstavle.

### 4.2 Bedriftsseksjoner (MVP)

Hver bedrift får sin egen side, redigert av egne redaktører:

- **Profilering:** logo, kategori, kort beskrivelse, lenke til nettside,
  hvor i huset de sitter.
- **Historie / om oss:** fritekst der bedriften forteller hvem de er,
  hvorfor de finnes og hvor de skal.
- **Tjenester og produkter:** korte kort med punktlister. Gründere kan
  også bruke feltet «Ser etter» (pilotkunder, ansatte, investorer).
- **Folkene:** ansatte med navn, tittel og bilde/avatar.
- **Bilder:** galleri med bilder av folk, produkter og hverdagen.
- **Siste innlegg:** bedriftens siste poster fra Pulsen, automatisk.

### 4.3 Senere versjoner (ikke i MVP)

- Arrangementer med påmelding (erstatter dagens sidekolonne-liste).
- Personkatalog med kompetansefelt («hvem i huset kan Webflow?»).
- Direktemeldinger eller kobling til husets eksisterende kanaler.
- Enkel markedsplass for medlemsfordeler bedriftene gir hverandre.

## 5. Moderering og kjøreregler

Foreslåtte kjøreregler (vises i prototypen under Administrasjon):

1. Plattformen er kun for medlemmer og ansatte i huset.
2. Innhold skal handle om huset, bedriftene og fellesskapet.
3. Spør alltid før du publiserer bilder der andre personer er
   gjenkjennbare.
4. Salg og tilbud til andre i huset er velkomment - ren massereklame
   er det ikke.
5. Moderatorene kan skjule innhold; hovedadministrator kan fjerne det.

Rapportert innhold havner i en kø i adminpanelet og skjules ved behov
til en moderator har vurdert det.

## 6. Personvern (GDPR)

- Plattformen er lukket: innhold er aldri synlig uten innlogging, og
  sidene indekseres ikke av søkemotorer.
- Bilder av ansatte krever samtykke fra den avbildede. Samtykket bør
  registreres på personprofilen (en enkel avkrysning ved opplasting),
  og trekkes tilbake ved å slette bildet.
- Hvert medlem eier sin egen profil og kan slette den selv. Når noen
  slutter, deaktiverer bedriftsredaktøren profilen.
- Databehandleravtale med plattformleverandøren (alternativ A) eller
  drifts-/skyleverandøren (alternativ B) må på plass før lansering.

## 7. Teknisk gjennomføring - to alternativer

### Alternativ A: Hyllevare-community (raskest i gang)

Bruk en ferdig community-tjeneste (f.eks. Circle, Mighty Networks eller
tilsvarende) med Lius-logo og -farger så langt tjenesten tillater.

- **Fordeler:** i drift på dager, apper og varslinger følger med,
  ingen utviklingskostnad, moderasjonsverktøy ferdig bygget.
- **Ulemper:** månedlig lisens, begrenset kontroll på design (vil ikke
  se ut som lius.no), data hos tredjepart, engelskspråklige flater.

### Alternativ B: Egen løsning på intern.lius.no (prototypen viser denne)

Bygg plattformen på samme stack som nettsiden, som en egen app på et
subdomene, med innlogging:

- **Frontend:** Astro (samme designsystem som lius.no - prototypen i
  dette repoet er i praksis starten på frontenden).
- **Backend:** en «backend as a service» som Supabase eller tilsvarende:
  autentisering (e-post/magisk lenke), database (bedrifter, medlemmer,
  innlegg, reaksjoner), fillagring (logoer og bilder) og
  tilgangsstyring på radnivå for rollene i kapittel 3.
- **Fordeler:** ser ut og føles som Lius, norsk språk hele veien, full
  kontroll på data og funksjoner, lav driftskostnad.
- **Ulemper:** må utvikles og vedlikeholdes; varslinger/app må lages
  eller løses med e-postoppsummeringer.

**Anbefaling:** Start med en tidsbegrenset pilot. Hvis dere vil teste
*konseptet* raskest mulig, kjør alternativ A i 3 måneder. Hvis dere
allerede vet at dere vil ha dette som en del av Lius-merkevaren, gå
rett på alternativ B - prototypen viser at designsystemet allerede
bærer hele flaten. Merk: dagens lius.no forblir uansett en ren statisk
side; internplattformen lever på eget subdomene med egen drift.

## 8. Utrulling i praksis

| Fase | Innhold | Varighet |
| --- | --- | --- |
| 1. Forankring | Vis prototypen for 3-5 leietakere, juster konseptet etter tilbakemeldingene | 2 uker |
| 2. Pilot | 5 bedrifter fyller ut profilene sine, Pulsen åpnes for pilotgruppen, vertskapet poster ukentlig | 4-6 uker |
| 3. Lansering | Alle leietakere inviteres, onboarding på husfrokost, hver bedrift får hjelp til første profilutkast | 2 uker |
| 4. Drift | Vertskapet fester ukens oppslag, moderatorene følger køen, profiler sjekkes halvårlig | løpende |

Suksessen avgjøres mer av fase 2-4 enn av teknologien: plattformen
lever bare hvis vertskapet bruker den aktivt selv (ukentlig innlegg,
arrangementer, «ukens bedrift») til vanen sitter hos medlemmene.

## 9. Eksempelbedriftene i prototypen

| Bedrift | Status | Brukt til å vise |
| --- | --- | --- |
| Digitelle | Reell kunnskapspartner for nettsider | Full profil med tjenester og kunnskapsdeling |
| Advokatfirmaet Halvorsen & Co | Reell partner | Profil + fagarrangement på Pulsen |
| Romerike Sparebank | Reell partner/initiativtaker | Drop-in-tilbud til huset |
| A til Å Regnskap | Reell partner | Enkel profil |
| Mobit | Reell partner | Praktiske hus-tips på Pulsen |
| Brectus | Reell partner | Produktvisning/medlemsfordel |
| Lørenskog i Utvikling | Reell initiativtaker | Vertskapsrollen og festede oppslag |
| Nabolab AS | **Fiktiv** | Gründerbedrift: lansering, historie, «ser etter»-felt |
| Studio Nord | **Fiktiv** | Enkeltpersonforetak med minimal profil |

Tekstene for de reelle bedriftene er markert som eksempeltekst i
prototypen og må erstattes av bedriftenes egne tekster.
