/**
 * BWPP admin – live preview, char count, test-post.
 * Sends the REST nonce in *both* the X-WP-Nonce header *and* as _wpnonce.
 */
(() => {
	if ( ! window.BWPP_Admin || ! document.body.classList.contains( 'settings_page_bwpp-settings' ) ) {
		return;
	}

	const $      = jQuery;
	const nonce  = BWPP_Admin.nonce;
	const root   = BWPP_Admin.root.replace( /\/$/, '' );        // trim trailing /

	/* helper – POST to route with form data + nonce (in header + body) */
	const api = (route, data) => $.ajax({
		url:        `${root}/${route}`,
		method:     'POST',
		data:       `${data}&_wpnonce=${encodeURIComponent( nonce )}`,
		beforeSend: xhr => xhr.setRequestHeader( 'X-WP-Nonce', nonce )
	});

	const $form  = $('#bwpp-settings-form');
	const $prev  = $('#bwpp-preview');
	const $char  = $('#bwpp-char');
	const $spin  = $('#bwpp-spinner');
	const $btn   = $('#bwpp-test');
	const $res   = $('#bwpp-result');

	/* live preview + char count */
	const refresh = () => {
		$spin.addClass('is-active');
		api('bwpp/v1/preview', $form.serialize())
			.done(r => { $prev.text(r.text); $char.text(r.length); })
			.always(() => $spin.removeClass('is-active'));
	};

	$form.on('input', '[name="bwpp_settings[bwp_post_prefix]"],[name="bwpp_settings[bwp_hashtags]"]', refresh);
	refresh();                                                // kick once

	/* send test post */
	$btn.on('click', e => {
		e.preventDefault();
		$res.text('');
		$spin.addClass('is-active');

		api('bwpp/v1/testpost', $form.serialize())
			.done(()  => $res.text('✓ OK').css('color','green'))
			.fail(jq => {
				const msg = jq.responseJSON?.message || 'Error';
				$res.text(msg).css('color','red');
			})
			.always(() => $spin.removeClass('is-active'));
	});
})();
// EOF
