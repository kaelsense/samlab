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
og `counts` er antall per reaksjonsnøkkel.

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
