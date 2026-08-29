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

## Filters

*(ingen ennå)*
