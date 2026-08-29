# Sikkerhetsgjennomgang (D3)

Systematisk gjennomgang av alle endepunkter og flater, utført
2026-08-29 mot kjørende WordPress i testriggen. Metode: adversariell
kodegjennomgang av hele pluginen (agentbasert, med uavhengig
verifisering av hvert funn), fulgt av HTTP-verifisering av
rettelsene. Statusene under er bekreftet i kode og over HTTP.

## Flater og bekreftet status

| Flate | Vern | Status |
| --- | --- | --- |
| Portal-ruter (`/portal/…`) | Innloggingsport på template_redirect, noindex-meta + X-Robots-Tag, eget skall | OK |
| Skjema: nytt behov (forms.php) | Nonce + `samlab_create_behov`, feltvis sanitering, bedriftskobling validert mot kontaktperson-eierskap | OK |
| Skjema: vegginnlegg m/bilde | Nonce + `samlab_post_wall` + `upload_files`; `media_handle_upload` (kjernens MIME-kontroll) | OK |
| Skjema: kommentar | Nonce + `samlab_read_portal`, målinnlegg må være publisert; escaped ved visning | OK |
| Skjema: moderering (fest/skjul) | Nonce + `samlab_pin_posts` / `samlab_hide_content` per handling | OK |
| Metabokser: bedrift, behov, håndbok-flagg | Nonce + `edit_post`-sjekk + feltvis sanitering i alle tre save-handlere | OK |
| `map_meta_cap` for bedrift | Kontaktperson → `samlab_edit_bedrift`; andre `do_not_allow`; sletting kun admin/redaktør | OK |
| REST `POST samlab/v1/reaksjoner` | Cookie + `wp_rest`-nonce, innlogging (401) + `samlab_read_portal` (403), objektvalidering (404) | OK |
| REST `GET samlab/v1/brukere` | Samme auth; **rettet:** avgrenset med `capability => samlab_read_portal` så kontoer utenfor portalen ikke kan høstes | OK (rettet) |
| Håndbok-sider utenfor portalen | **Rettet:** permalenke 301-er til portalruten (bak porten), utelatt fra sitemap, offentlig søk og anonym REST (liste + 401 på enkeltoppslag) | OK (rettet) |
| Innstillingsside | `manage_options`, Settings API-nonce, feltvis sanitering (`sanitize_title`/`sanitize_hex_color`/`esc_url_raw`) | OK |
| Kobling-CPT (E1) | Egne capability-primitiver (`edit_samlab_koblinger` m.fl., kun moderator+); parter mappes til ren lesing via `map_meta_cap`, andre `do_not_allow`; metaboks med nonce + `edit_post`; partvalidering mot post-type/bruker | OK |
| REST `GET/POST samlab/v1/varsler[/lest]` (E2) | Cookie + nonce, `samlab_read_portal`; svarer kun med innlogget brukers egne varsler (user_id fra sesjonen, aldri parameter); prepared statements i modellen; kaskadesletting ved objektsletting | OK |
| Kontrollpanelet (E3) | Menyside bak `edit_samlab_koblinger` (moderator+); handlinger via admin-post med nonce og `edit_post`-sjekk per kobling i `samlab_kontrollpanel_utfor` (returnerer WP_Error, testbar); HTTP-verifisert: medlem 403, utlogget til innlogging | OK |
| Matching-cron (E4) | Ingen brukerinput og ingen HTTP-flate: leses kun fra publiserte behov/bedrifter, oppretter koblinger via validert `samlab_opprett_kobling` med status foreslått (aldri automatisk introduksjon), dedup via match-meta (avvist gjenoppstår ikke); kjøres av WP-cron eller WP_CLI | OK |
| Egne tabeller (innlegg/reaksjoner) | `wpdb::prepare`/insert/update/delete med formatlister overalt; tabellnavn kun fra kode | OK |
| Templates/output | `esc_html`/`esc_attr`/`esc_url`/`esc_textarea`/`wp_kses_post` (kses sist, etter mention-/anker-transformasjon); allowlistet template-include | OK |
| Tema-CSS-broen i skallet | Verdivask til trygt tegnsett (ingen `<>;{}`), `sanitize_hex_color` på aksent; bekreftet ikke-escapbar | OK |
| Mentions-rendring | `esc_url` + `esc_html` i lenken, `wp_kses_post` kjøres etterpå; XSS-forsøk via attributter sporet og avvist | OK |
| Seed/uninstall | Kun WP_CLI / `WP_UNINSTALL_PLUGIN`-vakt; tilfeldige passord; sletting avgrenset til seed-merket innhold | OK |
| CI/testrigg | Kun lint, ingen hemmeligheter, ingen `pull_request_target` | OK |

## Funn og utfall

1. **Håndbok-sider var offentlige utenfor portalen** (middels;
   bekreftet 8/10). Merkede sider lå åpne på ordinær permalenke, i
   sitemap, søk og `/wp/v2/pages` - i strid med kravet om at
   portalinnhold er utenfor offentlige flater. **Rettet** i
   `includes/access.php`: kanonisk 301 til portalruten (dermed bak
   innloggingsporten), ekskludering fra sitemap, offentlig søk og
   anonym REST. HTTP-verifisert: permalenke 301, sitemap/søk/REST
   uten treff, anonymt enkeltoppslag 401, innlogget portalvisning
   uendret.
2. **Brukeroppramsing via `GET samlab/v1/brukere`** (lav; 4/10).
   Endepunktet returnerte `user_login` for alle kontoer, også
   admin-kontoer utenfor portalen. **Rettet** med
   `capability => samlab_read_portal` - kun portaldeltakere
   foreslås, som også er riktig produktatferd.
3. **Uescaped tittel på behovskortet** (lav; 3/10 - ikke utnyttbar i
   dag pga. `sanitize_text_field` og capability-krav). **Rettet**
   til `esc_html( get_the_title() )` for konsistens.

## Aksepterte restrisikoer (trusselmodell)

- **Mediefiler er offentlige.** Bilder på veggen (og bedriftslogoer)
  serveres fra `wp-content/uploads/` på gjettbare URL-er utenfor
  innloggingsporten - iboende i WordPress' mediemodell (3/10,
  dokumenteres fremfor kodefix). Ikke last opp sensitive dokumenter
  som bilder. Mulige senere tiltak: avgrense til `image/*` og
  tilfeldige filnavn; ekte vern krever server-nivå-regler.
- **Sidetitler i temaets navigasjon.** Temaer som lister alle sider i
  menyen (f.eks. TT25s sideliste) viser håndbok-sidens *tittel*;
  lenken går til innlogging. Unngås ved å holde håndbok-sider ute av
  menyer, eller aksepteres.
- **Brukernavn er halve legitimasjonen.** Portalmedlemmer ser
  hverandres visningsnavn og brukernavn (mentions) - etter
  utformingen. Bruk sterke passord/2FA for admin-kontoer.

## Gjenta gjennomgangen

Kjør røyk-testene i `tests/rigg/` og verifiser flatetabellen over
mot kjørende installasjon (`bin/testrigg.sh` + `wp samlab seed`).
Nye endepunkter og skjemaer skal inn i tabellen i samme endring som
de innføres.
