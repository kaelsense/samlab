/* --------------------------------------------------------------------------
   Eksempeldata for PROTOTYPEN av Lius' interne community-plattform.

   VIKTIG:
   - Alle PERSONER (navn, titler, innlegg, sitater) er fiktive eksempler.
   - Bedriftene er dels reelle Lius-partnere (brukt som illustrasjon på
     hvordan en bedriftsprofil kan se ut), dels helt fiktive
     eksempelbedrifter merket med `fiktiv: true`.
   - Ingenting av dette skal publiseres som reelt innhold uten at de
     omtalte bedriftene selv har levert og godkjent det.
   -------------------------------------------------------------------------- */

export interface Ansatt {
  navn: string;
  tittel: string;
  initialer: string;
  /* 1-4: fargevariant for initial-avatar */
  farge: number;
}

export interface Tjeneste {
  tittel: string;
  punkter: string[];
}

export interface Bedrift {
  slug: string;
  navn: string;
  kategori: string;
  kort: string;
  /* Logo-bilde hvis det finnes i repoet; ellers brukes initialer */
  logo?: string;
  initialer: string;
  farge: number;
  plass: string;
  nettside?: string;
  fiktiv?: boolean;
  om: string[];
  tjenester: Tjeneste[];
  ansatte: Ansatt[];
  bilder: string[];
}

export interface Innlegg {
  bedrift: string; /* slug */
  forfatter: Ansatt;
  tid: string;
  tekst: string;
  bilde?: string;
  bildeAlt?: string;
  liker: number;
  kommentarer: number;
  festet?: boolean;
}

export const bedrifter: Bedrift[] = [
  {
    slug: 'digitelle',
    navn: 'Digitelle',
    kategori: 'Nettsider - kunnskapspartner',
    kort: 'Kunnskapspartner for nettsider på Lius.',
    logo: '/img/partners/digitelle.svg',
    initialer: 'DI',
    farge: 1,
    plass: '3. etasje - fast kontorplass',
    nettside: 'https://digitelle.no/',
    om: [
      'Eksempeltekst: Her presenterer bedriften seg selv med egen tekst - historie, arbeidsmåte og hva de som kunnskapspartner kan hjelpe andre i huset med.',
    ],
    tjenester: [
      {
        tittel: 'Nettsider',
        punkter: ['Design', 'Utvikling', 'Drift og vedlikehold'],
      },
      {
        tittel: 'Kunnskapsdeling',
        punkter: ['Kurs og workshops for huset', 'Rådgivning for gründere'],
      },
    ],
    ansatte: [
      { navn: 'Vilde Aas', tittel: 'Rådgiver nettsider', initialer: 'VA', farge: 2 },
      { navn: 'Sander Holt', tittel: 'Utvikler', initialer: 'SH', farge: 3 },
    ],
    bilder: [
      '/img/hero/interior-1.jpg',
      '/img/arbeidsarena/moterom-3.jpg',
      '/img/hero/interior-2.jpg',
    ],
  },
  {
    slug: 'advokatfirmaet-halvorsen-co',
    navn: 'Advokatfirmaet Halvorsen & Co',
    kategori: 'Advokat',
    kort: 'Juridisk partner på Lius innen forretningsjus og rådgivning.',
    logo: '/img/partners/halvorsen-co.jpg',
    initialer: 'HC',
    farge: 2,
    plass: '2. etasje - partnerkontor',
    nettside: 'https://halvorsenco.no/',
    om: [
      'Eksempeltekst: Her presenterer bedriften seg selv med egen tekst - historie, fagområder og hva de kan hjelpe andre i huset med. Teksten leveres og eies av bedriften selv.',
    ],
    tjenester: [
      { tittel: 'Forretningsjus', punkter: ['Selskapsrett', 'Kontrakter', 'Arbeidsrett'] },
      { tittel: 'Rådgivning', punkter: ['Gründeravtaler', 'Tvisteløsning'] },
    ],
    ansatte: [
      { navn: 'Henrik Foss', tittel: 'Advokat / partner', initialer: 'HF', farge: 2 },
      { navn: 'Nora Lien', tittel: 'Advokatfullmektig', initialer: 'NL', farge: 3 },
      { navn: 'Kaja Storli', tittel: 'Advokatsekretær', initialer: 'KS', farge: 1 },
    ],
    bilder: ['/img/arbeidsarena/moterom-1.jpg', '/img/hero/interior-2.jpg'],
  },
  {
    slug: 'romerike-sparebank',
    navn: 'Romerike Sparebank',
    kategori: 'Bank',
    kort: 'Lokalbanken på Romerike og medinitiativtaker til Lius.',
    logo: '/img/partners/sparebank.jpg',
    initialer: 'RS',
    farge: 3,
    plass: '1. etasje - partnerkontor',
    nettside: 'https://rsbank.no/',
    om: [
      'Eksempeltekst: Her presenterer bedriften seg selv med egen tekst - historie, samfunnsrolle og tilbud til gründere og næringsliv i huset.',
    ],
    tjenester: [
      { tittel: 'Bank for næringsliv', punkter: ['Bedriftskonto', 'Finansiering', 'Rådgivning'] },
      { tittel: 'Gründertilbud', punkter: ['Oppstartslån', 'Drop-in banktime på Lius'] },
    ],
    ansatte: [
      { navn: 'Even Sandmo', tittel: 'Bedriftsrådgiver', initialer: 'ES', farge: 4 },
      { navn: 'Tuva Berg', tittel: 'Kunderådgiver', initialer: 'TB', farge: 1 },
    ],
    bilder: ['/img/hero/resepsjon.jpg', '/img/hero/interior-1.jpg'],
  },
  {
    slug: 'a-til-a-regnskap',
    navn: 'A til Å Regnskap',
    kategori: 'Regnskap og økonomistyring',
    kort: 'Regnskap, lønn og økonomistyring for små og mellomstore bedrifter.',
    logo: '/img/partners/regnskap.png',
    initialer: 'AÅ',
    farge: 4,
    plass: '3. etasje - fast kontorplass',
    om: [
      'Eksempeltekst: Her presenterer bedriften seg selv med egen tekst om historie, arbeidsmåte og hvem de passer for.',
    ],
    tjenester: [
      { tittel: 'Regnskap', punkter: ['Løpende regnskap', 'Årsoppgjør', 'Lønn'] },
      { tittel: 'Økonomistyring', punkter: ['Budsjett', 'Rapportering', 'Rådgivning'] },
    ],
    ansatte: [
      { navn: 'Oskar Meland', tittel: 'Autorisert regnskapsfører', initialer: 'OM', farge: 2 },
      { navn: 'Lea Nguyen', tittel: 'Regnskapskonsulent', initialer: 'LN', farge: 3 },
    ],
    bilder: ['/img/arbeidsarena/fast-bedrift.jpg'],
  },
  {
    slug: 'mobit',
    navn: 'Mobit',
    kategori: 'IT & AV-utstyr og løsninger',
    kort: 'IT-drift, utstyr og AV-løsninger - blant annet i møterommene på Lius.',
    logo: '/img/partners/mobit.png',
    initialer: 'MO',
    farge: 1,
    plass: '2. etasje - fast kontorplass',
    om: [
      'Eksempeltekst: Her presenterer bedriften seg selv med egen tekst om leveranser, kompetanse og tilbud til andre i huset.',
    ],
    tjenester: [
      { tittel: 'IT', punkter: ['Drift og support', 'Maskinvare', 'Sikkerhet'] },
      { tittel: 'AV-løsninger', punkter: ['Møteromsteknikk', 'Skjermer og lyd'] },
    ],
    ansatte: [
      { navn: 'Martin Grov', tittel: 'IT-konsulent', initialer: 'MG', farge: 3 },
      { navn: 'Silje Otterlei', tittel: 'Kundeansvarlig', initialer: 'SO', farge: 4 },
    ],
    bilder: ['/img/arbeidsarena/moterom-5.jpg', '/img/arbeidsarena/moterom-8.jpg'],
  },
  {
    slug: 'brectus',
    navn: 'Brectus',
    kategori: 'Profilering & messeutstyr',
    kort: 'Profileringsartikler, messeutstyr og synlighet for merkevarer.',
    logo: '/img/partners/brectus.jpg',
    initialer: 'BR',
    farge: 2,
    plass: 'Lounge-medlem',
    om: [
      'Eksempeltekst: Her presenterer bedriften seg selv med egen tekst om produkter, showroom og hva de kan hjelpe andre i huset med.',
    ],
    tjenester: [
      { tittel: 'Profilering', punkter: ['Profilklær', 'Gaveartikler', 'Trykk'] },
      { tittel: 'Messeutstyr', punkter: ['Messevegger', 'Roll-ups', 'Beachflagg'] },
    ],
    ansatte: [
      { navn: 'Amir Yusuf', tittel: 'Salgsansvarlig', initialer: 'AY', farge: 1 },
    ],
    bilder: ['/img/hero/slide1.jpg'],
  },
  {
    slug: 'lorenskog-i-utvikling',
    navn: 'Lørenskog i Utvikling',
    kategori: 'Stedsutvikling',
    kort: 'Medinitiativtaker til Lius og pådriver for utviklingen av Lørenskog.',
    initialer: 'LU',
    farge: 3,
    plass: '1. etasje - vertskap',
    om: [
      'Eksempeltekst: Her presenterer organisasjonen seg selv med egen tekst om formål, prosjekter og rollen som vertskap i huset.',
    ],
    tjenester: [
      { tittel: 'Stedsutvikling', punkter: ['Sentrumsutvikling', 'Nettverk', 'Arrangementer'] },
    ],
    ansatte: [
      { navn: 'Ingrid Bakken', tittel: 'Prosjektleder', initialer: 'IB', farge: 4 },
      { navn: 'Jon Erik Moe', tittel: 'Rådgiver', initialer: 'JM', farge: 2 },
    ],
    bilder: ['/img/hero/streetview.jpg', '/img/hero/bird-view.jpg'],
  },
  {
    slug: 'nabolab',
    navn: 'Nabolab AS',
    kategori: 'Apputvikling - gründerbedrift',
    kort: 'Fiktiv eksempelbedrift: tre gründere som bygger en app for nabolagstjenester.',
    initialer: 'NB',
    farge: 4,
    fiktiv: true,
    plass: 'Åpent landskap - gründerplass',
    om: [
      'Nabolab er en fiktiv eksempelbedrift i denne prototypen. Den viser hvordan en liten gründerbedrift kan bruke profilsiden sin: fortelle historien om hvorfor de startet, vise fram teamet og det de bygger, og legge ut bilder fra hverdagen i huset.',
      'Historie-seksjonen kan for eksempel fortelle: hvem grunnla selskapet, hva var ideen, hvor står de nå og hva ser de etter (kunder, ansatte, investorer eller samarbeidspartnere i huset).',
    ],
    tjenester: [
      { tittel: 'Produkt', punkter: ['Nabolagsapp (beta)', 'Pilotprosjekt med borettslag'] },
      { tittel: 'Ser etter', punkter: ['Pilotkunder', 'UX-designer (deltid)'] },
    ],
    ansatte: [
      { navn: 'Selma Ruud', tittel: 'Daglig leder / medgründer', initialer: 'SR', farge: 1 },
      { navn: 'David Okafor', tittel: 'CTO / medgründer', initialer: 'DO', farge: 2 },
      { navn: 'Emil Hovland', tittel: 'Utvikler', initialer: 'EH', farge: 3 },
    ],
    bilder: ['/img/arbeidsarena/lounge-grunder.jpg', '/img/arbeidsarena/fast-grunder.jpg'],
  },
  {
    slug: 'studio-nord',
    navn: 'Studio Nord',
    kategori: 'Foto og design - gründerbedrift',
    kort: 'Fiktiv eksempelbedrift: enkeltpersonforetak innen foto, film og visuell identitet.',
    initialer: 'SN',
    farge: 1,
    fiktiv: true,
    plass: 'Lounge-medlem',
    om: [
      'Studio Nord er en fiktiv eksempelbedrift i denne prototypen. Den viser hvordan et enkeltpersonforetak kan bruke plattformen: en enkel profil, portefølje-bilder og innlegg når det skjer noe nytt.',
    ],
    tjenester: [
      { tittel: 'Tjenester', punkter: ['Bedriftsfoto', 'Produktfoto', 'Visuell identitet'] },
    ],
    ansatte: [
      { navn: 'Aksel Winther', tittel: 'Fotograf / eier', initialer: 'AW', farge: 4 },
    ],
    bilder: ['/img/hero/podcast.jpg', '/img/arbeidsarena/podkaststudio-1.jpg'],
  },
];

export const vertskap: Ansatt = {
  navn: 'Lius vertskap',
  tittel: 'Hovedadministrator',
  initialer: 'LI',
  farge: 2,
};

export const innlegg: Innlegg[] = [
  {
    bedrift: 'lorenskog-i-utvikling',
    forfatter: vertskap,
    tid: 'Festet oppslag',
    tekst:
      'Velkommen til Pulsen - husets interne vegg! Her deler vi det som skjer på Lius: nye folk, arrangementer, lanseringer og små og store øyeblikk fra hverdagen. Vær rause med hverandre, og les kjørereglene før du legger ut bilder av andre.',
    liker: 34,
    kommentarer: 6,
    festet: true,
  },
  {
    bedrift: 'nabolab',
    forfatter: { navn: 'Selma Ruud', tittel: 'Nabolab AS', initialer: 'SR', farge: 1 },
    tid: 'I dag 09:12',
    tekst:
      'Stor dag for oss i Nabolab! I dag slapp vi beta-versjonen av appen til de første pilotbrukerne. Takk til alle i huset som har testet og gitt tilbakemeldinger de siste ukene - det hadde ikke gått uten dere. Vi spanderer kake i loungen kl. 14!',
    bilde: '/img/arbeidsarena/lounge-grunder.jpg',
    bildeAlt: 'Arbeidsplasser i loungen på Lius',
    liker: 28,
    kommentarer: 9,
  },
  {
    bedrift: 'romerike-sparebank',
    forfatter: { navn: 'Even Sandmo', tittel: 'Romerike Sparebank', initialer: 'ES', farge: 4 },
    tid: 'I dag 08:40',
    tekst:
      'Drop-in banktime i morgen 10-12 i 1. etasje. Ta med spørsmål om finansiering, oppstartslån eller helt vanlige bankting. Ingen påmelding - bare kom.',
    liker: 12,
    kommentarer: 2,
  },
  {
    bedrift: 'digitelle',
    forfatter: { navn: 'Vilde Aas', tittel: 'Digitelle', initialer: 'VA', farge: 2 },
    tid: 'I går 15:05',
    tekst:
      'Vi ønsker Sander velkommen på laget som ny utvikler! Han sitter sammen med oss i 3. etasje - si hei hvis du lurer på noe om nettsiden din. Neste uke holder vi et lite lynkurs for huset: "Nettsiden din på 30 minutter".',
    bilde: '/img/hero/interior-1.jpg',
    bildeAlt: 'Interiør fra fellesområdene på Lius',
    liker: 21,
    kommentarer: 5,
  },
  {
    bedrift: 'mobit',
    forfatter: { navn: 'Martin Grov', tittel: 'Mobit', initialer: 'MG', farge: 3 },
    tid: 'I går 11:30',
    tekst:
      'Tips til alle som booker møterom: den nye skjermen i møterom 4 deler trådløst - du trenger ikke kabel lenger. Kort veiledning henger på veggen. Si fra til oss hvis noe krangler, vi sitter i 2. etasje.',
    bilde: '/img/arbeidsarena/moterom-5.jpg',
    bildeAlt: 'Møterom på Lius med skjerm',
    liker: 17,
    kommentarer: 3,
  },
  {
    bedrift: 'advokatfirmaet-halvorsen-co',
    forfatter: { navn: 'Nora Lien', tittel: 'Advokatfirmaet Halvorsen & Co', initialer: 'NL', farge: 3 },
    tid: 'Tirsdag',
    tekst:
      'Minner om lunsjkurset "Kontrakter for gründere" på torsdag kl. 11:30 i amfiet. 30 minutter om de vanligste fellene i kundeavtaler, og god tid til spørsmål etterpå. Påmelding i arrangementskalenderen.',
    liker: 19,
    kommentarer: 4,
  },
  {
    bedrift: 'studio-nord',
    forfatter: { navn: 'Aksel Winther', tittel: 'Studio Nord', initialer: 'AW', farge: 4 },
    tid: 'Mandag',
    tekst:
      'Jeg rigger opp et lite fotohjørne i podkaststudioet onsdag ettermiddag. Trenger du nytt profilbilde til nettsiden eller LinkedIn? Book et kvarter - gratis for alle i huset denne runden.',
    bilde: '/img/hero/podcast.jpg',
    bildeAlt: 'Podkaststudioet på Lius',
    liker: 31,
    kommentarer: 11,
  },
  {
    bedrift: 'brectus',
    forfatter: { navn: 'Amir Yusuf', tittel: 'Brectus', initialer: 'AY', farge: 1 },
    tid: 'Forrige uke',
    tekst:
      'Vi har satt opp en messevegg i 1. etasje som demo - kom innom og se hvordan profilen deres kan se ut i full størrelse. Husets medlemmer får egne priser på profilering og messeutstyr.',
    liker: 9,
    kommentarer: 1,
  },
];

export const arrangementer = [
  { dato: 'Ons 12.00', tittel: 'Onsdagspitch', sted: 'Loungen', arrangor: 'Lius vertskap' },
  { dato: 'Fre 10.00', tittel: '«Bli kjent med ...»', sted: 'Auditoriet', arrangor: 'Lius vertskap' },
  { dato: '14. aug', tittel: 'Drop-in banktime', sted: '1. etasje', arrangor: 'Romerike Sparebank' },
  { dato: '15. aug', tittel: 'Kontrakter for gründere', sted: 'Amfiet', arrangor: 'Halvorsen & Co' },
  { dato: '21. aug', tittel: 'Frokostmøte', sted: 'Loungen', arrangor: 'Lius vertskap' },
];

export const nyeMedlemmer = [
  { navn: 'Sander Holt', tittel: 'Digitelle', initialer: 'SH', farge: 3 },
  { navn: 'Emil Hovland', tittel: 'Nabolab AS', initialer: 'EH', farge: 3 },
];

export function finnBedrift(slug: string): Bedrift | undefined {
  return bedrifter.find((b) => b.slug === slug);
}

/* --------------------------------------------------------------------------
   B2B Community OS (versjon 2 av forslaget, se docs/forslag-b2b-community-os.md)

   Intensjonsprofiler, strukturerte behov/tilbud og koblinger for
   matchmaking. Fortsatt eksempeldata - alle personer er fiktive.
   -------------------------------------------------------------------------- */

export interface Intensjoner {
  leverer: string;
  kjoper: string;
  trengerNa: string;
  idealkunder: string;
  apenFor: string[];
}

export const intensjoner: Record<string, Intensjoner> = {
  digitelle: {
    leverer: 'Nettsider: design, utvikling, drift og vedlikehold.',
    kjoper: 'Regnskap, foto og innholdsproduksjon.',
    trengerNa: 'Tekstforfatter til to kundeprosjekter i høst.',
    idealkunder: 'Små og mellomstore bedrifter som vil eie sin egen nettside.',
    apenFor: ['Kundehenvisninger', 'Partnerskap', 'Kompetansedeling'],
  },
  nabolab: {
    leverer: 'Nabolagsapp for borettslag og sameier (beta).',
    kjoper: 'Design, juridisk bistand og skytjenester.',
    trengerNa: 'UX-designer på deltid og pilotkunder til betaen.',
    idealkunder: 'Borettslag, sameier og velforeninger på Romerike.',
    apenFor: ['Pilotkunder', 'Investering', 'Rekruttering'],
  },
  'romerike-sparebank': {
    leverer: 'Bank- og finansieringstjenester for næringsliv og gründere.',
    kjoper: 'Foredrag, kurs og lokale arrangementstjenester.',
    trengerNa: 'Foredragsholdere til høstens gründerkvelder.',
    idealkunder: 'Bedrifter og gründere med tilknytning til Romerike.',
    apenFor: ['Kundehenvisninger', 'Kompetansedeling'],
  },
  mobit: {
    leverer: 'IT-drift, maskinvare og AV-løsninger for møterom.',
    kjoper: 'Markedsføring og innholdsproduksjon.',
    trengerNa: 'Case-kunder som vil vise fram moderne møteromsoppsett.',
    idealkunder: 'Bedrifter med 5-100 ansatte som vil ha IT uten eget IT-team.',
    apenFor: ['Kundehenvisninger', 'Partnerskap'],
  },
  'studio-nord': {
    leverer: 'Bedriftsfoto, produktfoto og visuell identitet.',
    kjoper: 'Regnskap og nettside.',
    trengerNa: 'Flere faste oppdragsgivere gjennom høsten.',
    idealkunder: 'Bedrifter i huset som trenger foto til nett og SoMe.',
    apenFor: ['Kundehenvisninger', 'Kompetansedeling'],
  },
  'advokatfirmaet-halvorsen-co': {
    leverer: 'Forretningsjus, kontrakter og rådgivning.',
    kjoper: 'Kommunikasjonstjenester og kurslokaler.',
    trengerNa: 'Ingenting akkurat nå - men tar gjerne en kaffe.',
    idealkunder: 'Vekstbedrifter og gründere med behov for løpende juridisk støtte.',
    apenFor: ['Kundehenvisninger', 'Kompetansedeling'],
  },
  brectus: {
    leverer: 'Profileringsartikler, messeutstyr og trykk.',
    kjoper: 'Digital markedsføring.',
    trengerNa: 'Pilotkunder til ny profilpakke for gründere.',
    idealkunder: 'Bedrifter som skal på messe eller bygge synlighet.',
    apenFor: ['Pilotkunder', 'Kundehenvisninger'],
  },
};

export interface Behov {
  bedrift: string; /* slug */
  kategori: 'trenger' | 'tilbyr';
  type: string;
  tittel: string;
  beskrivelse: string;
  frist?: string;
  budsjett?: string;
  kompetanse?: string[];
  kontakt: string;
}

export const behov: Behov[] = [
  {
    bedrift: 'nabolab',
    kategori: 'trenger',
    type: 'Trenger kompetanse eller rådgivning',
    tittel: 'UX-designer til beta-appen',
    beskrivelse:
      'Vi trenger hjelp til å rydde i påmeldingsflyten før lansering. Anslagsvis to dager i uken ut september.',
    frist: '1. september',
    budsjett: '20 000 - 40 000 kr',
    kompetanse: ['UX', 'Figma', 'Mobil'],
    kontakt: 'Kort videomøte',
  },
  {
    bedrift: 'romerike-sparebank',
    kategori: 'trenger',
    type: 'Trenger leverandør',
    tittel: 'Foredragsholdere til gründerkveld',
    beskrivelse:
      'Vi setter opp tre gründerkvelder i høst og ser etter medlemmer som kan holde korte, praktiske innlegg.',
    frist: '25. august',
    kompetanse: ['Formidling'],
    kontakt: 'E-post',
  },
  {
    bedrift: 'a-til-a-regnskap',
    kategori: 'trenger',
    type: 'Søker ansatte eller frilanser',
    tittel: 'Regnskapskonsulent på deltid',
    beskrivelse:
      'Økende oppdragsmengde - vi ser etter en konsulent to dager i uken, gjerne en i huset.',
    frist: 'Løpende',
    kompetanse: ['Regnskap', 'Tripletex'],
    kontakt: 'Kaffemøte',
  },
  {
    bedrift: 'brectus',
    kategori: 'trenger',
    type: 'Søker pilotkunde',
    tittel: 'Pilot: profilpakke for gründere',
    beskrivelse:
      'Vi tester en rimelig startpakke med logotrykk og roll-up og søker to gründerbedrifter som piloter mot medlemspris.',
    frist: '15. september',
    budsjett: 'Medlemspris',
    kontakt: 'Forespørsel via profilen',
  },
  {
    bedrift: 'digitelle',
    kategori: 'tilbyr',
    type: 'Har kapasitet tilgjengelig',
    tittel: 'Ledig kapasitet på nettsideprosjekter',
    beskrivelse:
      'Vi har rom for ett til to nye nettsideprosjekter med oppstart i september. Medlemmer i huset prioriteres.',
    frist: 'September',
    kontakt: 'Book rådgivningsmøte',
  },
  {
    bedrift: 'studio-nord',
    kategori: 'tilbyr',
    type: 'Har kapasitet tilgjengelig',
    tittel: 'To ledige dager for produktfoto',
    beskrivelse:
      'Riggen står oppe i podkaststudioet neste uke - to dager ledig for produktfoto til medlemspris.',
    frist: 'Neste uke',
    budsjett: 'Medlemspris',
    kontakt: 'Direkte melding',
  },
  {
    bedrift: 'mobit',
    kategori: 'tilbyr',
    type: 'Har utstyr, lokale eller andre ressurser tilgjengelig',
    tittel: 'Demo-rigg for videomøter kan lånes',
    beskrivelse:
      'Skal du i pitch eller kundemøte? Lån vår mobile skjerm- og lydrigg - vi hjelper med oppsett.',
    kontakt: 'Forespørsel via profilen',
  },
  {
    bedrift: 'advokatfirmaet-halvorsen-co',
    kategori: 'tilbyr',
    type: 'Tilbyr kompetanse eller rådgivning',
    tittel: 'Gratis førstevurdering av kundeavtaler',
    beskrivelse:
      'Vi tilbyr en kort førstevurdering av standardavtalene dine - en time per medlem, uten forpliktelser.',
    frist: 'Ut september',
    kontakt: 'Book rådgivningsmøte',
  },
  {
    bedrift: 'lorenskog-i-utvikling',
    kategori: 'trenger',
    type: 'Har et prosjekt som trenger underleverandør',
    tittel: 'Skilting og merking i fellesarealene',
    beskrivelse:
      'Vertskapet skal oppgradere skilting i huset og ser etter tilbud fra medlemmer før vi går eksternt.',
    frist: '30. august',
    budsjett: 'Etter tilbud',
    kontakt: 'E-post',
  },
];

export type IntroStatus =
  | 'Introduksjon foreslått'
  | 'Introduksjon akseptert'
  | 'Møte avtalt'
  | 'Mulighet identifisert'
  | 'Tilbud sendt'
  | 'Samarbeid startet'
  | 'Avtale inngått'
  | 'Ikke relevant nå';

export interface Kobling {
  parter: [string, string]; /* slugs */
  hvorfor: string;
  status: IntroStatus;
}

export const koblinger: Kobling[] = [
  {
    parter: ['nabolab', 'digitelle'],
    hvorfor:
      'Nabolab søker UX-hjelp til beta-appen; Digitelle har ledig kapasitet på design og nettsider i september.',
    status: 'Introduksjon foreslått',
  },
  {
    parter: ['brectus', 'nabolab'],
    hvorfor:
      'Brectus søker pilotkunder til profilpakke for gründere; Nabolab lanserer og trenger synlighet.',
    status: 'Introduksjon akseptert',
  },
  {
    parter: ['romerike-sparebank', 'advokatfirmaet-halvorsen-co'],
    hvorfor:
      'Banken søker foredragsholdere til gründerkveld; Halvorsen & Co holder allerede lunsjkurs om kontrakter.',
    status: 'Møte avtalt',
  },
  {
    parter: ['studio-nord', 'mobit'],
    hvorfor:
      'Mobit trenger case-bilder av moderne møteromsoppsett; Studio Nord har ledig fotokapasitet.',
    status: 'Samarbeid startet',
  },
];
