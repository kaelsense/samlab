# Samlab

WordPress-plugin under utvikling: en intern community-portal for
coworking-hus og kontorfellesskap - bedriftskatalog, behov og tilbud,
vegg og håndbok. Digitelle AS eier produktet.

Se `HANDOVER.md` og `handover/docs/forslag-wordpress-utvidelse.md`
for plan og konsept. Arbeidslisten for autonome utviklingsrunder
ligger i `BACKLOG.md`; åpne spørsmål og veivalg i `AVKLARINGER.md`.

## Utviklingsmiljø (wp-env)

Krav: Node.js (LTS), Docker.

```
npx wp-env start
```

Dette starter nyeste stabile WordPress på PHP 8.2 med pluginen
montert fra `samlab/` og standardtemaet Twenty Twenty-Four
(konfigurasjon i `.wp-env.json`). Nettstedet svarer på
`http://localhost:8888`, wp-admin på `http://localhost:8888/wp-admin`
(bruker `admin`, passord `password`). Aktiver pluginen under
Utvidelser i wp-admin, eller med:

```
npx wp-env run cli wp plugin activate samlab
```

Stopp miljøet med `npx wp-env stop`; `npx wp-env destroy` fjerner
det helt.
