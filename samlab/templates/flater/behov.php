<?php
/**
 * Behov & tilbud: kort med trenger/tilbyr-merker, filtre og
 * «nytt behov»-skjema. Struktur fra prototypens behov-side.
 *
 * @package Samlab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$samlab_flate = samlab_portal_views()['behov'];

// Lesefiltre og statusmeldinger fra URL-en (ingen tilstandsendring).
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$samlab_retning_filter = isset( $_GET['retning'] ) ? sanitize_key( wp_unslash( $_GET['retning'] ) ) : '';
$samlab_type_filter    = isset( $_GET['type'] ) ? sanitize_title( wp_unslash( $_GET['type'] ) ) : '';
$samlab_opprettet      = isset( $_GET['opprettet'] ) ? absint( $_GET['opprettet'] ) : 0;
$samlab_feil           = isset( $_GET['feil'] ) ? sanitize_key( wp_unslash( $_GET['feil'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$samlab_tax_query = array();
if ( in_array( $samlab_retning_filter, array( 'trenger', 'tilbyr' ), true ) ) {
	$samlab_tax_query[] = array(
		'taxonomy' => 'samlab_retning',
		'field'    => 'slug',
		'terms'    => $samlab_retning_filter,
	);
}
if ( '' !== $samlab_type_filter ) {
	$samlab_tax_query[] = array(
		'taxonomy' => 'samlab_behovstype',
		'field'    => 'slug',
		'terms'    => $samlab_type_filter,
	);
}

$samlab_args = array(
	'post_type'      => 'samlab_behov',
	'post_status'    => 'publish',
	'orderby'        => 'date',
	'order'          => 'DESC',
	'posts_per_page' => 50,
);
if ( array() !== $samlab_tax_query ) {
	$samlab_args['tax_query'] = $samlab_tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Flatefilter; lavvolum CPT.
}
$samlab_sporring = new WP_Query( $samlab_args );

$samlab_typer = get_terms(
	array(
		'taxonomy'   => 'samlab_behovstype',
		'hide_empty' => false,
	)
);
if ( is_wp_error( $samlab_typer ) ) {
	$samlab_typer = array();
}
$samlab_mine_bedrifter = is_user_logged_in() ? samlab_behov_bedrifter_for( get_current_user_id() ) : array();
?>
<header class="samlab-flate-hode">
	<h1><?php echo esc_html( $samlab_flate['label'] ); ?></h1>
	<p><?php esc_html_e( 'Det huset trenger og tilbyr akkurat nå - legg inn ditt eget behov nederst.', 'samlab' ); ?></p>
</header>

<?php if ( $samlab_opprettet ) : ?>
	<p class="samlab-melding er-suksess"><?php esc_html_e( 'Behovet er publisert.', 'samlab' ); ?></p>
<?php elseif ( 'tittel' === $samlab_feil ) : ?>
	<p class="samlab-melding er-feil"><?php esc_html_e( 'Behovet trenger en tittel - prøv igjen.', 'samlab' ); ?></p>
<?php endif; ?>

<ul class="samlab-chips samlab-katalog-filter">
	<li><a class="samlab-chip<?php echo '' === $samlab_retning_filter ? ' er-aktiv' : ''; ?>" href="<?php echo esc_url( samlab_portal_url( 'behov' ) ); ?>"><?php esc_html_e( 'Alle', 'samlab' ); ?></a></li>
	<li><a class="samlab-chip<?php echo 'trenger' === $samlab_retning_filter ? ' er-aktiv' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'retning', 'trenger', samlab_portal_url( 'behov' ) ) ); ?>"><?php esc_html_e( 'Trenger', 'samlab' ); ?></a></li>
	<li><a class="samlab-chip<?php echo 'tilbyr' === $samlab_retning_filter ? ' er-aktiv' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'retning', 'tilbyr', samlab_portal_url( 'behov' ) ) ); ?>"><?php esc_html_e( 'Tilbyr', 'samlab' ); ?></a></li>
	<?php foreach ( $samlab_typer as $samlab_type ) : ?>
		<li>
			<a class="samlab-chip<?php echo $samlab_type->slug === $samlab_type_filter ? ' er-aktiv' : ''; ?>"
				href="<?php echo esc_url( add_query_arg( 'type', $samlab_type->slug, samlab_portal_url( 'behov' ) ) ); ?>">
				<?php echo esc_html( $samlab_type->name ); ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>

<?php if ( ! $samlab_sporring->have_posts() ) : ?>
	<p class="samlab-tom"><?php esc_html_e( 'Ingen behov matchet filteret.', 'samlab' ); ?></p>
<?php else : ?>
	<ul class="samlab-kort-grid">
		<?php
		while ( $samlab_sporring->have_posts() ) :
			$samlab_sporring->the_post();
			$samlab_id      = get_the_ID();
			$samlab_retning = get_the_terms( $samlab_id, 'samlab_retning' );
			$samlab_retning = $samlab_retning && ! is_wp_error( $samlab_retning ) ? $samlab_retning[0] : null;
			$samlab_type    = get_the_terms( $samlab_id, 'samlab_behovstype' );
			$samlab_type    = $samlab_type && ! is_wp_error( $samlab_type ) ? $samlab_type[0]->name : '';
			$samlab_frist   = get_post_meta( $samlab_id, '_samlab_frist', true );
			$samlab_budsj   = get_post_meta( $samlab_id, '_samlab_budsjett', true );
			$samlab_kform   = get_post_meta( $samlab_id, '_samlab_kontaktform', true );
			$samlab_komp    = get_post_meta( $samlab_id, '_samlab_kompetanse', true );
			$samlab_bid     = (int) get_post_meta( $samlab_id, '_samlab_bedrift', true );
			$samlab_meta    = array();
			if ( '' !== $samlab_frist ) {
				/* translators: %s: fristen slik den er skrevet inn. */
				$samlab_meta[] = sprintf( __( 'Frist: %s', 'samlab' ), $samlab_frist );
			}
			if ( '' !== $samlab_budsj ) {
				$samlab_meta[] = $samlab_budsj;
			}
			if ( '' !== $samlab_kform ) {
				$samlab_meta[] = $samlab_kform;
			}
			?>
			<li class="samlab-kort" id="behov-<?php echo esc_attr( (string) $samlab_id ); ?>">
				<p class="samlab-behov-merker">
					<?php if ( $samlab_retning ) : ?>
						<span class="samlab-chip <?php echo 'trenger' === $samlab_retning->slug ? 'er-aktiv' : 'er-tilbyr'; ?>"><?php echo esc_html( $samlab_retning->name ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $samlab_type ) : ?>
						<span class="samlab-chip"><?php echo esc_html( $samlab_type ); ?></span>
					<?php endif; ?>
				</p>
				<h2><?php echo esc_html( get_the_title() ); ?></h2>
				<?php if ( '' !== trim( get_the_content() ) ) : ?>
					<p class="samlab-kort-tekst"><?php echo esc_html( wp_trim_words( get_the_content(), 30 ) ); ?></p>
				<?php endif; ?>
				<?php if ( is_array( $samlab_komp ) && array() !== $samlab_komp ) : ?>
					<ul class="samlab-chips">
						<?php foreach ( $samlab_komp as $samlab_k ) : ?>
							<li><span class="samlab-chip"><?php echo esc_html( $samlab_k ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( array() !== $samlab_meta ) : ?>
					<p class="samlab-kort-meta"><?php echo esc_html( implode( ' · ', $samlab_meta ) ); ?></p>
				<?php endif; ?>
				<?php if ( $samlab_bid && 'publish' === get_post_status( $samlab_bid ) ) : ?>
					<p class="samlab-kort-meta">
						<a href="<?php echo esc_url( samlab_portal_url( 'bedrifter', get_post_field( 'post_name', $samlab_bid ) ) ); ?>"><?php echo esc_html( get_the_title( $samlab_bid ) ); ?></a>
					</p>
				<?php endif; ?>
			</li>
		<?php endwhile; ?>
	</ul>
	<?php wp_reset_postdata(); ?>
<?php endif; ?>

<?php if ( current_user_can( 'samlab_create_behov' ) ) : ?>
	<section class="samlab-profil-del samlab-kort samlab-skjema">
		<h2><?php esc_html_e( 'Nytt behov', 'samlab' ); ?></h2>
		<form method="post" action="<?php echo esc_url( samlab_portal_url( 'behov' ) ); ?>">
			<?php wp_nonce_field( 'samlab_nytt_behov', 'samlab_behov_skjema_nonce' ); ?>
			<p>
				<label for="samlab-tittel"><?php esc_html_e( 'Tittel', 'samlab' ); ?> *</label><br />
				<input type="text" id="samlab-tittel" name="samlab_tittel" required />
			</p>
			<p>
				<label for="samlab-beskrivelse"><?php esc_html_e( 'Beskrivelse', 'samlab' ); ?></label><br />
				<textarea id="samlab-beskrivelse" name="samlab_beskrivelse" rows="4"></textarea>
			</p>
			<fieldset>
				<legend><?php esc_html_e( 'Retning', 'samlab' ); ?></legend>
				<label><input type="radio" name="samlab_retning" value="trenger" checked /> <?php esc_html_e( 'Trenger', 'samlab' ); ?></label>
				<label><input type="radio" name="samlab_retning" value="tilbyr" /> <?php esc_html_e( 'Tilbyr', 'samlab' ); ?></label>
			</fieldset>
			<?php if ( array() !== $samlab_typer ) : ?>
				<p>
					<label for="samlab-behovstype"><?php esc_html_e( 'Type', 'samlab' ); ?></label><br />
					<select id="samlab-behovstype" name="samlab_behovstype">
						<option value="0"><?php esc_html_e( '- Velg type -', 'samlab' ); ?></option>
						<?php foreach ( $samlab_typer as $samlab_type ) : ?>
							<option value="<?php echo esc_attr( (string) $samlab_type->term_id ); ?>"><?php echo esc_html( $samlab_type->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>
			<p>
				<label for="samlab-frist"><?php esc_html_e( 'Frist', 'samlab' ); ?></label><br />
				<input type="text" id="samlab-frist" name="samlab_frist" />
			</p>
			<p>
				<label for="samlab-budsjett"><?php esc_html_e( 'Budsjett', 'samlab' ); ?></label><br />
				<input type="text" id="samlab-budsjett" name="samlab_budsjett" />
			</p>
			<p>
				<label for="samlab-kompetanse"><?php esc_html_e( 'Kompetanse (én per linje)', 'samlab' ); ?></label><br />
				<textarea id="samlab-kompetanse" name="samlab_kompetanse" rows="3"></textarea>
			</p>
			<p>
				<label for="samlab-kontaktform"><?php esc_html_e( 'Ønsket kontaktform', 'samlab' ); ?></label><br />
				<input type="text" id="samlab-kontaktform" name="samlab_kontaktform" />
			</p>
			<?php if ( array() !== $samlab_mine_bedrifter ) : ?>
				<p>
					<label for="samlab-bedrift"><?php esc_html_e( 'Bedrift', 'samlab' ); ?></label><br />
					<select id="samlab-bedrift" name="samlab_bedrift">
						<option value="0"><?php esc_html_e( '- Ingen bedrift -', 'samlab' ); ?></option>
						<?php foreach ( $samlab_mine_bedrifter as $samlab_bedrift ) : ?>
							<option value="<?php echo esc_attr( (string) $samlab_bedrift->ID ); ?>"><?php echo esc_html( get_the_title( $samlab_bedrift ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>
			<p><button type="submit" class="samlab-knapp er-primar"><?php esc_html_e( 'Publiser behovet', 'samlab' ); ?></button></p>
		</form>
	</section>
<?php endif; ?>
