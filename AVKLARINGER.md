# Avklaringer

Spørsmål og veivalg loop-rundene ikke skal avgjøre selv. Loopen
skriver inn punkter her og merker oppgaven [BLOKKERT] i BACKLOG.md;
mennesker svarer i en interaktiv økt og fjerner blokkeringen.

Format per punkt: dato, oppgave-ID, spørsmålet, og gjerne loopens
anbefaling med begrunnelse.

---

1. **2026-08-29 - A1 (gjelder også A2, B1-B11, C1-C7, D1):**
   Loop-miljøet (Claude Code remote) kan ikke kjøre wp-env: docker
   fungerer, men containerne når ikke nettet gjennom øktens proxy,
   så image-bygget feiler. Alle «Ferdig når»-krav som forutsetter en
   kjørende WordPress kan dermed ikke verifiseres av loopen.
   Spørsmål: skal loopen (a) krysse av oppgaver på statisk
   verifisering alene (`php -l`, WPCS, YAML-lint) og la et menneske
   kjøre wp-env-testene lokalt i etterkant, eller (b) la alle slike
   oppgaver stå [BLOKKERT]?
   Anbefaling: (a) - koden i A1 er committet (eae15e1) og trivielt
   lav-risiko, og alternativ (b) stopper hele backloggen. Ved valg
   (a): kryss av A1 og fjern [BLOKKERT].
   *Svar (2026-08-29, Kay):* Loopen skal få mulighet til å verifisere
   selv. LØST: `bin/testrigg.sh` bygger WordPress på verten
   (wp-cli + SQLite-drop-in, uten docker) og brukes som
   verifiseringsgrunnlag i loop-miljøet. Verifisert: WordPress 7.1
   installerer, Samlab aktiveres uten feil. wp-env beholdes som
   oppskrift for lokal kjøring; A1 avkrysset, A2 venter kun på en
   lokal `npx wp-env start`-krysstest.

2. **2026-08-29 - Lisens (fase 0): AVGJORT.** Kay valgte
   GPL-2.0-or-later. Lagt inn i plugin-header og composer.json.

3. **2026-08-29 - Branch: AVGJORT.** Loopen fortsetter på
   `claude/backlog-tasks-loop-79kb3l`; merge til andre brancher gjøres
   av mennesker. Doc-stiene i CLAUDE.md er rettet til `handover/...`.

4. **2026-08-29 - Fase 3-4-planlegging: AVGJORT.** Interaktiv økt
   med Kay etter MVP-milepælen: (a) fase E får fullt scope -
   planens kjerne (koblinger, kontrollpanel, matching, ukesbrev)
   pluss alle analysenes tillegg (in-app-varsler, arrangementer +
   infoskjerm, lesebekreftelser, avstemninger); (b) hele
   assistentmodulen (fase F) bygges nå, med SSE utsatt til
   webhotell-test (hel-svar som standard); (c) samme autonome
   loop-regime som MVP-en. Backloggen fase E/F er kontrakten.

5. **2026-08-30 - G1/G2: Hva får motparten vite ved «nei takk»?**
   Når én part avslår en forespurt kobling settes den til avvist.
   Spørsmål: skal motparten få vite hvem som takket nei?
   Anbefaling: nei - nøytralt varsel («koblingen ble ikke noe av
   denne gangen») uten navn, mens community-manageren ser
   detaljene i kontrollpanelet. Et navngitt avslag mellom naboer i
   samme hus koster mer sosialt enn det informerer.

6. **2026-08-30 - G1: Automatisk eller CM-styrt «introdusert»?**
   Når begge parter har takket ja (status godkjent): skal systemet
   selv sette introdusert og dele kontaktinfo, eller skal
   community-manageren utføre introduksjonen som i dag?
   Anbefaling: CM beholder introdusert-steget. Decket selger
   nettopp mennesket i løkken («community-manageren
   kvalitetssikrer»), og uten møtebooking i scope er introduksjonen
   uansett en menneskelig handling. G1 er skrevet etter
   anbefalingen; sier Kay noe annet justeres kun
   status-automatikken i `samlab_kobling_svar`.

7. **2026-08-30 - G6: Ubesvart-køen mot «logges aldri»-løftet.**
   Decket (slide 10) lover at ubesvarte spørsmål samles til
   community-manageren; koden og README lover i dag at spørsmål og
   svar aldri logges (F3, et bevisst personvernvalg). Begge kan
   ikke stå. Spørsmål: (a) anonym ubesvart-kø som innstilling,
   standard på, med omformulert personvernløfte («samtaler logges
   aldri; ubesvarte spørsmål lagres anonymt når innstillingen er
   på»), (b) samme men standard av, eller (c) dropp køen og stryk
   løftet fra decket? Anbefaling: (a) - køen lagrer kun
   spørsmålstekst uten bruker-ID og uten svar, løkken er en
   bærende del av produktfortellingen, og innstillingen gir husene
   som vil noe annet en av-bryter. G6 står [BLOKKERT] til dette er
   avgjort.

8. **2026-08-30 - Slide 6/7: Deck-løfter utenfor pluginens scope.**
   To løfter i decket kan ikke lukkes av fase G: «Kort møte bookes
   direkte» (slide 6, steg 5 - booking er eksplisitt utenfor scope
   i CLAUDE.md; koblingsflaten kan lenke til husets eksisterende
   bookingløsning, men mer blir det ikke) og gårdeier-metrikkene
   (slide 7: fornyelsesgrad, frafall, bruk av lokaler - krever
   leiekontrakt- og adgangsdata som bor i andre systemer).
   Spørsmål: justere deck-formuleringene («møte avtales direkte»,
   gårdeier-boksen merkes som integrasjonsveikart), eller planlegge
   integrasjon/manuelle felter som egen fase senere? Anbefaling:
   juster decket nå (ærlig mot designpartnere), og la
   integrasjonssporet vente på erfaringene fra Lius-piloten.
