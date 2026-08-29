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
