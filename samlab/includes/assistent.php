<?php
/**
 * Assistenten (fase F) - bootstrap og innstillinger. Denne filen
 * lastes alltid (den eier av/på-bryteren og innstillingene), men
 * selve modulen (includes/assistent/modul.php med cron, REST og
 * widget) lastes KUN når modulen er slått på. Portalen fungerer
 * fullt ut uten assistenten.
 *
 * API-nøkkelen leses utelukkende fra konstanten
 * SAMLAB_CLAUDE_API_KEY i wp-config.php - aldri fra databasen, og
 * verdien vises aldri i admin (kun funnet/ikke funnet).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Om assistent-modulen er slått på (standard av).
 *
 * @return bool
 */
function samlab_assistent_aktiv() {
	return '1' === samlab_get_setting( 'assistent_aktiv' );
}

/**
 * Assistentens visningsnavn, standard «Assistenten».
 *
 * @return string
 */
function samlab_assistent_navn() {
	return samlab_get_setting( 'assistent_navn', __( 'Assistenten', 'samlab' ) );
}

/**
 * Velkomstmeldingen i chat-widgeten.
 *
 * @return string
 */
function samlab_assistent_velkomst() {
	return samlab_get_setting( 'assistent_velkomst', __( 'Hei! Spør meg om huset, medlemmene eller det praktiske.', 'samlab' ) );
}

/**
 * Toneinstruksen som legges i systemprompten (F3).
 *
 * @return string
 */
function samlab_assistent_tone() {
	return samlab_get_setting( 'assistent_tone', '' );
}

/**
 * Modellen kall går mot, standard claude-opus-5.
 *
 * @return string
 */
function samlab_assistent_modell() {
	return samlab_get_setting( 'assistent_modell', 'claude-opus-5' );
}

/**
 * Eksterne kunnskapskilder (URL-er) for kunnskaps-cronen (F2).
 *
 * @return string[]
 */
function samlab_assistent_kilder() {
	$linjer = explode( "\n", samlab_get_setting( 'assistent_kilder', '' ) );
	return array_values( array_filter( array_map( 'trim', $linjer ) ) );
}

/**
 * Om API-nøkkelen finnes i wp-config.php. Verdien leses aldri ut
 * her - kun om konstanten er satt og ikke-tom.
 *
 * @return bool
 */
function samlab_assistent_har_nokkel() {
	return defined( 'SAMLAB_CLAUDE_API_KEY' ) && '' !== (string) SAMLAB_CLAUDE_API_KEY;
}

/**
 * Statustekst for nøkkelen til innstillingssiden - aldri verdien.
 *
 * @return string
 */
function samlab_assistent_nokkel_status() {
	if ( samlab_assistent_har_nokkel() ) {
		return __( 'Funnet i wp-config.php (SAMLAB_CLAUDE_API_KEY er satt).', 'samlab' );
	}
	return __( 'Ikke funnet. Legg til define( \'SAMLAB_CLAUDE_API_KEY\', \'…\' ); i wp-config.php - nøkkelen lagres aldri i databasen.', 'samlab' );
}

/**
 * Statustekst for kunnskapsgrunnlaget til innstillingssiden. Leser
 * option direkte så statusen vises også når modulen er av.
 *
 * @return string
 */
function samlab_assistent_kunnskap_status() {
	$grunnlag = get_option( 'samlab_kunnskap', null );
	if ( ! is_array( $grunnlag ) || empty( $grunnlag['bygget'] ) ) {
		return __( 'Ikke bygget ennå - bygges automatisk hver dag når modulen er på, eller med knappen under.', 'samlab' );
	}
	$tekst = sprintf(
		/* translators: 1: versjonsnummer, 2: dato og tid, 3: størrelse. */
		__( 'Versjon %1$d, bygget %2$s (%3$s).', 'samlab' ),
		(int) $grunnlag['versjon'],
		date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $grunnlag['bygget'] ),
		size_format( (int) $grunnlag['storrelse'] )
	);
	if ( ! empty( $grunnlag['kilder_feilet'] ) ) {
		/* translators: %s: kommaseparert liste over kilder som feilet. */
		$tekst .= ' ' . sprintf( __( 'Kilder som feilet: %s.', 'samlab' ), implode( ', ', $grunnlag['kilder_feilet'] ) );
	}
	return $tekst;
}

/**
 * «Bygg nå»-seksjonen nederst på innstillingssiden - kun når
 * modulen er på (handleren finnes bare da).
 *
 * @return void
 */
function samlab_assistent_settings_seksjon() {
	if ( ! samlab_assistent_aktiv() ) {
		return;
	}
	?>
	<h2><?php esc_html_e( 'Kunnskapsgrunnlaget', 'samlab' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Bygges automatisk hver dag fra portalinnholdet og de eksterne kildene. Grunnlaget skal aldri inneholde passord eller sensitive detaljer.', 'samlab' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="samlab_bygg_kunnskap" />
		<?php wp_nonce_field( 'samlab_bygg_kunnskap' ); ?>
		<button type="submit" class="button"><?php esc_html_e( 'Bygg nå', 'samlab' ); ?></button>
	</form>
	<?php
}

/**
 * Rydder kunnskaps-cronen når modulen er av (planleggingen skjer i
 * modulen, som bare lastes når den er på).
 *
 * @return void
 */
function samlab_assistent_rydd_cron() {
	if ( ! samlab_assistent_aktiv() && wp_next_scheduled( 'samlab_assistent_kunnskap' ) ) {
		wp_clear_scheduled_hook( 'samlab_assistent_kunnskap' );
	}
}
add_action( 'init', 'samlab_assistent_rydd_cron' );

/*
 * Selve modulen lastes kun når den er slått på - ingen
 * assistent-kode (cron, REST, widget) ellers.
 */
if ( samlab_assistent_aktiv() ) {
	require_once SAMLAB_PLUGIN_DIR . 'includes/assistent/modul.php';
}
