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

## Gjenta testen

```
bin/testrigg.sh
wp samlab seed
# aktiver ønsket tema, ta skjermbilder av /portal/-flatene
```
