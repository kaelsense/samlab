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
