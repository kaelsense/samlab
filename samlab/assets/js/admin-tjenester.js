/**
 * Tjeneste-repeateren i bedrifts-metaboksen.
 *
 * Malraden ligger i et <script type="text/template"> i markupen - det
 * er data, ikke atferd, og den er genuint per innlegg. Denne fila er
 * atferden, og lastes kun på bedriftseditoren.
 *
 * Neste indeks leses fra den høyeste som allerede finnes, ikke fra
 * antall rader: har meta hull i nøklene, ville en teller basert på
 * antall gitt en indeks som alt er i bruk, og to rader ville smeltet
 * sammen til én ved lagring.
 */
( function () {
	var beholder = document.getElementById( 'samlab-tjenester' );
	var knapp    = document.getElementById( 'samlab-legg-til-tjeneste' );
	var mal      = document.getElementById( 'samlab-tjeneste-mal' );

	if ( ! beholder || ! knapp || ! mal ) {
		return;
	}

	var teller = 0;
	Array.prototype.forEach.call( beholder.children, function ( rad ) {
		var i = parseInt( rad.getAttribute( 'data-samlab-indeks' ), 10 );
		if ( ! isNaN( i ) && i >= teller ) {
			teller = i + 1;
		}
	} );

	knapp.addEventListener( 'click', function () {
		var div = document.createElement( 'div' );
		div.innerHTML = mal.innerHTML.replace( /__i__/g, String( teller++ ) );
		var rad = div.firstElementChild;
		beholder.appendChild( rad );

		// Fokus følger med til den nye raden - ellers blir man stående
		// på knappen og må lete seg fram med tastaturet.
		var felt = rad.querySelector( 'input[type="text"]' );
		if ( felt ) {
			felt.focus();
		}
	} );

	beholder.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'samlab-fjern-tjeneste' ) ) {
			return;
		}
		var rad = e.target.closest( '.samlab-tjeneste' );
		if ( rad ) {
			rad.remove();
			knapp.focus();
		}
	} );
}() );
