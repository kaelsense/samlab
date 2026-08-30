# Temakompatibilitet - gjennomgang (D1)

Portalen er testet mot tre temaer i testriggen
(`bin/testrigg.sh`, WordPress 7.1) med skjermbilder tatt i Chromium
(1200x900) som innlogget medlem. Skjermbildene ligger i
`docs/tema-test/` som `<tema>-<flate>.png` for flatene vegg,
bedrifter, profil, behov og handbok.

| Tema | Type | Brødtekst-kontrast | Aksent-kontrast (aktiv nav) | Font |
| --- | --- | --- | --- | --- |
| Twenty Twenty-Four | theme.json | 17.94 | 11.55 (#cfcabe / #111) | Inter (body-preset) |
| Twenty Twenty | klassisk, uten theme.json | 17.76 | 5.25 (#cd2653 / #fff) | system-ui (fallback) |
| Twenty Twenty-Five | theme.json | 18.88 | 15.85 (#ffee58 / #111) | Manrope (via stilbro) |

Alle målte kontraster er over WCAG AA-kravet (4.5:1). Kontrastene er
målt med getComputedStyle i nettleseren, ikke fra kildekoden.

## Funn og rettelser

1. **Hvit tekst på lys aksentfarge.** Aksent-kontrasten var hardkodet
   hvit; TT25s gule `#ffee58` ga 1.19 og TT24s beige `#cfcabe` ga
   1.63. Rettet med `samlab_portal_accent()` som leser temaets
   aksent fra paletten (eller innstillingen) og velger mørk eller
   lys kontrastfarge ut fra luminans.
2. **Aksent som ren tekst.** Kategorilinjer, mentions og
   festet-merket brukte aksentfargen som tekst på vanlig bakgrunn -
   uleselig med lyse aksenter. Nytt token `--samlab-aksent-tekst`
   faller tilbake til vanlig tekstfarge når aksenten er lys.
3. **Stilrekkefølge.** Skallets tema-bro og aksent-overstyringer ble
   skrevet ut før portal.css og tapte dermed mot stilarkets egne
   deklarasjoner (siste vinner ved lik spesifisitet). portal.css
   lenkes nå først; overstyringene kommer etter.

## Klassiske temaer

Twenty Twenty har ikke theme.json; portalen faller da tilbake til de
nøytrale tokene, men plukker opp temaets registrerte
editor-fargepalett (`accent`-slug) for aksentfargen. Layouten er
identisk med theme.json-temaene siden skallet er et eget dokument -
temaet påvirker kun tokens, aldri struktur.

## Admin-flatene (H): temauavhengige, men fargeskjema-tro

Testen over gjelder portalen. Admin-designet (fase H) lever i
wp-admin, der temaet ikke har noe å si - wp-admin stilsettes av
kjernen, ikke av det aktive temaet. Det er verdt å si rett ut framfor å la en
grønn temategest se ut som mer enn den er.

Gesten er likevel kjørt, og målt framfor påstått: samme fem flater
(innstillinger, kontrollpanel, rapport, koblingslisten,
bedriftseditoren) er lest med `getComputedStyle` under Twenty
Twenty-Five og Twenty Twenty-Four, for `body`, `.wrap`, `.wrap h1`,
`.postbox`, `table`, `.samlab-sammendrag` og `.samlab-tjeneste` -
farge, bakgrunn, skriftfamilie, skriftstørrelse og geometri, pluss
sidehøyde. Hver eneste målte verdi er **identisk** mellom de to
temaene, og
`#wpbody-content`-markupen er identisk med seg selv mellom to
lastinger. Skjermbildene av fem representative flater ligger i
`docs/tema-test/` som `admin-<flate>.png`.

*Om skjermbilder som bevis:* fire av de fem PNG-ene er bit for bit
like mellom temaene, den femte (koblingslisten) ikke - men den er
ulik seg selv mellom to lastinger under **samme** tema, med identisk
HTML. Fullside-PNG-er har altså støy i selve avfotograferingen. Det
er `getComputedStyle`-målingen og HTML-en som bærer konklusjonen her,
ikke bildefilene.

Den prøven som faktisk betyr noe for admin-laget er derfor en annen:
**brukerens fargeskjema**, ikke temaet.

| Fargeskjema | Kjernens meny | Kjernens primærknapp | Samlabs sammendragstall | Kjernens vanlige lenke |
| --- | --- | --- | --- | --- |
| Fresh | #1d2327 | #007cba | #2271b1 | #2271b1 |
| Modern | #1e1e1e | #3858e9 | #3858e9 | #3858e9 |
| Midnight | #333c42 | #cf4339 | #0073aa | #0073aa |
| Ocean | #39535a | #567958 | #0073aa | #0073aa |

Sammendragstallet har **samme farge som kjernens egen lenkefarge i
hvert eneste skjema**. Det er hele poenget med beslutningen om at
Samlab ikke maler interaktiv farge: fargen er arvet, ikke gjettet.
Ingen Samlab-flate holder seg fresh-blå når resten av skjermen er
Midnight.

`admin.css` har tre fargedeklarasjoner i alt, og ingen av dem
ligger på et interaktivt element: en dempet tekstfarge og en
flatebakgrunn, begge `--wpds-*` med nøytral fallback, pluss den
klebrige lagre-radens `#f0f0f0` - den eneste ubetingede verdien.
Målt i nettleser er kroppsbakgrunnen `#f0f0f1` i Fresh og `#f0f0f0`
i Modern, Midnight og Ocean, så raden treffer eksakt i tre av fire
og ligger ett trinn unna i den fjerde. Dette er chrome, ikke
interaktiv farge.

### Reflow på admin-flatene

Alle elleve Samlab-flater i wp-admin (tre egne sider, fire
listetabeller, fire editorer med metabokser) er målt ved 320 px:
`scrollWidth` er lik `clientWidth` på hver av dem, altså ingen
horisontal scroll (WCAG 1.4.10). Kroppsklassen og stilarket er
verifisert til stede på alle elleve, og konsollen er uten JS-feil.

## Gjenta testen

```
bin/testrigg.sh
wp samlab seed
# aktiver ønsket tema, ta skjermbilder av /portal/-flatene
```

For admin-flatene byttes fargeskjema på `profile.php` - samme vei som
en bruker går, ikke direkte i databasen - og `getComputedStyle` leses
på flatene i tabellen over, ved 320 px og 1280 px.
