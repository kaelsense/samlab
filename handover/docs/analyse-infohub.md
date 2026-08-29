# Konkurrentanalyse: Infohub (infohub.no)

*August 2026. Grunnlag: infohub.no (produkt-, pris-, kunde- og
om-sidene). Formål: lærdom som hever vår community-portal for norske
coworking- og kontorfellesskap - uten å kopiere og uten å skifte
retning.*

---

## 1. Hva Infohub er

- Norsk SaaS for **internkommunikasjon rettet mot ansatte i felt og
  drift** - produksjon, logistikk, bygg, retail, transport, renovasjon.
  App (iOS/Android) + nettleser.
- Grunnlagt 2012 av Robin Dominik Havre, eid av ham og
  seniorutvikler Torgny Walin. Kontorer i Oslo og Larvik, all
  utvikling i Norge. Produktet sprang ut av ti år med skreddersydde
  kommunikasjonsapper (Peab, Norsk Hydro) - innsikten var at «de
  enkleste løsningene fungerte best», og de produktifiserte deretter.
- Referansekunder: Eplehuset (300+ ansatte), Taxisentralen 02000,
  Schütz Nordic, Agaia, GLØR, Mapei, GK m.fl.

**Viktig avgrensning:** Infohub er ikke en direkte konkurrent. De
løser arbeidsgiver-til-ansatte-kommunikasjon i én bedrift; vi bygger
fellesskap og forretning *mellom* selvstendige bedrifter i et hus.
Men de er det beste norske eksempelet på hvordan man pakker, priser og
selger et internkommunikasjonsprodukt til norske virksomheter - og
der er det mye å hente.

## 2. Forretningsmodell og pakketering

- **69 kr per bruker per måned** (under 100 ansatte), volumrabatt
  over. Ingen etableringskostnad. Norsk onboarding og support
  inkludert.
- **30 dagers gratis prøve uten kort.** Månedsabonnement uten binding
  som opsjon (dyrere enn årsavtale). Faktura via EHF med 14 dagers
  frist.
- Forbruksbaserte tillegg der kostnaden er reell: SMS til 39 øre per
  melding. Betalte tilleggsmoduler: auto-oversettelse, Azure
  AD-synkronisering, Simployer-integrasjon, infoskjerm, intern
  podkast.
- **Tillitspakken er del av produktet**: data i Frankfurt (EU),
  databehandleravtale inkludert i prisen, GDPR-dokumentasjon, «norsk
  support - kun mennesker, ingen chatbots».

## 3. Funksjonsbildet

Nyheter/oppslag fra ledelsen (beskyttet mot å drukne i sosialt
innhold), ansattinnlegg, chat, push-varsler, SMS, undersøkelser/
avstemninger, quiz, kommentarer/likes, **lesebekreftelser og
lesestatistikk** («hvem har lest hva»), kunnskapsbase,
telefonkatalog, lenker til eksterne verktøy, **egendefinerte skjemaer**
(avvik, forbedringsforslag, nestenulykker), målrettet synlighet per
avdeling/gruppe, redaktørroller med avgrenset tilgang, logo/farger,
RSS, infoskjerm-visning.

## 4. Key takeaways - lærdom vi implementerer

1. **Tillitspakken for det norske markedet (fase 0/lansering).**
   Infohub selger EU-lagring, inkludert databehandleravtale og norsk
   support like hardt som funksjoner. Vi har en enda sterkere
   historie - **selvhostet WordPress betyr at dataene bor hos huset
   selv** - men den må sies eksplisitt: GDPR-side, DPA-mal for
   assistent-modulen (Anthropic-leddet), norsk support, «utviklet i
   Norge». I det norske SMB-markedet er dette ofte avgjørende, ikke
   funksjonslisten.
2. **Prisbenchmark i NOK (fase 0).** 69 kr/bruker/mnd er
   referansepunktet norske kjøpere kjenner. Et hus med 50-100
   medlemmer ville kostet 41 000-83 000 kr/år hos en per-bruker-SaaS.
   Vår flate årslisens per installasjon med ubegrensede medlemmer kan
   prises godt under dette og fortsatt være svært lønnsom - og
   «ubegrensede medlemmer, flat pris» blir et hovedargument.
   Vurder også: gratis prøveperiode uten kort og månedsopsjon uten
   binding - det senker terskelen målbart.
3. **Lesebekreftelser på viktige oppslag (fase 3).** Infohubs mest
   elskede funksjon hos ledere: dokumentasjon på at budskapet nådde
   frem. Vår variant: «bekreft lest» på festede oppslag fra
   vertskapet (brannrutiner, husregler, viktige endringer) med
   oversikt i kontrollpanelet. Liten funksjon, stor verdi for
   vertskap og gårdeier - og helt i tråd med vår måle-filosofi.
4. **Infoskjerm-visning (fase 3/4 - lavthengende frukt).**
   Coworking-hus har skjermer i fellesarealene. En read-only
   skjermvisning av veggen + kommende arrangementer (egen URL med
   visningstoken) er billig å bygge på vår arkitektur og gir portalen
   fysisk synlighet i huset hver dag. Dette er kanskje den beste
   enkeltideen å låne: den kobler den digitale portalen til det
   fysiske rommet.
5. **Meldingsskjemaer til vertskapet (fase 3).** Deres avviks- og
   forslagsskjemaer oversatt til vår verden: «meld inn»-knapp for
   feil på møterom, ønsker og forbedringsforslag, rett inn i
   kontrollpanelets «trenger oppmerksomhet»-liste. Enkel modul som
   gjør portalen til husets naturlige kanal for alt praktisk.
6. **Ledelsens innhold skjermes (fase 2-designprinsipp).** Infohub
   skiller bevisst vertskaps-/ledelsesinnhold fra sosialt innhold så
   det viktige ikke drukner. Vi har allerede festede oppslag - ta
   prinsippet videre: vertskapets oppslag har egen visuell kanal og
   kan ikke skyves ned av feed-støy.
7. **Målrettet synlighet (backlog).** Innhold per gruppe/avdeling hos
   dem; per etasje/medlemstype/gruppe hos oss. Naturlig sammen med
   faggrupper når de kommer.
8. **Redaktørroller med avgrenset tilgang (fase 1-bekreftelse).**
   Deres modell (full eller avdelingsavgrenset redaktør) bekrefter
   vår: bedriftsredaktører avgrenset til egen bedrift, håndhevet i
   capability-laget.
9. **Kundehistorier med bransje og tall (lansering).** Kundesidene
   deres er konkrete: bransje, antall ansatte, problemet som ble
   løst. Vår Lius-case med måltall fra piloten (matcher,
   introduksjoner, avtaler) blir salgsmotoren - planlegg datafangst
   fra dag én av piloten.
10. **Forbrukskostnad prises som forbruk (fase 0).** SMS til 39 øre
    hos dem; assistentens API-kostnad hos oss. Ikke bak inn
    AI-kostnaden i grunnprisen - la modulen ha egen pris eller
    kundens egen API-nøkkel, så forblir grunnproduktet billig og
    forutsigbart.
11. **Enkelhets-posisjonering (gjennomgående).** «Enkelhet foran
    trendy design», «ingen alt-i-ett-app» - tydelig, ærlig
    posisjonering som bygger tillit. Vår versjon: «vi erstatter ikke
    booking, adgang eller regnskap - vi gjør én ting godt:
    forretning og fellesskap mellom medlemmene».
12. **Auto-oversettelse (backlog).** Deres tillegg for internasjonal
    arbeidsstyrke minner om at engelsk UI-språkfil bør komme tidlig -
    internasjonale medlemmer finnes i norske coworking-hus også.

## 5. Hva vi bevisst IKKE tar med

- **Ikke SMS, chat eller push-app i v1** - vi er web-først (PWA), og
  kuraterte introduksjoner er vår kontaktmodell, ikke fri meldingsflyt.
- **Ikke quiz/kunnskapsbase som egne moduler** - håndboken dekker
  kunnskapsbehovet vårt.
- **Ikke per-bruker-prising** - flat pris per installasjon er vår
  motposisjon.
- Én kontrast verdt å merke: Infohub markedsfører «ingen chatbots» om
  supporten sin. Vi skiller tydelig: assistenten vår er et *produkt-
  tilbud til medlemmene* (valgfri modul) - support til kundene våre
  er mennesker.

## 6. Trusselvurdering

Lav direkte trussel. Infohub er bygget for én arbeidsgivers ansatte -
modellen deres har ingen bedriftsprofiler, ingen matching, ingen
introduksjoner, og per-bruker-prisingen passer dårlig for et hus med
mange små bedrifter. Skulle de bevege seg mot coworking-segmentet,
måtte de bygge om kjernemodellen. Vår differensiering står seg:
bedrifter som enhet, forretningsverdi mellom medlemmer, selvhostet
WordPress og flat pris.

Den reelle lærdommen er at de har **bevist betalingsvilje i det
norske markedet** for internkommunikasjon til SMB-segmentet - med et
lite team på to eiere. Det er godt nytt for oss.

## 7. Oppsummert: endringer inn i planen

| Endring | Fase |
| --- | --- |
| Tillitspakke: GDPR-side, DPA-mal for assistenten, «data bor hos dere»-budskap | 0/lansering |
| Prismodell: flat pris + «ubegrensede medlemmer» som argument; prøveperiode uten kort; AI som egen forbruks-/modulpris | 0 |
| Vertskapsinnhold skjermes visuelt fra feed-støy | 2 |
| «Bekreft lest» på festede oppslag + oversikt i kontrollpanelet | 3 |
| «Meld inn»-skjema til vertskapet (feil, ønsker, forslag) | 3 |
| Infoskjerm-visning av vegg + arrangementer (read-only URL) | 3/4 |
| Målrettet synlighet per gruppe, engelsk språkfil, auto-oversettelse | Backlog |
| Datafangst for Lius-casen (matcher, introduksjoner, avtaler) fra pilotstart | Pilot |

## Kilder

- [infohub.no](https://infohub.no/) (produkt, funksjoner, prising, posisjonering)
- [infohub.no/om-infohub](https://infohub.no/om-infohub/) (selskapsfakta, historie, filosofi)
