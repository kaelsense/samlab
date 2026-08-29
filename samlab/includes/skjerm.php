<?php
/**
 * Infoskjerm (E9): read-only storskjermvisning på
 * /portal-sti/skjerm/<nøkkel>/ med festede oppslag, siste
 * vegginnlegg og kommende arrangementer. Ingen innlogging -
 * den hemmelige nøkkelen i URL-en er porten, og flaten viser
 * ingen persondata utover det veggen selv viser. Nøkkelen er
 * en innstilling og kan regenereres (eller fjernes, som slår
 * skjermen av).
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Infoskjermens URL-slug under portal-stien, standard «skjerm».
 *
 * @return string
 */
function samlab_skjerm_slug() {
	$slug = sanitize_title( samlab_get_setting( 'slug_skjerm', 'skjerm' ) );
	return '' !== $slug ? $slug : 'skjerm';
}

/**
 * Den hemmelige nøkkelen, eller tom streng når skjermen er av.
 *
 * @return string
 */
function samlab_skjerm_nokkel() {
	return (string) get_option( 'samlab_skjerm_nokkel', '' );
}

/**
 * Genererer (eller regenererer) nøkkelen og slår skjermen på.
 * Gamle URL-er slutter å virke umiddelbart.
 *
 * @return string Den nye nøkkelen.
 */
function samlab_skjerm_generer_nokkel() {
	$nokkel = wp_generate_password( 24, false, false );
	update_option( 'samlab_skjerm_nokkel', $nokkel, false );
	return $nokkel;
}

/**
 * Infoskjermens fulle URL, eller tom streng når skjermen er av.
 *
 * @return string
 */
function samlab_skjerm_url() {
	$nokkel = samlab_skjerm_nokkel();
	if ( '' === $nokkel ) {
		return '';
	}
	return home_url( '/' . samlab_portal_path() . '/' . samlab_skjerm_slug() . '/' . rawurlencode( $nokkel ) . '/' );
}

/**
 * Fanger skjermruten før innloggingsporten (prioritet 7 mot portens
 * 9) og svarer selv: riktig nøkkel gir skjermen, alt annet 404 -
 * aldri videresending til innlogging.
 *
 * @return void
 */
function samlab_route_skjerm() {
	if ( samlab_skjerm_slug() !== (string) get_query_var( 'samlab_portal' ) ) {
		return;
	}

	header( 'X-Robots-Tag: noindex, nofollow' );
	nocache_headers();

	$nokkel = samlab_skjerm_nokkel();
	$gitt   = (string) get_query_var( 'samlab_item' );
	if ( '' === $nokkel || '' === $gitt || ! hash_equals( $nokkel, $gitt ) ) {
		status_header( 404 );
		wp_die( esc_html__( 'Fant ikke siden.', 'samlab' ), '', array( 'response' => 404 ) );
	}

	status_header( 200 );
	require SAMLAB_PLUGIN_DIR . 'templates/skjerm.php';
	exit;
}
add_action( 'template_redirect', 'samlab_route_skjerm', 7 );

/**
 * Admin-post-handler for nøkkelen: generer/regenerer eller fjern
 * (slår skjermen av). Kun for manage_options, med nonce.
 *
 * @return void
 */
function samlab_skjerm_nokkel_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Du har ikke tilgang til dette.', 'samlab' ), '', 403 );
	}
	check_admin_referer( 'samlab_skjerm_nokkel' );

	$handling = isset( $_POST['samlab_handling'] ) ? sanitize_key( wp_unslash( $_POST['samlab_handling'] ) ) : '';
	if ( 'generer' === $handling ) {
		samlab_skjerm_generer_nokkel();
	} elseif ( 'fjern' === $handling ) {
		delete_option( 'samlab_skjerm_nokkel' );
	}

	wp_safe_redirect( admin_url( 'options-general.php?page=samlab' ) );
	exit;
}
add_action( 'admin_post_samlab_skjerm_nokkel', 'samlab_skjerm_nokkel_handler' );

/**
 * Infoskjerm-seksjonen nederst på innstillingssiden.
 *
 * @return void
 */
function samlab_skjerm_settings_seksjon() {
	$url = samlab_skjerm_url();
	?>
	<h2><?php esc_html_e( 'Infoskjerm', 'samlab' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Read-only storskjermvisning med festede oppslag, siste innlegg og kommende arrangementer. Den hemmelige nøkkelen i URL-en er porten - del den kun med skjermen. Regenerering slår gamle URL-er av umiddelbart.', 'samlab' ); ?>
	</p>
	<?php if ( '' !== $url ) : ?>
		<p><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></p>
	<?php else : ?>
		<p><em><?php esc_html_e( 'Skjermen er av - generer en nøkkel for å slå den på.', 'samlab' ); ?></em></p>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="samlab_skjerm_nokkel" />
		<?php wp_nonce_field( 'samlab_skjerm_nokkel' ); ?>
		<button type="submit" class="button" name="samlab_handling" value="generer">
			<?php '' !== $url ? esc_html_e( 'Regenerer nøkkel', 'samlab' ) : esc_html_e( 'Generer nøkkel', 'samlab' ); ?>
		</button>
		<?php if ( '' !== $url ) : ?>
			<button type="submit" class="button" name="samlab_handling" value="fjern"><?php esc_html_e( 'Slå av skjermen', 'samlab' ); ?></button>
		<?php endif; ?>
	</form>
	<?php
}
