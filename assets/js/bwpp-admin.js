/**
 * BWPP admin – live preview, char count, test-post
 * Adds `_wpnonce` to every REST request so the server accepts it.
 */
(() => {

	if ( ! document.body.classList.contains( 'settings_page_bwpp-settings' ) ) {
		return;             // run only on our settings screen
	}

	const $      = jQuery;
	const nonce  = window.wpApiSettings?.nonce || '';           // WP sets this
	const $form  = $( '#bwpp-settings-form' );
	const $prev  = $( '#bwpp-preview' );
	const $char  = $( '#bwpp-char' );
	const $spin  = $( '#bwpp-spinner' );
	const $btn   = $( '#bwpp-test' );
	const $res   = $( '#bwpp-result' );

	/**
	 * Helper – POST to our REST route with form data + nonce.
	 */
	const call = ( route ) => wp.apiRequest( {
		path:   route,
		method: 'POST',
		data:   $form.serialize() + '&_wpnonce=' + encodeURIComponent( nonce )
	} );

	/**
	 * Live preview + char count
	 */
	const refresh = () => {
		$spin.addClass( 'is-active' );
		call( '/bwpp/v1/preview' )
			.done( r => { $prev.text( r.text ); $char.text( r.length ); } )
			.always( () => $spin.removeClass( 'is-active' ) );
	};

	$form.on( 'input', '[name="bwpp_settings[bwp_post_prefix]"],[name="bwpp_settings[bwp_hashtags]"]', refresh );
	refresh();                                                // kick once

	/**
	 * Send Test Post
	 */
	$btn.on( 'click', e => {
		e.preventDefault();
		$res.text( '' );
		$spin.addClass( 'is-active' );

		call( '/bwpp/v1/testpost' )
			.done( () => { $res.text( '✓ OK' ).css( 'color', 'green' ); } )
			.fail( jq => {
				const msg = jq.responseJSON?.message || 'Error';
				$res.text( msg ).css( 'color', 'red' );
			} )
			.always( () => $spin.removeClass( 'is-active' ) );
	} );
})();
// EOF
