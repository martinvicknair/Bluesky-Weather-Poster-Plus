/**
 * BWPP admin – live preview, char count, test-post
 * Loads only on Settings ▸ Bluesky Weather Poster Plus.
 */

( () => {
	if ( ! document.body.classList.contains( 'settings_page_bwpp-settings' ) ) { return; }

	const $ = jQuery;

	const $form    = $( '#bwpp-settings-form' );
	const $prefix  = $( '[name="bwpp_settings[bwp_post_prefix]"]' );
	const $tags    = $( '[name="bwpp_settings[bwp_hashtags]"]' );
	const $prev    = $( '#bwpp-preview' );
	const $char    = $( '#bwpp-char' );
	const $spin    = $( '#bwpp-spinner' );
	const $btnTest = $( '#bwpp-test' );
	const $result  = $( '#bwpp-result' );

	const refresh = () => {
		$spin.addClass( 'is-active' );
		wp.apiRequest( {
			path:   '/bwpp/v1/preview',
			method: 'POST',
			data:   $form.serialize()
		} ).done( r => {
			$prev.text( r.text );
			$char.text( r.length );
		} ).always( () => $spin.removeClass( 'is-active' ) );
	};

	$prefix.on( 'input', refresh );
	$tags.on(   'input', refresh );
	refresh(); // initial

	$btnTest.on( 'click', e => {
		e.preventDefault();
		$result.text( '' );
		$spin.addClass( 'is-active' );
		wp.apiRequest( {
			path:   '/bwpp/v1/testpost',
			method: 'POST',
			data:   $form.serialize()
		} ).done( () => {
			$result.text( '✓ OK' ).css( 'color', 'green' );
		} ).fail( jq => {
			const msg = jq.responseJSON?.message || 'Error';
			$result.text( msg ).css( 'color', 'red' );
		} ).always( () => $spin.removeClass( 'is-active' ) );
	} );
} )();
// EOF
