<?php
/**
 * Kommandoen `wp samlab seed` med nøytrale demodata.
 *
 * Strukturen speiler prototypens intern.ts, men uten kundenavn -
 * alle bedrifter, personer og tekster er fiktive og nøytrale.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seed-kommandoer for demoinnhold.
 */
class Samlab_CLI_Command {

	/**
	 * Fyller installasjonen med nøytrale demodata, eller rydder dem.
	 *
	 * ## OPTIONS
	 *
	 * [--slett]
	 * : Fjern alt seedet innhold i stedet for å opprette det.
	 *
	 * ## EXAMPLES
	 *
	 *     wp samlab seed
	 *     wp samlab seed --slett
	 *
	 * @param array $args       Posisjonsargumenter (ubrukt).
	 * @param array $assoc_args Flagg.
	 * @return void
	 */
	public function seed( $args, $assoc_args ) {
		if ( isset( $assoc_args['slett'] ) ) {
			$this->slett();
			return;
		}

		if ( false !== get_option( 'samlab_seed_terms' ) ) {
			WP_CLI::error( 'Demodata finnes allerede - kjør `wp samlab seed --slett` først.' );
		}

		$brukere   = $this->seed_brukere();
		$termer    = $this->seed_termer();
		$bedrifter = $this->seed_bedrifter( $brukere, $termer );
		$this->seed_behov( $bedrifter, $termer );
		$this->seed_vegg( $brukere );
		$this->seed_handbok();
		$this->seed_arrangementer( $bedrifter );
		$this->seed_koblinger( $brukere, $bedrifter );

		WP_CLI::success( 'Demodata på plass: 4 bedrifter, 5 behov, 5 vegginnlegg (med avstemning), 2 håndbok-sider, 3 arrangementer og 6 koblinger i ulike statuser (med varsler til partene, en åpen forespørsel og en fulgt opp kobling med utfall).' );
	}

	/**
	 * Kjører den regelbaserte matchingen manuelt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp samlab match
	 *
	 * @return void
	 */
	public function match() {
		$opprettet = samlab_kjor_matching();
		WP_CLI::success( sprintf( '%d nye matchforslag lagt i kontrollpanelets kø.', count( $opprettet ) ) );
	}

	/**
	 * Genererer og sender ukesbrevet nå (uavhengig av innstilt ukedag).
	 *
	 * ## OPTIONS
	 *
	 * [--vis]
	 * : Skriv brevteksten til terminalen i stedet for å sende.
	 *
	 * ## EXAMPLES
	 *
	 *     wp samlab ukesbrev
	 *     wp samlab ukesbrev --vis
	 *
	 * @param array $args       Posisjonsargumenter (ubrukt).
	 * @param array $assoc_args Flagg.
	 * @return void
	 */
	public function ukesbrev( $args, $assoc_args ) {
		if ( isset( $assoc_args['vis'] ) ) {
			$seksjoner = samlab_ukesbrev_seksjoner( time() - WEEK_IN_SECONDS );
			if ( array() === $seksjoner ) {
				WP_CLI::log( 'Ingenting å melde denne uken - brevet ville ikke blitt sendt.' );
				return;
			}
			WP_CLI::log( samlab_ukesbrev_tekst( $seksjoner ) );
			return;
		}
		$antall = samlab_send_ukesbrev();
		WP_CLI::success( sprintf( 'Ukesbrev sendt til %d mottakere.', $antall ) );
	}

	/**
	 * Bygger assistentens kunnskapsgrunnlag nå (krever at modulen er på).
	 *
	 * ## OPTIONS
	 *
	 * [--vis]
	 * : Skriv grunnlaget til terminalen i stedet for å bygge på nytt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp samlab kunnskap
	 *     wp samlab kunnskap --vis
	 *
	 * @param array $args       Posisjonsargumenter (ubrukt).
	 * @param array $assoc_args Flagg.
	 * @return void
	 */
	public function kunnskap( $args, $assoc_args ) {
		if ( ! function_exists( 'samlab_assistent_bygg_kunnskap' ) ) {
			WP_CLI::error( 'Assistent-modulen er av - slå den på under Innstillinger → Samlab først.' );
		}
		if ( isset( $assoc_args['vis'] ) ) {
			$grunnlag = samlab_assistent_kunnskap();
			if ( ! $grunnlag ) {
				WP_CLI::log( 'Ikke bygget ennå - kjør `wp samlab kunnskap` først.' );
				return;
			}
			WP_CLI::log( $grunnlag['tekst'] );
			return;
		}
		$grunnlag = samlab_assistent_bygg_kunnskap();
		WP_CLI::success( sprintf( 'Kunnskapsgrunnlag versjon %d bygget: %s, %d kilder hentet, %d feilet.', $grunnlag['versjon'], size_format( $grunnlag['storrelse'] ), $grunnlag['kilder_ok'], count( $grunnlag['kilder_feilet'] ) ) );
	}

	/**
	 * Oppretter demo-brukere (medlemmer og bedriftsredaktører).
	 *
	 * @return array<string, int> Brukernavn => ID.
	 */
	private function seed_brukere() {
		$definisjoner = array(
			'kari.demo'   => array( 'Kari Nordmann', 'samlab_company_editor' ),
			'ola.demo'    => array( 'Ola Hansen', 'samlab_company_editor' ),
			'ingrid.demo' => array( 'Ingrid Berg', 'samlab_company_editor' ),
			'jonas.demo'  => array( 'Jonas Dal', 'samlab_member' ),
		);

		$brukere = array();
		foreach ( $definisjoner as $login => $def ) {
			$id = username_exists( $login );
			if ( ! $id ) {
				$id = wp_insert_user(
					array(
						'user_login'   => $login,
						'user_email'   => $login . '@example.com',
						'user_pass'    => wp_generate_password(),
						'display_name' => $def[0],
						'role'         => $def[1],
					)
				);
				update_user_meta( $id, '_samlab_seed', '1' );
			}
			$brukere[ $login ] = (int) $id;
		}
		return $brukere;
	}

	/**
	 * Oppretter kategorier og behovstyper.
	 *
	 * @return array<string, int> Slug => term-ID.
	 */
	private function seed_termer() {
		$termer = array();
		foreach ( array(
			array( 'samlab_kategori', 'Design og kommunikasjon' ),
			array( 'samlab_kategori', 'Teknologi og IT' ),
			array( 'samlab_kategori', 'Økonomi og rådgivning' ),
			array( 'samlab_behovstype', 'Trenger leverandør' ),
			array( 'samlab_behovstype', 'Trenger kompetanse eller rådgivning' ),
			array( 'samlab_behovstype', 'Tilbyr tjeneste' ),
		) as $def ) {
			$eksisterende = get_term_by( 'name', $def[1], $def[0] );
			if ( $eksisterende ) {
				$termer[ $eksisterende->slug ] = (int) $eksisterende->term_id;
				continue;
			}
			$ny = wp_insert_term( $def[1], $def[0] );
			if ( ! is_wp_error( $ny ) ) {
				$term                  = get_term( $ny['term_id'] );
				$termer[ $term->slug ] = (int) $ny['term_id'];
			}
		}
		update_option( 'samlab_seed_terms', $termer, false );
		return $termer;
	}

	/**
	 * Genererer et enkelt fargebilde og legger det i mediebiblioteket.
	 *
	 * @param string $navn Filnavn uten etternavn.
	 * @param int    $farge RGB-verdi som heksheltall, f.eks. 0x88AA66.
	 * @param int    $post_id Post bildet knyttes til.
	 * @return int Attachment-ID, eller 0.
	 */
	private function lag_bilde( $navn, $farge, $post_id ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return 0;
		}
		$bilde = imagecreatetruecolor( 480, 320 );
		imagefill( $bilde, 0, 0, $farge );
		ob_start();
		imagepng( $bilde );
		$data = ob_get_clean();
		imagedestroy( $bilde );

		$fil = wp_upload_bits( $navn . '.png', null, $data );
		if ( ! empty( $fil['error'] ) ) {
			return 0;
		}
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => $navn,
				'post_status'    => 'inherit',
			),
			$fil['file'],
			$post_id
		);
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $fil['file'] ) );
		update_post_meta( $attachment_id, '_samlab_seed', '1' );
		return (int) $attachment_id;
	}

	/**
	 * Oppretter demobedriftene.
	 *
	 * @param array $brukere Brukernavn => ID.
	 * @param array $termer  Slug => term-ID.
	 * @return array<string, int> Slug => post-ID.
	 */
	private function seed_bedrifter( $brukere, $termer ) {
		$definisjoner = array(
			array(
				'navn'      => 'Brygga Design',
				'kategori'  => 'design-og-kommunikasjon',
				'kontakt'   => 'kari.demo',
				'kort'      => 'Visuell identitet og nettsider for små virksomheter.',
				'plass'     => '2. etasje - fast kontorplass',
				'om'        => 'Vi er tre designere som hjelper virksomheter i huset med å se like bra ut som de er. Eksempeltekst - erstattes av bedriftens egen.',
				'farge'     => 0x7A9E7E,
				'tjenester' => array(
					array(
						'tittel'  => 'Visuell identitet',
						'punkter' => array( 'Logo', 'Profilhåndbok', 'Maler' ),
					),
					array(
						'tittel'  => 'Nettsider',
						'punkter' => array( 'Design', 'Universell utforming' ),
					),
				),
				'leverer'   => 'Design, merkevare og nettsider',
				'kjoper'    => 'Regnskap og fotografi',
				'trenger'   => 'Fotograf til kundecaser',
				'ideal'     => 'Små virksomheter med vekstplaner',
				'apen'      => array( 'Samarbeid', 'Kaffeprat', 'Foredrag' ),
			),
			array(
				'navn'      => 'Fjordnett Systemer',
				'kategori'  => 'teknologi-og-it',
				'kontakt'   => 'ola.demo',
				'kort'      => 'Drift, sky og support for kontorfellesskapet.',
				'plass'     => '3. etasje',
				'om'        => 'IT-partneren i huset. Eksempeltekst - erstattes av bedriftens egen.',
				'farge'     => 0x5B7C99,
				'tjenester' => array(
					array(
						'tittel'  => 'Drift',
						'punkter' => array( 'Skytjenester', 'Sikkerhet', 'Support' ),
					),
				),
				'leverer'   => 'IT-drift og rådgivning',
				'kjoper'    => 'Markedsføring',
				'trenger'   => 'Innholdsprodusent til nyhetsbrev',
				'ideal'     => 'Virksomheter uten egen IT-avdeling',
				'apen'      => array( 'Partnerskap', 'Lunsjprat' ),
			),
			array(
				'navn'      => 'Tallknuserne',
				'kategori'  => 'okonomi-og-radgivning',
				'kontakt'   => 'ingrid.demo',
				'kort'      => 'Regnskap og økonomirådgivning for gründere.',
				'plass'     => 'Lounge-medlem',
				'om'        => 'Vi gjør tallene forståelige. Eksempeltekst - erstattes av bedriftens egen.',
				'farge'     => 0xB08968,
				'tjenester' => array(
					array(
						'tittel'  => 'Regnskap',
						'punkter' => array( 'Løpende føring', 'Årsoppgjør', 'Lønn' ),
					),
				),
				'leverer'   => 'Regnskap og økonomistyring',
				'kjoper'    => 'Design og nettsider',
				'trenger'   => 'Ny nettside',
				'ideal'     => 'Gründere og småbedrifter',
				'apen'      => array( 'Samarbeid', 'Kurs' ),
			),
			array(
				'navn'      => 'Grønn Vekst Rådgivning',
				'kategori'  => 'okonomi-og-radgivning',
				'kontakt'   => 'kari.demo',
				'kort'      => 'Bærekraftsrapportering og støtteordninger.',
				'plass'     => '1. etasje - fleksplass',
				'om'        => 'Vi hjelper virksomheter med bærekraft i praksis. Eksempeltekst.',
				'farge'     => 0x6B8E23,
				'tjenester' => array(
					array(
						'tittel'  => 'Rådgivning',
						'punkter' => array( 'Bærekraftsrapport', 'Søknader' ),
					),
				),
				'leverer'   => 'Bærekraftsrådgivning',
				'kjoper'    => 'IT-støtte',
				'trenger'   => 'Pilotkunder til nytt kurs',
				'ideal'     => 'Virksomheter med rapporteringskrav',
				'apen'      => array( 'Pilotprosjekter' ),
			),
		);

		$bedrifter = array();
		foreach ( $definisjoner as $def ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'samlab_bedrift',
					'post_status'  => 'publish',
					'post_title'   => $def['navn'],
					'post_content' => $def['om'],
					'meta_input'   => array(
						'_samlab_seed'          => '1',
						'_samlab_kort'          => $def['kort'],
						'_samlab_plass'         => $def['plass'],
						'_samlab_nettside'      => 'https://example.no/',
						'_samlab_kontaktperson' => $brukere[ $def['kontakt'] ],
						'_samlab_tjenester'     => $def['tjenester'],
						'_samlab_leverer'       => $def['leverer'],
						'_samlab_kjoper'        => $def['kjoper'],
						'_samlab_trenger_na'    => $def['trenger'],
						'_samlab_idealkunder'   => $def['ideal'],
						'_samlab_apen_for'      => $def['apen'],
					),
				)
			);
			if ( isset( $termer[ $def['kategori'] ] ) ) {
				wp_set_object_terms( $id, array( $termer[ $def['kategori'] ] ), 'samlab_kategori' );
			}
			$logo = $this->lag_bilde( sanitize_title( $def['navn'] ) . '-logo', $def['farge'], $id );
			if ( $logo ) {
				set_post_thumbnail( $id, $logo );
			}
			$bedrifter[ sanitize_title( $def['navn'] ) ] = (int) $id;
		}

		// Galleri-bilder på første bedrift.
		$forste = reset( $bedrifter );
		$this->lag_bilde( 'brygga-design-arbeid-1', 0xA3B18A, $forste );
		$this->lag_bilde( 'brygga-design-arbeid-2', 0x588157, $forste );

		return $bedrifter;
	}

	/**
	 * Oppretter demobehov.
	 *
	 * @param array $bedrifter Slug => post-ID.
	 * @param array $termer    Slug => term-ID.
	 * @return void
	 */
	private function seed_behov( $bedrifter, $termer ) {
		$definisjoner = array(
			array( 'Fotograf til kundecaser', 'trenger', 'trenger-leverandor', 'brygga-design', '1. oktober', '15 000 - 25 000 kr', array( 'Foto', 'Redigering' ), 'Kort videomøte' ),
			array( 'Innholdsprodusent til nyhetsbrev', 'trenger', 'trenger-kompetanse-eller-radgivning', 'fjordnett-systemer', 'Løpende', '', array( 'Tekst', 'E-post' ), 'E-post' ),
			array( 'Ny nettside til regnskapsbyrå', 'trenger', 'trenger-leverandor', 'tallknuserne', '15. november', '40 000 - 80 000 kr', array( 'WordPress', 'Design' ), 'Møte i huset' ),
			array( 'Tilbyr gratis økonomisjekk', 'tilbyr', 'tilbyr-tjeneste', 'tallknuserne', 'Ut året', '', array( 'Økonomi' ), 'Stikk innom' ),
			array( 'Pilotkunder til bærekraftskurs', 'trenger', 'trenger-kompetanse-eller-radgivning', 'gronn-vekst-radgivning', '20. september', 'Gratis for piloter', array( 'Bærekraft' ), 'E-post' ),
		);

		foreach ( $definisjoner as $def ) {
			$id      = wp_insert_post(
				array(
					'post_type'    => 'samlab_behov',
					'post_status'  => 'publish',
					'post_title'   => $def[0],
					'post_content' => 'Eksempelbeskrivelse for demobehovet «' . $def[0] . '».',
					'meta_input'   => array(
						'_samlab_seed'        => '1',
						'_samlab_bedrift'     => isset( $bedrifter[ $def[3] ] ) ? $bedrifter[ $def[3] ] : 0,
						'_samlab_frist'       => $def[4],
						'_samlab_budsjett'    => $def[5],
						'_samlab_kompetanse'  => $def[6],
						'_samlab_kontaktform' => $def[7],
					),
				)
			);
			$retning = get_term_by( 'slug', $def[1], 'samlab_retning' );
			if ( $retning ) {
				wp_set_object_terms( $id, array( (int) $retning->term_id ), 'samlab_retning' );
			}
			if ( isset( $termer[ $def[2] ] ) ) {
				wp_set_object_terms( $id, array( $termer[ $def[2] ] ), 'samlab_behovstype' );
			}
		}
	}

	/**
	 * Oppretter vegginnlegg med reaksjoner og en kommentar.
	 *
	 * @param array $brukere Brukernavn => ID.
	 * @return void
	 */
	private function seed_vegg( $brukere ) {
		$innlegg_ids = array();

		$innlegg_ids[] = Samlab_Innlegg::create(
			array(
				'user_id' => $brukere['kari.demo'],
				'content' => 'Velkommen til portalen! Her deler vi stort og smått fra huset.',
				'pinned'  => 1,
			)
		);
		$innlegg_ids[] = Samlab_Innlegg::create(
			array(
				'user_id' => $brukere['ola.demo'],
				'content' => 'Noen som vil dele en kaffe og prate skytjenester i dag?',
			)
		);
		$innlegg_ids[] = Samlab_Innlegg::create(
			array(
				'user_id' => $brukere['ingrid.demo'],
				'content' => 'Minner om fristen for mva-melding neste uke - stikk innom om dere trenger hjelp.',
			)
		);
		$innlegg_ids[] = Samlab_Innlegg::create(
			array(
				'user_id' => $brukere['jonas.demo'],
				'content' => 'Første uke i huset - takk for varm velkomst!',
			)
		);

		// Avstemning (E7): innlegg med spørsmål og stemmer fra tre medlemmer.
		$avstemning    = Samlab_Innlegg::create(
			array(
				'user_id'       => $brukere['ola.demo'],
				'content'       => 'Vi planlegger neste husfrokost - hjelp oss å velge dag!',
				'poll_sporsmal' => 'Hvilken dag passer best for husfrokost?',
				'poll_valg'     => array( 'Tirsdag', 'Onsdag', 'Fredag' ),
			)
		);
		$innlegg_ids[] = $avstemning;
		Samlab_Stemme::vote( $avstemning, $brukere['kari.demo'], 2 );
		Samlab_Stemme::vote( $avstemning, $brukere['ingrid.demo'], 0 );
		Samlab_Stemme::vote( $avstemning, $brukere['jonas.demo'], 2 );

		Samlab_Reaksjon::add( 'innlegg', $innlegg_ids[3], $brukere['kari.demo'] );
		Samlab_Reaksjon::add( 'innlegg', $innlegg_ids[3], $brukere['ola.demo'] );
		Samlab_Reaksjon::add( 'innlegg', $innlegg_ids[0], $brukere['jonas.demo'] );

		wp_insert_comment(
			array(
				'comment_post_ID'      => 0,
				'comment_type'         => 'samlab_innlegg',
				'comment_content'      => 'Velkommen skal du være!',
				'user_id'              => $brukere['kari.demo'],
				'comment_author'       => 'Kari Nordmann',
				'comment_author_email' => 'kari.demo@example.com',
				'comment_approved'     => 1,
				'comment_meta'         => array(
					'_samlab_innlegg' => $innlegg_ids[3],
					'_samlab_seed'    => '1',
				),
			)
		);

		update_option( 'samlab_seed_innlegg', array_map( 'intval', $innlegg_ids ), false );
	}

	/**
	 * Oppretter håndbok-sider.
	 *
	 * @return void
	 */
	private function seed_handbok() {
		$praktisk = '<!-- wp:heading --><h2>Adgang og nøkler</h2><!-- /wp:heading -->'
			. '<!-- wp:paragraph --><p>Eksempeltekst om adgangsbrikker og åpningstider.</p><!-- /wp:paragraph -->'
			. '<!-- wp:heading --><h2>Møterom</h2><!-- /wp:heading -->'
			. '<!-- wp:paragraph --><p>Eksempeltekst om booking av møterom.</p><!-- /wp:paragraph -->'
			. '<!-- wp:heading --><h2>Vanlige spørsmål</h2><!-- /wp:heading -->'
			. '<!-- wp:details --><details class="wp-block-details"><summary>Kan jeg ta med gjester?</summary><!-- wp:paragraph --><p>Ja - registrer dem i resepsjonen.</p><!-- /wp:paragraph --></details><!-- /wp:details -->'
			. '<!-- wp:details --><details class="wp-block-details"><summary>Finnes det parkering?</summary><!-- wp:paragraph --><p>Eksempelsvar om parkering.</p><!-- /wp:paragraph --></details><!-- /wp:details -->';

		foreach ( array(
			array( 'Praktisk informasjon', $praktisk, 1 ),
			array( 'Bli kjent i huset', '<!-- wp:paragraph --><p>Eksempeltekst om fellesskapet, arrangementer og hvordan du kommer i gang.</p><!-- /wp:paragraph -->', 2 ),
		) as $def ) {
			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $def[0],
					'post_content' => $def[1],
					'menu_order'   => $def[2],
					'meta_input'   => array(
						'_samlab_seed'    => '1',
						'_samlab_handbok' => '1',
					),
				)
			);
		}
	}

	/**
	 * Oppretter demoarrangementer: to kommende og ett tidligere (E6).
	 *
	 * De kommende ligger et godt stykke frem i tid: test-f2 forventer
	 * at «Felleslunsj med quiz» er kommende, og med kun +1 døgn ble
	 * testen rød i en rigg seedet dagen før - arrangementet var da
	 * fortid, og kunnskapsbygget utelot det helt korrekt.
	 *
	 * @param array $bedrifter Slug => post-ID.
	 * @return void
	 */
	private function seed_arrangementer( $bedrifter ) {
		$definisjoner = array(
			array( 'Felleslunsj med quiz', 7 * DAY_IN_SECONDS, HOUR_IN_SECONDS, 'Kantina', '' ),
			array( 'Frokostmøte: bærekraft i praksis', 10 * DAY_IN_SECONDS, 2 * HOUR_IN_SECONDS, '2. etasje', 'gronn-vekst-radgivning' ),
			array( 'Sommerfesten (vel overstått)', -30 * DAY_IN_SECONDS, 4 * HOUR_IN_SECONDS, 'Takterrassen', '' ),
		);

		foreach ( $definisjoner as $def ) {
			$start = time() + $def[1];
			wp_insert_post(
				array(
					'post_type'    => 'samlab_arrangement',
					'post_status'  => 'publish',
					'post_title'   => $def[0],
					'post_content' => 'Eksempelbeskrivelse for demoarrangementet «' . $def[0] . '».',
					'meta_input'   => array(
						'_samlab_seed'    => '1',
						'_samlab_start'   => wp_date( 'Y-m-d H:i', $start ),
						'_samlab_slutt'   => wp_date( 'Y-m-d H:i', $start + $def[2] ),
						'_samlab_sted'    => $def[3],
						'_samlab_bedrift' => isset( $bedrifter[ $def[4] ] ) ? $bedrifter[ $def[4] ] : 0,
					),
				)
			);
		}
	}

	/**
	 * Oppretter demokoblinger i ulike statuser (E1/E3). Statusløftene
	 * til godkjent/introdusert varsler partene (E2), så seeden gir
	 * også uleste varsler å vise frem.
	 *
	 * @param array $brukere   Brukernavn => ID.
	 * @param array $bedrifter Slug => post-ID.
	 * @return void
	 */
	private function seed_koblinger( $brukere, $bedrifter ) {
		$definisjoner = array(
			array( 'Brygga Design ↔ Jonas Dal', 'Jonas ser etter designmiljø - foreslått av verten.', 'brygga-design', 'jonas.demo', array() ),
			array( 'Fjordnett Systemer ↔ Tallknuserne', 'Tallknuserne trenger driftshjelp.', 'fjordnett-systemer', 'ingrid.demo', array( 'godkjent' ) ),
			array( 'Grønn Vekst ↔ Brygga Design', 'Bærekraftsrapporten trenger ny visuell drakt.', 'gronn-vekst-radgivning', 'kari.demo', array( 'godkjent', 'introdusert' ) ),
			array( 'Fjordnett Systemer ↔ Jonas Dal', 'Ikke aktuelt akkurat nå.', 'fjordnett-systemer', 'jonas.demo', array( 'avvist' ) ),
			array( 'Tallknuserne ↔ Jonas Dal', 'Jonas trenger hjelp med regnskapet i oppstarten.', 'tallknuserne', 'jonas.demo', array( 'forespurt' ) ),
			array( 'Brygga Design ↔ Ingrid Berg', 'Regnskapsbyrået trengte ny nettside - og fikk den.', 'brygga-design', 'ingrid.demo', array( 'forespurt' ) ),
		);

		foreach ( $definisjoner as $def ) {
			$kobling = samlab_opprett_kobling(
				array(
					'tittel'      => $def[0],
					'begrunnelse' => $def[1],
					'part_a'      => array(
						'type' => 'bedrift',
						'id'   => isset( $bedrifter[ $def[2] ] ) ? $bedrifter[ $def[2] ] : 0,
					),
					'part_b'      => array(
						'type' => 'bruker',
						'id'   => $brukere[ $def[3] ],
					),
				)
			);
			if ( is_wp_error( $kobling ) ) {
				continue;
			}
			update_post_meta( $kobling, '_samlab_seed', '1' );
			foreach ( $def[4] as $status ) {
				samlab_sett_kobling_status( $kobling, $status );
			}

			// Den siste koblingen kjøres hele samtykkeflyten (G1-G4):
			// begge parter takker ja, introduseres, og utfallet
			// «avtale» føres - løftet til fulgt opp følger med.
			if ( 'Brygga Design ↔ Ingrid Berg' === $def[0] ) {
				samlab_kobling_svar( $kobling, 'a', 'ja', (int) get_post_meta( $bedrifter[ $def[2] ], '_samlab_kontaktperson', true ) );
				samlab_kobling_svar( $kobling, 'b', 'ja', $brukere[ $def[3] ] );
				samlab_sett_kobling_status( $kobling, 'introdusert' );
				samlab_sett_kobling_utfall( $kobling, 'avtale', 'Ny nettside levert - fastprisavtale.' );
			}
		}
	}

	/**
	 * Fjerner alt seedet innhold.
	 *
	 * @return void
	 */
	private function slett() {
		$poster = get_posts(
			array(
				'post_type'      => array( 'samlab_bedrift', 'samlab_behov', 'samlab_arrangement', 'samlab_kobling', 'page', 'attachment' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_samlab_seed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Kun ved opprydding.
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $poster as $post ) {
			wp_delete_post( $post->ID, true );
		}

		foreach ( (array) get_option( 'samlab_seed_innlegg', array() ) as $innlegg_id ) {
			Samlab_Innlegg::delete( (int) $innlegg_id );
		}

		$kommentarer = get_comments(
			array(
				'type'       => 'samlab_innlegg',
				'meta_key'   => '_samlab_seed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Kun ved opprydding.
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $kommentarer as $kommentar ) {
			wp_delete_comment( $kommentar->comment_ID, true );
		}

		foreach ( (array) get_option( 'samlab_seed_terms', array() ) as $term_id ) {
			foreach ( array( 'samlab_kategori', 'samlab_behovstype' ) as $taksonomi ) {
				if ( get_term( $term_id, $taksonomi ) instanceof WP_Term ) {
					wp_delete_term( $term_id, $taksonomi );
				}
			}
		}

		$brukere = get_users(
			array(
				'meta_key'   => '_samlab_seed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Kun ved opprydding.
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $brukere as $bruker ) {
			wp_delete_user( $bruker->ID );
		}

		delete_option( 'samlab_seed_terms' );
		delete_option( 'samlab_seed_innlegg' );

		WP_CLI::success( 'Demodata fjernet.' );
	}

	/**
	 * Eksporterer alt portalinnhold til JSON for migrering til
	 * webapp-sporet. Formatet er definert i samlab-webapp-repoets
	 * docs/eksportformat.md og endres kun i samme runde som importen.
	 *
	 * Passord, API-nøkler og infoskjerm-nøkkelen eksporteres aldri.
	 * Varsler (flyktige) og kunnskapsgrunnlaget (bygges på nytt)
	 * er utelatt.
	 *
	 * ## OPTIONS
	 *
	 * [--fil=<sti>]
	 * : Skriv til fil i stedet for standard ut.
	 *
	 * [--medier=<katalog>]
	 * : Kopier mediefilene til katalogen (beholder relative stier).
	 *
	 * ## EXAMPLES
	 *
	 *     wp samlab eksport --fil=samlab.json --medier=medier/
	 *
	 * @param array $args       Posisjonsargumenter (ubrukt).
	 * @param array $assoc_args Flagg.
	 * @return void
	 */
	public function eksport( $args, $assoc_args ) {
		$data = $this->eksport_data();

		if ( isset( $assoc_args['medier'] ) && '' !== $assoc_args['medier'] ) {
			$antall = $this->eksport_medier_kopier( $data['medier'], (string) $assoc_args['medier'] );
			WP_CLI::log( sprintf( '%d mediefiler kopiert.', $antall ) );
		}

		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		if ( isset( $assoc_args['fil'] ) && '' !== $assoc_args['fil'] ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI-verktøy, skriver dit operatøren peker.
			if ( false === file_put_contents( (string) $assoc_args['fil'], $json ) ) {
				WP_CLI::error( 'Kunne ikke skrive til ' . $assoc_args['fil'] );
			}
			WP_CLI::success( sprintf( 'Eksportert til %s (%d bedrifter, %d behov, %d koblinger, %d innlegg, %d brukere).', $assoc_args['fil'], count( $data['bedrifter'] ), count( $data['behov'] ), count( $data['koblinger'] ), count( $data['innlegg'] ), count( $data['brukere'] ) ) );
			return;
		}
		WP_CLI::log( $json );
	}

	/**
	 * Bygger hele eksportstrukturen. Offentlig så riggtesten kan
	 * validere formatet uten å gå via filsystemet.
	 *
	 * @return array
	 */
	public function eksport_data() {
		$medier  = array();
		$innlegg = $this->eksport_innlegg( $medier );

		return array(
			'format'         => 'samlab-eksport',
			'format_versjon' => 1,
			'plugin_versjon' => SAMLAB_VERSION,
			'eksportert'     => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'nettsted'       => array(
				'navn' => get_bloginfo( 'name' ),
				'url'  => home_url(),
			),
			'innstillinger'  => $this->eksport_innstillinger(),
			'brukere'        => $this->eksport_brukere(),
			'bedrifter'      => $this->eksport_bedrifter( $medier ),
			'behov'          => $this->eksport_behov(),
			'koblinger'      => $this->eksport_koblinger(),
			'arrangementer'  => $this->eksport_arrangementer(),
			'handbok'        => $this->eksport_handbok(),
			'innlegg'        => $innlegg,
			'kommentarer'    => $this->eksport_kommentarer( array_column( $innlegg, 'id' ) ),
			'reaksjoner'     => $this->eksport_tabell( 'reaksjoner' ),
			'stemmer'        => $this->eksport_tabell( 'stemmer' ),
			'ubesvart'       => $this->eksport_ubesvart(),
			'medier'         => array_values( $medier ),
		);
	}

	/**
	 * Innstillingene - uten hemmeligheter: nøkler med «nokkel» i
	 * navnet hoppes alltid over, og infoskjerm-nøkkelen bor uansett
	 * i egen option som ikke røres her.
	 *
	 * @return array<string, string>
	 */
	private function eksport_innstillinger() {
		$ut = array();
		foreach ( (array) get_option( 'samlab_settings', array() ) as $nokkel => $verdi ) {
			if ( false !== strpos( (string) $nokkel, 'nokkel' ) ) {
				continue;
			}
			$ut[ (string) $nokkel ] = is_array( $verdi ) ? $verdi : (string) $verdi;
		}
		return $ut;
	}

	/**
	 * Medlemmene: alle med en samlab-rolle. Aldri passord.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function eksport_brukere() {
		$ut = array();
		foreach ( get_users( array( 'role__in' => array_keys( samlab_get_roles() ) ) ) as $bruker ) {
			$ut[] = array(
				'wp_id'              => (int) $bruker->ID,
				'login'              => $bruker->user_login,
				'epost'              => $bruker->user_email,
				'visningsnavn'       => $bruker->display_name,
				'roller'             => array_values( array_intersect( $bruker->roles, array_keys( samlab_get_roles() ) ) ),
				'registrert'         => $this->til_utc( $bruker->user_registered ),
				'ukesbrev_reservert' => '1' === get_user_meta( $bruker->ID, 'samlab_ukesbrev_reservert', true ),
			);
		}
		return $ut;
	}

	/**
	 * Bedriftene med tjenester, intensjoner, logo og galleri.
	 *
	 * @param array $medier Medieregisteret (utvides underveis).
	 * @return array<int, array<string, mixed>>
	 */
	private function eksport_bedrifter( &$medier ) {
		$ut = array();
		foreach ( $this->alle_poster( 'samlab_bedrift' ) as $post ) {
			$galleri = array();
			$vedlegg = get_children(
				array(
					'post_parent' => $post->ID,
					'post_type'   => 'attachment',
					'fields'      => 'ids',
				)
			);
			foreach ( $vedlegg as $vedlegg_id ) {
				$galleri[] = $this->registrer_medium( (int) $vedlegg_id, $medier );
			}
			$logo = has_post_thumbnail( $post ) ? $this->registrer_medium( (int) get_post_thumbnail_id( $post ), $medier ) : null;

			$ut[] = array(
				'wp_id'         => (int) $post->ID,
				'tittel'        => $post->post_title,
				'slug'          => $post->post_name,
				'status'        => $post->post_status,
				'beskrivelse'   => $post->post_content,
				'kort'          => (string) get_post_meta( $post->ID, '_samlab_kort', true ),
				'kategorier'    => $this->term_slugs( $post, 'samlab_kategori' ),
				'kontaktperson' => (int) get_post_meta( $post->ID, '_samlab_kontaktperson', true ),
				'plass'         => (string) get_post_meta( $post->ID, '_samlab_plass', true ),
				'nettside'      => (string) get_post_meta( $post->ID, '_samlab_nettside', true ),
				'tjenester'     => (array) get_post_meta( $post->ID, '_samlab_tjenester', true ),
				'intensjoner'   => array(
					'leverer'     => (string) get_post_meta( $post->ID, '_samlab_leverer', true ),
					'kjoper'      => (string) get_post_meta( $post->ID, '_samlab_kjoper', true ),
					'trenger_na'  => (string) get_post_meta( $post->ID, '_samlab_trenger_na', true ),
					'idealkunder' => (string) get_post_meta( $post->ID, '_samlab_idealkunder', true ),
					'apen_for'    => (array) get_post_meta( $post->ID, '_samlab_apen_for', true ),
				),
				'logo'          => $logo,
				'galleri'       => array_values( array_filter( $galleri ) ),
				'opprettet'     => $this->til_utc( $post->post_date_gmt ),
			);
		}
		return $ut;
	}

	/**
	 * Behov og tilbud.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function eksport_behov() {
		$ut = array();
		foreach ( $this->alle_poster( 'samlab_behov' ) as $post ) {
			$retning = $this->term_slugs( $post, 'samlab_retning' );
			$type    = $this->term_slugs( $post, 'samlab_behovstype' );
			$ut[]    = array(
				'wp_id'       => (int) $post->ID,
				'tittel'      => $post->post_title,
				'beskrivelse' => $post->post_content,
				'status'      => $post->post_status,
				'retning'     => $retning ? $retning[0] : '',
				'behovstype'  => $type ? $type[0] : '',
				'frist'       => (string) get_post_meta( $post->ID, '_samlab_frist', true ),
				'kompetanse'  => array_values( (array) get_post_meta( $post->ID, '_samlab_kompetanse', true ) ),
				'bedrift'     => (int) get_post_meta( $post->ID, '_samlab_bedrift', true ),
				'opprettet'   => $this->til_utc( $post->post_date_gmt ),
			);
		}
		return $ut;
	}

	/**
	 * Koblingene med parter, samtykke, utfall og full statuslogg.
	 * Utfallets hvem/når hentes fra loggens utfall_-innslag.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function eksport_koblinger() {
		$ut = array();
		foreach ( $this->alle_poster( 'samlab_kobling' ) as $post ) {
			$logg = get_post_meta( $post->ID, '_samlab_statuslogg', true );
			$logg = is_array( $logg ) ? $logg : array();

			$logg_ut     = array();
			$utfall_hvem = 0;
			$utfall_tid  = '';
			foreach ( $logg as $rad ) {
				$slug      = (string) ( $rad['status'] ?? '' );
				$logg_ut[] = array(
					'status'     => $slug,
					'user_wp_id' => (int) ( $rad['user_id'] ?? 0 ),
					'tid'        => $this->til_utc( (string) ( $rad['tid'] ?? '' ) ),
				);
				if ( 0 === strpos( $slug, 'utfall_' ) ) {
					$utfall_hvem = (int) ( $rad['user_id'] ?? 0 );
					$utfall_tid  = $this->til_utc( (string) ( $rad['tid'] ?? '' ) );
				}
			}

			$utfall_type = (string) get_post_meta( $post->ID, '_samlab_utfall', true );
			$ut[]        = array(
				'wp_id'       => (int) $post->ID,
				'status'      => (string) get_post_meta( $post->ID, '_samlab_status', true ),
				'part_a'      => array(
					'type'  => (string) get_post_meta( $post->ID, '_samlab_part_a_type', true ),
					'wp_id' => (int) get_post_meta( $post->ID, '_samlab_part_a_id', true ),
				),
				'part_b'      => array(
					'type'  => (string) get_post_meta( $post->ID, '_samlab_part_b_type', true ),
					'wp_id' => (int) get_post_meta( $post->ID, '_samlab_part_b_id', true ),
				),
				'samtykke_a'  => samlab_kobling_samtykke( $post->ID, 'a' ),
				'samtykke_b'  => samlab_kobling_samtykke( $post->ID, 'b' ),
				'kilde'       => (string) get_post_meta( $post->ID, '_samlab_kilde', true ),
				'begrunnelse' => $post->post_content,
				'utfall'      => '' === $utfall_type ? null : array(
					'type'    => $utfall_type,
					'notat'   => (string) get_post_meta( $post->ID, '_samlab_utfall_notat', true ),
					'satt_av' => $utfall_hvem,
					'tid'     => $utfall_tid,
				),
				'paminnet'    => '1' === get_post_meta( $post->ID, '_samlab_utfall_paminnet', true ),
				'statuslogg'  => $logg_ut,
				'opprettet'   => $this->til_utc( $post->post_date_gmt ),
			);
		}
		return $ut;
	}

	/**
	 * Arrangementene.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function eksport_arrangementer() {
		$ut = array();
		foreach ( $this->alle_poster( 'samlab_arrangement' ) as $post ) {
			$ut[] = array(
				'wp_id'       => (int) $post->ID,
				'tittel'      => $post->post_title,
				'beskrivelse' => $post->post_content,
				'status'      => $post->post_status,
				'start'       => (string) get_post_meta( $post->ID, '_samlab_start', true ),
				'slutt'       => (string) get_post_meta( $post->ID, '_samlab_slutt', true ),
				'sted'        => (string) get_post_meta( $post->ID, '_samlab_sted', true ),
				'bedrift'     => (int) get_post_meta( $post->ID, '_samlab_bedrift', true ),
			);
		}
		return $ut;
	}

	/**
	 * Håndbok-sidene (kun sider merket som håndbok).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function eksport_handbok() {
		$ut = array();
		foreach ( samlab_get_handbok_pages() as $post ) {
			$ut[] = array(
				'wp_id'   => (int) $post->ID,
				'tittel'  => $post->post_title,
				'slug'    => $post->post_name,
				'status'  => $post->post_status,
				'innhold' => $post->post_content,
			);
		}
		return $ut;
	}

	/**
	 * Vegginnleggene fra egen tabell.
	 *
	 * @param array $medier Medieregisteret (utvides underveis).
	 * @return array<int, array<string, mixed>>
	 */
	private function eksport_innlegg( &$medier ) {
		global $wpdb;
		$tabell = samlab_table( 'innlegg' );
		$ut     = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Egen tabell, engangs-eksport uten brukerinput.
		foreach ( (array) $wpdb->get_results( "SELECT * FROM {$tabell} ORDER BY id" ) as $rad ) {
			$valg = json_decode( (string) $rad->poll_valg, true );
			$ut[] = array(
				'id'         => (int) $rad->id,
				'user_wp_id' => (int) $rad->user_id,
				'innhold'    => (string) $rad->content,
				'bilde'      => $rad->image_id ? $this->registrer_medium( (int) $rad->image_id, $medier ) : null,
				'festet'     => (bool) $rad->pinned,
				'lesekrav'   => (bool) $rad->confirm_read,
				'status'     => (string) $rad->status,
				'avstemning' => '' === (string) $rad->poll_sporsmal ? null : array(
					'sporsmal'     => (string) $rad->poll_sporsmal,
					'alternativer' => is_array( $valg ) ? $valg : array(),
				),
				'opprettet'  => $this->til_utc( (string) $rad->created_at ),
			);
		}
		return $ut;
	}

	/**
	 * Kommentarene på veggen (WP-kommentarer, type samlab_innlegg).
	 * Foreldreløse kommentarer (innlegget er slettet) hoppes over -
	 * de vises ingen steder og skal ikke gjenoppstå ved import.
	 *
	 * @param int[] $innlegg_ids Innleggene som faktisk eksporteres.
	 * @return array<int, array<string, mixed>>
	 */
	private function eksport_kommentarer( $innlegg_ids ) {
		$ut       = array();
		$sporring = array(
			'type'   => 'samlab_innlegg',
			'status' => 'approve',
		);
		foreach ( get_comments( $sporring ) as $kommentar ) {
			$innlegg_id = (int) get_comment_meta( $kommentar->comment_ID, '_samlab_innlegg', true );
			if ( ! in_array( $innlegg_id, $innlegg_ids, true ) ) {
				continue;
			}
			$ut[] = array(
				'wp_id'      => (int) $kommentar->comment_ID,
				'innlegg_id' => $innlegg_id,
				'user_wp_id' => (int) $kommentar->user_id,
				'innhold'    => $kommentar->comment_content,
				'opprettet'  => $this->til_utc( $kommentar->comment_date_gmt ),
			);
		}
		return $ut;
	}

	/**
	 * Rader fra en av pluginens egne tabeller (reaksjoner/stemmer).
	 *
	 * @param string $navn Tabellens basisnavn.
	 * @return array<int, array<string, mixed>>
	 */
	private function eksport_tabell( $navn ) {
		global $wpdb;
		$tabell = samlab_table( $navn );
		$ut     = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Egen tabell, navn fra samlab_table(), engangs-eksport.
		foreach ( (array) $wpdb->get_results( "SELECT * FROM {$tabell} ORDER BY id" ) as $rad ) {
			if ( 'reaksjoner' === $navn ) {
				$ut[] = array(
					'object_type' => (string) $rad->object_type,
					'object_id'   => (int) $rad->object_id,
					'user_wp_id'  => (int) $rad->user_id,
					'reaksjon'    => (string) $rad->reaction,
					'opprettet'   => $this->til_utc( (string) $rad->created_at ),
				);
			} else {
				$ut[] = array(
					'innlegg_id' => (int) $rad->innlegg_id,
					'user_wp_id' => (int) $rad->user_id,
					'valg'       => (int) $rad->valg,
					'opprettet'  => $this->til_utc( (string) $rad->created_at ),
				);
			}
		}
		return $ut;
	}

	/**
	 * Ubesvart-køen - anonym per domenekontrakten.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function eksport_ubesvart() {
		$ut = array();
		foreach ( (array) get_option( 'samlab_ubesvart', array() ) as $rad ) {
			$ut[] = array(
				'sporsmal' => (string) ( $rad['sporsmal'] ?? '' ),
				'antall'   => (int) ( $rad['antall'] ?? 0 ),
				'sist'     => (string) ( $rad['dato'] ?? '' ),
			);
		}
		return $ut;
	}

	/**
	 * Registrerer et vedlegg i medieregisteret og returnerer wp_id.
	 *
	 * @param int   $vedlegg_id Vedlegget.
	 * @param array $medier     Registeret (wp_id => rad).
	 * @return int|null wp_id, eller null når vedlegget ikke finnes.
	 */
	private function registrer_medium( $vedlegg_id, &$medier ) {
		if ( isset( $medier[ $vedlegg_id ] ) ) {
			return $vedlegg_id;
		}
		$fil = (string) get_post_meta( $vedlegg_id, '_wp_attached_file', true );
		if ( '' === $fil ) {
			return null;
		}
		$medier[ $vedlegg_id ] = array(
			'wp_id' => $vedlegg_id,
			'fil'   => $fil,
			'url'   => (string) wp_get_attachment_url( $vedlegg_id ),
		);
		return $vedlegg_id;
	}

	/**
	 * Kopierer mediefilene til en katalog, med relative stier i behold.
	 *
	 * @param array  $medier  Medieregisterets rader.
	 * @param string $katalog Målkatalogen.
	 * @return int Antall kopierte filer.
	 */
	private function eksport_medier_kopier( $medier, $katalog ) {
		$oppl   = wp_get_upload_dir();
		$antall = 0;
		foreach ( $medier as $rad ) {
			$kilde = trailingslashit( $oppl['basedir'] ) . $rad['fil'];
			$maal  = trailingslashit( $katalog ) . $rad['fil'];
			if ( ! file_exists( $kilde ) ) {
				continue;
			}
			wp_mkdir_p( dirname( $maal ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- CLI-verktøy.
			if ( copy( $kilde, $maal ) ) {
				++$antall;
			}
		}
		return $antall;
	}

	/**
	 * Term-slugs for en post i en taksonomi, tom liste uten termer.
	 *
	 * @param WP_Post $post      Posten.
	 * @param string  $taksonomi Taksonomien.
	 * @return string[]
	 */
	private function term_slugs( $post, $taksonomi ) {
		$termer = get_the_terms( $post, $taksonomi );
		if ( ! is_array( $termer ) ) {
			return array();
		}
		return array_values( wp_list_pluck( $termer, 'slug' ) );
	}

	/**
	 * Alle poster av en type, uansett status, eldste først.
	 *
	 * @param string $type Post-typen.
	 * @return WP_Post[]
	 */
	private function alle_poster( $type ) {
		return get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * «Y-m-d H:i:s» i UTC (slik pluginen lagrer) til RFC3339.
	 *
	 * @param string $tid Tidspunktet, eller tom streng.
	 * @return string RFC3339, eller tom streng.
	 */
	private function til_utc( $tid ) {
		$ts = strtotime( (string) $tid . ' UTC' );
		return $ts ? gmdate( 'Y-m-d\TH:i:s\Z', $ts ) : '';
	}
}

WP_CLI::add_command( 'samlab', 'Samlab_CLI_Command' );
