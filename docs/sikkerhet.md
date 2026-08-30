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
| Assistentinnstillinger (F1) | Samme Settings API-vern som resten av siden (`manage_options` + nonce, feltvis sanitering: modell-ID til trygt tegnsett, kilder kun http(s)-URL-er); API-nøkkelen leses KUN fra `SAMLAB_CLAUDE_API_KEY` i wp-config.php - aldri databasen, statusvisningen sier kun funnet/ikke funnet og røper aldri verdien (HTTP-verifisert med konstant satt); status-/overskriftsrader tar aldri imot POST-verdier; ingen assistent-kode lastes når modulen er av | OK |
| Chat-widgeten (F4) | **Rettet:** rendres kun for medlemmer som faktisk kan bruke den - innlogget, med `samlab_read_portal` OG med API-nøkkel satt (innloggingsporten slipper inn alle innloggede, så en knapp som alltid svarer 403/503 er fjernet); kun med modulen på (fraværende ellers, HTTP-verifisert); all PHP-output escaped (`esc_html`/`esc_attr`), all JS-DOM-skriving via `textContent` - aldri `innerHTML` med data; verifisert i ekte nettleser: HTML i API-svar og XSS-forsøk i meldinger vises som ren tekst og tolkes aldri | OK (rettet) |
| REST `POST samlab/v1/assistent` (F3) | Kun når modulen er på (ellers 404); cookie + nonce + `samlab_read_portal` (401/403); nøkkelen kun server-side som header mot API-et, aldri i svar; rate-limit per bruker (429) - atomisk `wp_cache_incr` med eksternt objektcache - feiler tellingen der, faller den ned på transient framfor å slippe kallet gjennom - ellers transient (**restrisiko:** uten objektcache kan noen få samtidige kall passere grensen; den er en kostnadsbrems, ikke et sikkerhetstiltak); historikk vaskes (rolle-whitelist - `system` avvises, tekst sanitert og kappet, maks 10 innslag) og normaliseres til vekslende roller; **rettet:** meldingen saniteres før lengdesjekken, tom melding gir 400 uten å nå API-et eller bruke rate-kvoten; generiske feilmeldinger uten konfigdetaljer (400/503/502, HTTP-verifisert); samtaler logges aldri - ubesvarte spørsmål lagres anonymt i køen når innstillingen er på (G6, egen rad) - ved API-feil kun statuskoden, kun med WP_DEBUG | OK (rettet) |
| Kunnskaps-cron (F2) | Lastes kun når modulen er på; bygger utelukkende fra publisert portalinnhold - kun håndbok-MERKEDE sider (aldri andre sider), passordbeskyttet innhold hoppes alltid over, persondata begrenset til kontaktpersoners visningsnavn; eksterne kilder hentes server-side med `wp_safe_remote_get` (**rettet:** SSRF - `wp_remote_get` lot en URL mot loopback eller interne adresser som skymetadata bli hentet og lagret i `samlab_kunnskap`, lesbart for ethvert medlem via assistenten) og strippes til tekst (inkl. script/style) med størrelsestak; seriell henting med tidsbudsjett målt fra byggets start (hver kilde kappes mot tiden som er igjen), roterende startpunkt så et stramt budsjett ikke sulter ut de samme kildene, og kildetekst fra forrige bygg i egen option som reserve; portalinnholdet lagres før kildene hentes, med forrige byggs kildetekst, så en avbrutt jobb verken etterlater grunnlaget uoppdatert eller dårligere enn før; «bygg nå» bak `manage_options` + nonce (403 uten, HTTP-verifisert); grunnlaget viser til innloggede sider for detaljer | OK (rettet) |
| Admin-stilark og repeater-JS (admin-design) | Statiske filer uten brukerinput, enqueuet via `wp_enqueue_style`/`wp_enqueue_script` og gatet på `get_current_screen()`, så de lastes kun på Samlabs egne skjermer - repeater-scriptet kun på bedriftseditoren. Malraden i `<script type="text/template">` rendres av **samme escapede render-funksjon** som de synlige radene, med tom data - den inneholder ingen lagrede verdier, kun markup og plassholderen `__i__`. JS-ens `innerHTML` på malen interpolerer derfor bare et tall (`String( teller++ )`), aldri brukerdata; øvrige DOM-endringer er `appendChild`/`remove`. Ingen ny HTTP-flate, ingen AJAX, ingen nonce-behov | OK |
| CPT-listetabeller (admin-design) | Kolonner, filtre og visninger går utelukkende gjennom core sine egne kroker (`manage_*_columns`, `restrict_manage_posts`, `pre_get_posts`, `views_edit-*`) - ingen egne ruter, ingen egne filtre. Hver `pre_get_posts` er gatet på `is_admin()`, `is_main_query()` og post-type, så andre spørringer på nettstedet står urørt. Filterverdier leses fra GET og saniteres med `sanitize_key`; de avgrenser kun en visning og endrer aldri noe, som i core selv (derfor ingen nonce). Statustellingen bak visningslenkene er én gruppert `$wpdb`-spørring uten brukerinput: post-statusene kommer fra `get_post_stati()` (kjernens eget «vises i alle»-utvalg, så tallet dekker nøyaktig det lenken viser), og siden antallet statuser varierer bygges plassholderne (`%s, %s, …`) mens alle verdier fortsatt sendes som argumenter til `prepare()` - ingen verdi interpoleres inn i SQL-en. **Flateendring verdt å merke:** kontaktperson- og bedriftskolonnene viser visningsnavn til enhver med `edit_posts` på vedkommende CPT - samme publikum som allerede kunne åpne posten og se det samme | OK |
| Infoskjermen (E9) | Nøkkel-URL uten innlogging (bevisst): 24-tegns tilfeldig nøkkel sammenlignet med `hash_equals`, feil/manglende nøkkel gir 404 (aldri videresending til innlogging), av som standard og av ved fjernet nøkkel; regenerering/fjerning bak `manage_options` + nonce (admin-post); noindex som header og meta; viser kun det veggen selv viser | OK |
| Kobling-CPT (E1) | Egne capability-primitiver (`edit_samlab_koblinger` m.fl., kun moderator+); parter mappes til ren lesing via `map_meta_cap`, andre `do_not_allow`; metaboksen er delt i to (redigerbare «Koblingsdetaljer» og skrivebeskyttet «Historikk» i sidespalten) - noncen og alle feltene ligger i den redigerbare, historikkboksen skriver ingenting og tar ikke imot POST; partvalidering mot post-type/bruker | OK |
| REST `POST samlab/v1/lest` + lest-krav (E8) | Samme auth som reaksjoner; 404 uten lest-krav på oppslaget; én bekreftelse per medlem (UNIQUE-nøkkel), idempotent og kan aldri trekkes tilbake - `lest`-nøkkelen er sperret i toggle-endepunktet (400); krev/fjern-handlingen bak nonce + `samlab_pin_posts`, kun på festede oppslag; oversikten bak `edit_samlab_koblinger` (medlem 403, HTTP-verifisert) | OK |
| REST `POST samlab/v1/stemmer` (E7) | Samme auth som reaksjoner (cookie + nonce + `samlab_read_portal`); innlegg må være publisert med avstemning (404), valg-indeks validert mot alternativene (400); én rad per medlem håndhevet med UNIQUE-nøkkel; avstemningsfelter saniteres i modellen (2-5 alternativer); HTTP-verifisert: 401 utlogget, 400/404, endring flytter tallet | OK |
| REST `GET/POST samlab/v1/varsler[/lest]` (E2) | Cookie + nonce, `samlab_read_portal`; svarer kun med innlogget brukers egne varsler (user_id fra sesjonen, aldri parameter); prepared statements i modellen; kaskadesletting ved objektsletting | OK |
| Kontrollpanelet (E3) | Menyside bak `edit_samlab_koblinger` (moderator+); handlinger via admin-post med nonce og `edit_post`-sjekk per kobling i `samlab_kontrollpanel_utfor` (returnerer WP_Error, testbar); HTTP-verifisert: medlem 403, utlogget til innlogging | OK |
| Skjema: nytt arrangement (E6) | Nonce + `samlab_create_arrangement`, feltvis sanitering (tid mot strengt format), arrangør-bedrift validert mot kontaktperson-eierskap (samme helper som behov); metaboks med egen nonce + `edit_post`; HTTP-verifisert: anonym til innlogging, feil nonce 403 | OK |
| Matching-cron (E4) | Ingen brukerinput og ingen HTTP-flate: leses kun fra publiserte behov/bedrifter, oppretter koblinger via validert `samlab_opprett_kobling` med status foreslått (aldri automatisk introduksjon), dedup via match-meta (avvist gjenoppstår ikke); kjøres av WP-cron eller WP_CLI | OK |
| Ukesbrev-cron (E5) | Ingen HTTP-flate; mottakere avgrenset til `samlab_read_portal` minus reserverte; innhold kun fra publisert portalinnhold, ren tekst (ingen HTML-injeksjon); innstillinger sanitert feltvis; reservasjonsfeltet på profilsiden lagres bak kjernens profil-nonce + `edit_user`-sjekk | OK |
| Ubesvart-køen (G6) | Deteksjon via [UBESVART]-markør i systemprompten, alltid strippet før svaret når medlemmet; lagrer KUN spørsmålstekst (sanitert, kappet til 500 tegn), dato og teller i option `samlab_ubesvart` (autoload av) - aldri bruker-ID og aldri svaret; dedupe på normalisert tekst, FIFO-tak på 200; innstilling standard på (avklaring 7) med klartekst om hva som lagres - av stopper all lagring; ingen egen HTTP-flate (skriving skjer kun server-side i F3-endepunktet) | OK |
| Ubesvart-køen i kontrollpanelet (G7) | Seksjonen bak `edit_samlab_koblinger` (samme side som kontrollpanelet), kun lastet når modulen er på; handlingene via admin-post med nonce + `edit_samlab_koblinger` - «legg i håndboken» krever i tillegg `edit_pages` (utkastet skal kunne redigeres av den som lager det; knappen skjules og handleren avviser uten - HTTP-verifisert 403, også uten nonce); utkastet opprettes som draft med spørsmålet som sanitert tittel og håndbok-flagget, og publiseres av et menneske før kunnskapsbygget tar det med; spørsmålstekster escapes i tabellen | OK |
| Rapportsiden + CSV-eksport (G5) | Undermeny bak `edit_samlab_koblinger` (HTTP-verifisert: medlem 403, anonym til innlogging, moderator 200); rendringen har egen capability-vakt i tillegg til menyens; CSV via admin-post med nonce + samme capability (403 uten, HTTP-verifisert); kun aggregater - aldri navn på hvem som gjorde hva, og aldri beløp; periodevalget valideres mot fast liste (30/90/365) | OK |
| Utfallsregistrering (G4): REST `POST …/koblinger/<id>/utfall`, kontrollpanel- og metaboks-vei | REST med samme partsvakt som svar-ruten (401/403/404, enum-validert utfall, notat kappet til 500 tegn, 409 før introdusert); kontrollpanel-veien bak samme nonce + `edit_post`-sjekk som øvrige handlinger, metaboks-veien bak metaboks-noncen; alle tre veier går gjennom `samlab_sett_kobling_utfall` med vaktene; kun kategori og notat lagres - aldri beløp (ikke noe felt for det); «ble det noe?»-påminnelsen sendes av cron uten brukerinput, én gang per kobling (meta-vakt) | OK |
| Koblingsflaten i portalen (G3) | Bak innloggingsporten som resten av portalen (nettleser-verifisert: utlogget sendes til innlogging); viser kun innlogget brukers egne koblinger via partskap-filteret, foreslåtte koblinger vises aldri for partene, motpartens kontaktinfo først fra godkjent; all output escaped (`esc_html`/`esc_attr`/`esc_url`), verifisert med script-payload i tittel/begrunnelse; svar-knappene poster mot G2-endepunktet med `wp_rest`-nonce; ukesbrev-seksjonen er aggregert antall uten navn (felles brev) | OK |
| REST `GET samlab/v1/koblinger` + `POST …/koblinger/<id>/svar` (G2) | Cookie + nonce, `samlab_read_portal` (401/403); listen filtrert på partskap fra sesjonen (aldri parameter) og deler aldri motpartens kontaktinfo før status godkjent (`motpart_kontakt` er `null` frem til begge har takket ja); svar-ruten krever i tillegg partskap i akkurat den koblingen (403, 404 for ukjent), svar valideres mot enum ja/nei, og svar utenfor forespurt gir 409 - statusløftet går kun gjennom `samlab_kobling_svar` med G1-vaktene; forespørsel-varselet bygges av tittel + begrunnelse og inneholder aldri kontaktinfo, nei-varselet til motparten er nøytralt uten avsender (aktør 0) | OK |
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

## Trusselnotat: assistenten (fase F)

- **Prompt-injeksjon fra portalinnhold.** Kunnskapsgrunnlaget
  bygges av innhold medlemmene selv skriver (behov, bedriftsfelter,
  håndbok) og av eksterne kilder - en ondsinnet tekst kan forsøke å
  instruere modellen («ignorer instruksene …»). Dette kan ikke
  utelukkes med filtrering, så vernet ligger i konsekvensbegrensning:
  assistenten har **aldri skrivetilgang** - ingen verktøy, ingen
  WordPress-API-er, ingen handlinger; den produserer kun tekst.
  Svaret escapes i widgeten (textContent), så heller ikke HTML/JS i
  et manipulert svar kan kjøre. Instruksblokken ligger først i
  systemprompten og ber modellen holde seg til grunnlaget. Verste
  realistiske utfall er et villedende svar til et innlogget medlem -
  synlig, rapporterbart og rettbart ved å fjerne kildeinnholdet og
  bygge grunnlaget på nytt.
- **Assistenten er kun for innloggede.** Endepunktet krever
  `samlab_read_portal`; kunnskapsgrunnlaget inneholder kun det
  portalmedlemmer uansett ser, og grunnlaget viser til innloggede
  sider for detaljer. Nøkkelen bor i wp-config.php og forlater
  aldri serveren. Samtaler logges aldri; ubesvarte spørsmål lagres
  anonymt i køen (kun spørsmålstekst, dato og teller - aldri hvem
  som spurte, aldri svaret) når innstillingen er på (G6).

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
- **Infoskjermens nøkkel står i URL-en.** Bevisst avveining for
  innloggingsfrie skjermenheter: nøkkelen kan havne i nettleser-
  historikk og serverlogger. Skjermen viser kun veggens eget
  innhold, er av som standard, og nøkkelen regenereres med ett
  klikk (gamle URL-er dør umiddelbart). Del den kun med skjermen.
- **Brukernavn er halve legitimasjonen.** Portalmedlemmer ser
  hverandres visningsnavn og brukernavn (mentions) - etter
  utformingen. Bruk sterke passord/2FA for admin-kontoer.

## Gjenta gjennomgangen

Kjør røyk-testene i `tests/rigg/` og verifiser flatetabellen over
mot kjørende installasjon (`bin/testrigg.sh` + `wp samlab seed`).
Nye endepunkter og skjemaer skal inn i tabellen i samme endring som
de innføres.
