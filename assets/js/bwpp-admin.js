/**
 * BWPP admin – live preview, char count, test-post
 * Uses localized BWPP_Admin.root & BWPP_Admin.nonce (see enqueue()).
 */
(() => {
	if ( ! BWPP_Admin || ! document.body.classList.contains( 'settings_page_bwpp-settings' ) ) {
		return;
	}

	const $      = jQuery;
	const API    = (route, data) => $.ajax({
		url:        BWPP_Admin.root + route,
		method:     'POST',
		data:       data,
		beforeSend: xhr => xhr.setRequestHeader( 'X-WP-Nonce', BWPP_Admin.nonce )
	});

	const $form  = $( '#bwpp-settings-form' );
	const $prev  = $( '#bwpp-preview' );
	const $char  = $( '#bwpp-char' );
	const $spin  = $( '#bwpp-spinner' );
	const $btn   = $( '#bwpp-test' );
	const $res   = $( '#bwpp-result' );

	/* live preview -------------------------------------------------- */
	const refresh = () => {
		$spin.addClass( 'is-active' );
		API( 'bwpp/v1/preview', $form.serialize() )
			.done( r => { $prev.text( r.text ); $char.text( r.length ); } )
			.always( () => $spin.removeClass( 'is-active' ) );
	};

	$form.on( 'input', '[name="bwpp_settings[bwp_post_prefix]"],[name="bwpp_settings[bwp_hashtags]"]', refresh );
	refresh();

	/* send test post ------------------------------------------------ */
	$btn.on( 'click', e => {
		e.preventDefault();
		$res.text( '' );
		$spin.addClass( 'is-active' );

		API( 'bwpp/v1/testpost', $form.serialize() )
			.done( () => $res.text( '✓ OK' ).css( 'color', 'green' ) )
			.fail( jq => {
				const msg = jq.responseJSON?.message || 'Error';
				$res.text( msg ).css( 'color', 'red' );
			} )
			.always( () => $spin.removeClass( 'is-active' ) );
	});
})();
// EOF
