<?php
/**
 * Innstillingsside: portalnavn, portal-sti, flatenavn/slugs, valgfri
 * aksentfarge-overstyring og logo. Alt lagres i samlab_settings;
 * standardene bor i koden (rewrites.php) - aldri kundeverdier.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feltdefinisjonene for innstillingssiden.
 *
 * @return array<string, array{label: string, type: string, help?: string}>
 */
function samlab_settings_fields() {
	return array(
		'portal_navn'        => array(
			'label' => __( 'Portalnavn', 'samlab' ),
			'type'  => 'text',
			'help'  => __( 'Vises i toppen av portalen. Standard: «Portalen».', 'samlab' ),
		),
		'portal_sti'         => array(
			'label' => __( 'Portal-sti', 'samlab' ),
			'type'  => 'slug',
			'help'  => __( 'URL-stien portalen bor under. Standard: «portal».', 'samlab' ),
		),
		'navn_vegg'          => array(
			'label' => __( 'Navn på veggen', 'samlab' ),
			'type'  => 'text',
		),
		'slug_vegg'          => array(
			'label' => __( 'Slug for veggen', 'samlab' ),
			'type'  => 'slug',
		),
		'navn_behov'         => array(
			'label' => __( 'Navn på behovsflaten', 'samlab' ),
			'type'  => 'text',
		),
		'slug_behov'         => array(
			'label' => __( 'Slug for behovsflaten', 'samlab' ),
			'type'  => 'slug',
		),
		'navn_bedrifter'     => array(
			'label' => __( 'Navn på bedriftskatalogen', 'samlab' ),
			'type'  => 'text',
		),
		'slug_bedrifter'     => array(
			'label' => __( 'Slug for bedriftskatalogen', 'samlab' ),
			'type'  => 'slug',
		),
		'navn_arrangementer' => array(
			'label' => __( 'Navn på arrangementsflaten', 'samlab' ),
			'type'  => 'text',
		),
		'slug_arrangementer' => array(
			'label' => __( 'Slug for arrangementsflaten', 'samlab' ),
			'type'  => 'slug',
		),
		'navn_handbok'       => array(
			'label' => __( 'Navn på håndboken', 'samlab' ),
			'type'  => 'text',
		),
		'slug_handbok'       => array(
			'label' => __( 'Slug for håndboken', 'samlab' ),
			'type'  => 'slug',
		),
		'slug_skjerm'        => array(
			'label' => __( 'Slug for infoskjermen', 'samlab' ),
			'type'  => 'slug',
			'help'  => __( 'URL-delen før nøkkelen. Standard: «skjerm».', 'samlab' ),
		),
		'aksentfarge'        => array(
			'label' => __( 'Aksentfarge', 'samlab' ),
			'type'  => 'farge',
			'help'  => __( 'Valgfri overstyring som heksverdi, f.eks. #3a5a40. Tom = temaets aksentfarge.', 'samlab' ),
		),
		'logo'               => array(
			'label' => __( 'Logo-URL', 'samlab' ),
			'type'  => 'url',
			'help'  => __( 'Valgfri. Last opp i mediebiblioteket og lim inn URL-en her.', 'samlab' ),
		),
		'ukesbrev_aktiv'     => array(
			'label' => __( 'Ukesbrev', 'samlab' ),
			'type'  => 'avkryssing',
			'help'  => __( 'Send et ukentlig oppsummerings-brev på e-post til medlemmene. Medlemmer kan reservere seg på profilsiden sin.', 'samlab' ),
		),
		'ukesbrev_ukedag'    => array(
			'label' => __( 'Ukesbrevets ukedag', 'samlab' ),
			'type'  => 'ukedag',
			'help'  => __( 'Dagen brevet sendes. Standard: mandag.', 'samlab' ),
		),
		'ukesbrev_avsender'  => array(
			'label' => __( 'Ukesbrevets avsendernavn', 'samlab' ),
			'type'  => 'text',
			'help'  => __( 'Navnet e-posten sendes fra. Tom = portalnavnet.', 'samlab' ),
		),
		'assistent_seksjon'  => array(
			'label' => __( 'Assistenten', 'samlab' ),
			'type'  => 'overskrift',
			'help'  => __( 'Valgfri KI-modul - portalen fungerer fullt ut uten. Kall går server-side mot Claude API.', 'samlab' ),
		),
		'assistent_aktiv'    => array(
			'label' => __( 'Assistent på', 'samlab' ),
			'type'  => 'avkryssing',
			'help'  => __( 'Standard av. Når modulen er av, lastes ingen assistent-kode.', 'samlab' ),
		),
		'assistent_nokkel'   => array(
			'label'     => __( 'Claude API-nøkkel', 'samlab' ),
			'type'      => 'status',
			'status_cb' => 'samlab_assistent_nokkel_status',
		),
		'assistent_navn'     => array(
			'label' => __( 'Assistentens navn', 'samlab' ),
			'type'  => 'text',
			'help'  => __( 'Standard: «Assistenten».', 'samlab' ),
		),
		'assistent_velkomst' => array(
			'label' => __( 'Velkomstmelding', 'samlab' ),
			'type'  => 'tekstfelt',
			'help'  => __( 'Meldingen som møter medlemmene i chatten.', 'samlab' ),
		),
		'assistent_tone'     => array(
			'label' => __( 'Toneinstruks', 'samlab' ),
			'type'  => 'tekstfelt',
			'help'  => __( 'Hvordan assistenten skal svare, f.eks. «kortfattet og uformell, på bokmål».', 'samlab' ),
		),
		'assistent_modell'   => array(
			'label' => __( 'Modell', 'samlab' ),
			'type'  => 'modell',
			'help'  => __( 'Claude-modell-ID. Standard: claude-opus-5.', 'samlab' ),
		),
		'assistent_kilder'   => array(
			'label' => __( 'Eksterne kilder', 'samlab' ),
			'type'  => 'urlliste',
			'help'  => __( 'Én URL per linje - hentes inn i kunnskapsgrunnlaget av den daglige jobben. Aldri sider med passord eller sensitivt innhold.', 'samlab' ),
		),
	);
}

/**
 * Ukedagene til ukedag-feltet (ISO 8601: 1 = mandag).
 *
 * @return array<int, string>
 */
function samlab_settings_ukedager() {
	return array(
		1 => __( 'Mandag', 'samlab' ),
		2 => __( 'Tirsdag', 'samlab' ),
		3 => __( 'Onsdag', 'samlab' ),
		4 => __( 'Torsdag', 'samlab' ),
		5 => __( 'Fredag', 'samlab' ),
		6 => __( 'Lørdag', 'samlab' ),
		7 => __( 'Søndag', 'samlab' ),
	);
}

/**
 * Registrerer innstillingen med saniteringscallback.
 * Kjøres på init slik at saniteringen også gjelder wp-cli.
 *
 * @return void
 */
function samlab_register_settings() {
	register_setting(
		'samlab_settings_group',
		'samlab_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'samlab_sanitize_settings',
			'default'           => array(),
		)
	);
}
add_action( 'init', 'samlab_register_settings' );

/**
 * Saniterer innstillingene feltvis.
 *
 * @param mixed $input Rå innsending.
 * @return array<string, string>
 */
function samlab_sanitize_settings( $input ) {
	if ( ! is_array( $input ) ) {
		return array();
	}

	$ren = array();
	foreach ( samlab_settings_fields() as $key => $felt ) {
		// Rene visningsrader tar aldri imot verdier.
		if ( in_array( $felt['type'], array( 'overskrift', 'status' ), true ) ) {
			continue;
		}
		if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) {
			continue;
		}
		switch ( $felt['type'] ) {
			case 'slug':
				$verdi = sanitize_title( $input[ $key ] );
				break;
			case 'farge':
				$verdi = (string) sanitize_hex_color( $input[ $key ] );
				break;
			case 'url':
				$verdi = esc_url_raw( $input[ $key ] );
				break;
			case 'avkryssing':
				$verdi = '1' === $input[ $key ] ? '1' : '';
				break;
			case 'ukedag':
				$dag   = (int) $input[ $key ];
				$verdi = ( $dag >= 1 && $dag <= 7 ) ? (string) $dag : '';
				break;
			case 'tekstfelt':
				$verdi = sanitize_textarea_field( $input[ $key ] );
				break;
			case 'modell':
				$verdi = preg_replace( '/[^a-z0-9.\-]/', '', strtolower( sanitize_text_field( $input[ $key ] ) ) );
				break;
			case 'urlliste':
				$linjer = explode( "\n", sanitize_textarea_field( $input[ $key ] ) );
				$urler  = array();
				foreach ( $linjer as $linje ) {
					$url = esc_url_raw( trim( $linje ), array( 'http', 'https' ) );
					if ( '' !== $url ) {
						$urler[] = $url;
					}
				}
				$verdi = implode( "\n", $urler );
				break;
			default:
				$verdi = sanitize_text_field( $input[ $key ] );
		}
		if ( '' !== $verdi ) {
			$ren[ $key ] = $verdi;
		}
	}
	return $ren;
}

/**
 * Flusher rewrite-reglene når sti eller slugs endres - ingen manuell
 * flush skal være nødvendig.
 *
 * @param mixed $old_value Forrige verdi.
 * @param mixed $value     Ny verdi.
 * @return void
 */
function samlab_settings_updated( $old_value, $value ) {
	$sti_felter = array( 'portal_sti', 'slug_vegg', 'slug_behov', 'slug_bedrifter', 'slug_arrangementer', 'slug_handbok' );
	$gammel     = is_array( $old_value ) ? array_intersect_key( $old_value, array_flip( $sti_felter ) ) : array();
	$ny         = is_array( $value ) ? array_intersect_key( $value, array_flip( $sti_felter ) ) : array();
	if ( $gammel === $ny ) {
		return;
	}
	samlab_register_rewrites();
	flush_rewrite_rules();
}
add_action( 'update_option_samlab_settings', 'samlab_settings_updated', 10, 2 );
add_action( 'add_option_samlab_settings', 'samlab_settings_updated', 10, 2 );

/**
 * Legger innstillingssiden under Innstillinger.
 *
 * @return void
 */
function samlab_settings_menu() {
	add_options_page(
		__( 'Samlab', 'samlab' ),
		__( 'Samlab', 'samlab' ),
		'manage_options',
		'samlab',
		'samlab_render_settings_page'
	);
}
add_action( 'admin_menu', 'samlab_settings_menu' );

/**
 * Rendrer innstillingssiden.
 *
 * @return void
 */
function samlab_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings = get_option( 'samlab_settings', array() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Samlab-innstillinger', 'samlab' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'samlab_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( samlab_settings_fields() as $key => $felt ) : ?>
					<?php if ( 'overskrift' === $felt['type'] ) : ?>
						<tr>
							<th scope="row" colspan="2" style="padding-bottom:0;">
								<h2 style="margin-bottom:0;"><?php echo esc_html( $felt['label'] ); ?></h2>
								<?php if ( ! empty( $felt['help'] ) ) : ?>
									<p class="description" style="font-weight:normal;"><?php echo esc_html( $felt['help'] ); ?></p>
								<?php endif; ?>
							</th>
						</tr>
						<?php continue; ?>
					<?php endif; ?>
					<?php if ( 'status' === $felt['type'] ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $felt['label'] ); ?></th>
							<td><p><?php echo esc_html( call_user_func( $felt['status_cb'] ) ); ?></p></td>
						</tr>
						<?php continue; ?>
					<?php endif; ?>
					<tr>
						<th scope="row">
							<label for="samlab-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $felt['label'] ); ?></label>
						</th>
						<td>
							<?php $verdi = isset( $settings[ $key ] ) ? $settings[ $key ] : ''; ?>
							<?php if ( in_array( $felt['type'], array( 'tekstfelt', 'urlliste' ), true ) ) : ?>
								<textarea class="large-text" rows="3"
									id="samlab-<?php echo esc_attr( $key ); ?>"
									name="samlab_settings[<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $verdi ); ?></textarea>
							<?php elseif ( 'avkryssing' === $felt['type'] ) : ?>
								<input type="checkbox" value="1"
									id="samlab-<?php echo esc_attr( $key ); ?>"
									name="samlab_settings[<?php echo esc_attr( $key ); ?>]"
									<?php checked( '1', $verdi ); ?> />
							<?php elseif ( 'ukedag' === $felt['type'] ) : ?>
								<select id="samlab-<?php echo esc_attr( $key ); ?>"
									name="samlab_settings[<?php echo esc_attr( $key ); ?>]">
									<?php foreach ( samlab_settings_ukedager() as $dag => $navn ) : ?>
										<option value="<?php echo esc_attr( (string) $dag ); ?>" <?php selected( (string) $dag, '' === $verdi ? '1' : $verdi ); ?>><?php echo esc_html( $navn ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<input type="text" class="regular-text"
									id="samlab-<?php echo esc_attr( $key ); ?>"
									name="samlab_settings[<?php echo esc_attr( $key ); ?>]"
									value="<?php echo esc_attr( $verdi ); ?>" />
							<?php endif; ?>
							<?php if ( ! empty( $felt['help'] ) ) : ?>
								<p class="description"><?php echo esc_html( $felt['help'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php samlab_skjerm_settings_seksjon(); ?>
	</div>
	<?php
}
