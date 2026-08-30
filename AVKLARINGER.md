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

5. **2026-08-30 - G1/G2: Hva får motparten vite ved «nei takk»?
   AVGJORT.** Kay valgte nøytralt varsel: «koblingen ble ikke noe
   av denne gangen», uten navn. Community-manageren ser detaljene
   i kontrollpanelet. Et navngitt avslag mellom naboer i samme hus
   koster mer sosialt enn det informerer.

6. **2026-08-30 - G1: Automatisk eller CM-styrt «introdusert»?
   AVGJORT.** Kay valgte at community-manageren beholder
   introdusert-steget og utfører introduksjonen selv. Decket
   selger nettopp mennesket i løkken («community-manageren
   kvalitetssikrer»), og uten møtebooking i scope er
   introduksjonen uansett en menneskelig handling. G1 står som
   skrevet.

7. **2026-08-30 - G6: Ubesvart-køen mot «logges aldri»-løftet.
   AVGJORT.** Decket (slide 10) lover at ubesvarte spørsmål samles
   til community-manageren; koden og README lovet at spørsmål og
   svar aldri logges (F3). Kay valgte alternativ (a): anonym
   ubesvart-kø som innstilling, standard på - kun spørsmålstekst,
   aldri bruker-ID og aldri svaret - og personvernløftet
   omformuleres til «samtaler logges aldri; ubesvarte spørsmål
   lagres anonymt når innstillingen er på». G6 er avblokkert.

8. **2026-08-30 - Slide 6/7: Deck-løfter utenfor pluginens scope.
   AVGJORT.** To løfter i decket kan ikke lukkes av fase G: «Kort
   møte bookes direkte» (slide 6, steg 5 - booking er eksplisitt
   utenfor scope i CLAUDE.md) og gårdeier-metrikkene (slide 7:
   fornyelsesgrad, frafall, bruk av lokaler - krever leiekontrakt-
   og adgangsdata som bor i andre systemer). Kay valgte å justere
   decket nå: «møte bookes direkte» blir «møte avtales direkte»,
   og gårdeier-boksen merkes som integrasjonsveikart.
   Integrasjonssporet venter på erfaringene fra Lius-piloten;
   deck-justeringen fanges opp av G8s sluttnotat.
