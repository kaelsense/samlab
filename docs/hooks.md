# Samlab - actions og filters

Dokumentasjon av pluginens hooks. Navnekonvensjon: alle hooks har
prefikset `samlab_`, navngis på norsk bokmål i preteritum for
hendelser («_endret», «_opprettet») og dokumenteres her i samme
endring som de innføres. Hooks er en API-flate: navn og signaturer
endres ikke uten versjonsbump og oppføring her.

## REST-API

Navnerom: `samlab/v1`. Alle skrivende endepunkter bruker WordPress'
cookie-autentisering med `X-WP-Nonce` (`wp_rest`-nonce) og
capability-sjekk i `permission_callback`.

### `POST /wp-json/samlab/v1/reaksjoner`

Slår en reaksjon av/på for innlogget bruker. Krever
`samlab_read_portal`.

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `object_type` | string | `innlegg` (standard) eller `kommentar` |
| `object_id` | int | Objektets ID (påkrevd) |
| `reaction` | string | Reaksjonsnøkkel, standard `like` |

Svar: `{ object_type, object_id, reaction, reacted, counts }` der
`reacted` sier om reaksjonen ble lagt til (`true`) eller fjernet,
og `counts` er antall per reaksjonsnøkkel. Nøkkelen `lest` er
reservert for lesebekreftelser og avvises her med 400 - bruk
`/lest`-endepunktet.

Eksempel fra nettleser-konsollen i portalen:

```js
fetch('/wp-json/samlab/v1/reaksjoner', {
	method: 'POST',
	credentials: 'same-origin',
	headers: {
		'Content-Type': 'application/json',
		'X-WP-Nonce': samlabNonce, // gjøres tilgjengelig i skallet i C-fasen
	},
	body: JSON.stringify( { object_id: 1 } ),
} ).then( ( r ) => r.json() ).then( console.log );
```

### `GET /wp-json/samlab/v1/brukere`

Brukerforslag til @-mentions. Krever `samlab_read_portal`.

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `sok` | string | Søkestreng mot brukernavn og visningsnavn (påkrevd) |

Svar: liste av `{ login, navn }`, maks 8.

### `POST /wp-json/samlab/v1/lest`

Bekrefter at innlogget bruker har lest et festet oppslag med
lest-krav. Én bekreftelse per medlem; gjentatte kall er idempotente
og kan aldri trekke bekreftelsen tilbake. Krever
`samlab_read_portal`.

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `innlegg_id` | int | Oppslaget med lest-krav (påkrevd) |

Svar: `{ innlegg_id, bekreftet, allerede, antall }` der `allerede`
sier om bekreftelsen fantes fra før og `antall` er totalt antall
bekreftelser. 404 når oppslaget ikke finnes eller ikke krever
lesebekreftelse.

### `POST /wp-json/samlab/v1/stemmer`

Avgir eller endrer innlogget brukers stemme i en avstemning på et
vegginnlegg (én stemme per medlem, ny stemme erstatter den gamle).
Krever `samlab_read_portal`.

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `innlegg_id` | int | Innlegget med avstemningen (påkrevd) |
| `valg` | int | Alternativindeks 0-4 (påkrevd) |

Svar: `{ innlegg_id, valg, counts, totalt }` der `counts` er antall
stemmer per alternativ (indeksert liste) og `totalt` er summen -
resultatvisningen etter avgitt stemme. 404 uten avstemning på
innlegget, 400 ved indeks utenfor alternativene.

### `POST /wp-json/samlab/v1/assistent`

Assistentens chat-endepunkt (kun når assistent-modulen er på -
ellers finnes ikke ruten). Kaller Claude Messages API server-side;
nøkkelen forlater aldri serveren. Krever `samlab_read_portal`.

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `melding` | string | Medlemmets spørsmål, maks 4000 tegn (påkrevd) |
| `historikk` | array | Valgfri samtalehistorikk: `[{ rolle: user\|assistant, tekst }]` - avgrenses server-side til de siste 10 |

Historikken normaliseres server-side til formen Messages API-et
krever: ukjente roller og tomme innslag fjernes, to like roller på
rad kollapses (nyeste beholdes), og listen trimmes så den starter med
`user` og slutter med `assistant` før medlemmets nye melding legges
til.

Svar: `{ svar, navn }`. Feilsvar: 503 uten API-nøkkel, 400 når
meldingen er tom etter sanitering, 429 over rate-grensen (15 kall per
5 minutter per bruker), 502 ved API-feil - alle med generiske
meldinger uten konfigurasjonsdetaljer. Spørsmål og svar logges
aldri.

**Mulig oppgradering: SSE-streaming.** Widgeten leverer i dag hele
svaret i ett (planens plan B). Streaming ville gitt ord-for-ord-
visning: sett `"stream": true` i Messages API-kallet, les
`text/event-stream`-svaret server-side og videresend deltaene til
klienten som Server-Sent Events (`Content-Type: text/event-stream`,
`X-Accel-Buffering: no`, flush per delta; klienten bytter fetch mot
`EventSource`/`ReadableStream`). Krever at webhotellet ikke bufrer:
**testoppskrift** - legg en midlertidig PHP-fil på serveren som
sender `echo "data: $i\n\n"; flush();` ti ganger med ett sekunds
mellomrom; kommer tallene enkeltvis i nettleseren støtter oppsettet
SSE, kommer alle på én gang bufrer proxy/FastCGI (typisk fiks:
`fastcgi_buffering off`/`X-Accel-Buffering: no` i nginx, eller
`output_buffering = Off` i PHP-FPM). Bygges ikke nå.

### `GET /wp-json/samlab/v1/varsler`

Innlogget brukers varsler (maks 20, nyeste først) med uleste-teller.
Krever `samlab_read_portal`. Svar:
`{ varsler: [{ id, tekst, lenke, tid, lest }], uleste }`.

### `POST /wp-json/samlab/v1/varsler/lest`

Markerer alle innlogget brukers varsler som lest. Samme auth.
Svar: `{ uleste: 0 }`.

Varseltyper: `mention`, `kommentar`, `reaksjon` (vegginnlegg) og
`kobling_godkjent`/`kobling_introdusert`/`kobling_fulgt_opp`
(partene varsles; foreslått/avvist er moderatorens arbeidsflate).

## Actions

### `samlab_reaksjon_endret`

Kjøres når en reaksjon er lagt til eller fjernet via REST.

```php
do_action( 'samlab_reaksjon_endret', $type, $obj_id, $user_id, $reaction, $reacted );
```

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `$type` | string | Objekttype: `innlegg` eller `kommentar` |
| `$obj_id` | int | Objektets ID |
| `$user_id` | int | Brukeren som reagerte |
| `$reaction` | string | Reaksjonsnøkkel, f.eks. `like` |
| `$reacted` | bool | `true` = lagt til, `false` = fjernet |

Siden: 0.1.0.

### `samlab_behov_opprettet`

Kjøres når et behov er opprettet fra portalens «nytt behov»-skjema
(ikke ved opprettelse i wp-admin).

```php
do_action( 'samlab_behov_opprettet', $behov_id, $user_id );
```

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `$behov_id` | int | Behovets post-ID |
| `$user_id` | int | Innsenderen |

Siden: 0.1.0.

### `samlab_innlegg_opprettet`

Kjøres når et vegginnlegg er opprettet fra portalen.

```php
do_action( 'samlab_innlegg_opprettet', $innlegg_id, $user_id );
```

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `$innlegg_id` | int | Innleggets ID i samlab_innlegg-tabellen |
| `$user_id` | int | Forfatteren |

Siden: 0.1.0.

### `samlab_arrangement_opprettet`

Kjøres når et arrangement er opprettet fra portalens «nytt
arrangement»-skjema (ikke ved opprettelse i wp-admin).

```php
do_action( 'samlab_arrangement_opprettet', $arrangement_id, $user_id );
```

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `$arrangement_id` | int | Arrangementets post-ID |
| `$user_id` | int | Innsenderen |

Siden: 0.2.0.

### `samlab_lest_bekreftet`

Kjøres når et medlem bekrefter å ha lest et oppslag - kun første
gang (gjentatte kall er idempotente og fyrer ikke på nytt).

```php
do_action( 'samlab_lest_bekreftet', $innlegg_id, $user_id );
```

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `$innlegg_id` | int | Oppslaget |
| `$user_id` | int | Medlemmet som bekreftet |

Siden: 0.2.0.

### `samlab_stemme_avgitt`

Kjøres når en stemme er avgitt eller endret via REST.

```php
do_action( 'samlab_stemme_avgitt', $innlegg_id, $user_id, $valg );
```

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `$innlegg_id` | int | Innlegget med avstemningen |
| `$user_id` | int | Brukeren som stemte |
| `$valg` | int | Alternativindeksen (0-basert) |

Siden: 0.2.0.

### `samlab_assistent_svarte`

Kjøres etter et vellykket assistent-svar. Sender bevisst ALDRI med
spørsmålet eller svaret - kun brukeren, til statistikkformål.

```php
do_action( 'samlab_assistent_svarte', $user_id );
```

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `$user_id` | int | Brukeren som spurte |

Siden: 0.2.0.

### `samlab_portal_bunn`

Kjøres nederst i portalskallet, før footeren - for innhold som skal
ligge på alle portalflater. Assistentens chat-widget hekter seg på
her når modulen er på.

```php
do_action( 'samlab_portal_bunn' );
```

Ingen parametre. Siden: 0.2.0.

### `samlab_kunnskap_bygget`

Kjøres etter at assistentens kunnskapsgrunnlag er bygget (daglig
cron `samlab_assistent_kunnskap`, «Bygg nå»-knappen eller `wp
samlab kunnskap`). Kun tilgjengelig når assistent-modulen er på.

```php
do_action( 'samlab_kunnskap_bygget', $grunnlag );
```

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `$grunnlag` | array | `versjon`, `bygget` (timestamp), `storrelse`, `tekst`, `kilder_ok`, `kilder_feilet` |

Siden: 0.2.0.

### `samlab_kobling_status_endret`

Kjøres når en kobling/introduksjon endrer status i statuskjeden
(foreslått → godkjent → introdusert → fulgt opp, eller avvist).

```php
do_action( 'samlab_kobling_status_endret', $kobling_id, $status, $gammel, $user_id );
```

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `$kobling_id` | int | Koblingens post-ID |
| `$status` | string | Ny status-slug |
| `$gammel` | string | Forrige status (tom streng ved opprettelse) |
| `$user_id` | int | Hvem som endret (0 = system/cron) |

Siden: 0.2.0.

### `samlab_matching_kjort`

Kjøres etter en runde regelbasert matching (daglig cron
`samlab_matching`, eller manuelt via `wp samlab match`), også når
runden ikke ga nye forslag.

```php
do_action( 'samlab_matching_kjort', $opprettet );
```

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `$opprettet` | int[] | Post-ID-ene til koblingene runden opprettet (tom array = ingen nye) |

Siden: 0.2.0.

### `samlab_ukesbrev_sendt`

Kjøres etter at et ukesbrev er sendt (daglig cron `samlab_ukesbrev`
på innstilt ukedag, eller manuelt via `wp samlab ukesbrev`). Kjøres
ikke når brevet var tomt og ble droppet.

```php
do_action( 'samlab_ukesbrev_sendt', $antall, $seksjoner );
```

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `$antall` | int | Antall e-poster sendt |
| `$seksjoner` | array | Seksjonene brevet inneholdt |

Siden: 0.2.0.

## Filters

### `samlab_ukesbrev_seksjoner`

Filtrerer ukesbrevets seksjoner før rendring og utsending. Hver
seksjon er `{ tittel: string, linjer: [{ tekst: string, url?:
string }] }`. E6 legger til kommende arrangementer her. Returner tom
array for å hindre utsending.

```php
apply_filters( 'samlab_ukesbrev_seksjoner', $seksjoner, $siden );
```

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `$seksjoner` | array | Seksjonene (tittel + linjer med tekst og ev. url) |
| `$siden` | int | Unix-tidspunktet brevet dekker fra (en uke tilbake) |

Siden: 0.2.0.

### `samlab_kunnskap_tidsbudsjett`

Filtrerer hvor lenge kunnskapsbygget bruker på å hente eksterne
kilder. Hentingen er seriell, og bygget kjøres av wp-cron over HTTP
der `max_execution_time` gjelder. Budsjettet måles fra bygget
starter, og hver kilde får det minste av kildetimeouten og tiden som
er igjen. Standard er 60 % av `max_execution_time`, eller 45 sekunder
når PHP ikke har noen kjøretidsgrense.

Når budsjettet tar slutt, starter neste bygg hentingen der dette
stoppet, slik at et fast budsjett ikke sulter ut de samme kildene
bygg etter bygg. Teksten fra forrige henting ligger i sin egen
option (`samlab_kunnskap_kilder`) og brukes for kilder som ikke
rekkes eller feiler - grunnlaget mister ikke innhold det allerede
hadde. En kilde uten tekst i grunnlaget rapporteres i
`kilder_feilet`.

```php
apply_filters( 'samlab_kunnskap_tidsbudsjett', $budsjett );
```

| Parameter | Type | Beskrivelse |
| --- | --- | --- |
| `$budsjett` | int | Sekunder til rådighet for hele kildehentingen |

Siden: 0.2.0.
