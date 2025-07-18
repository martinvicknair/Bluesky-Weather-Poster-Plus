/**
 * BWPP admin – live preview, char count, test-post (with REST nonce).
 */
(() => {
	if (!document.body.classList.contains('settings_page_bwpp-settings')) { return; }

	const $ = jQuery;
	const nonce   = window.wpApiSettings?.nonce || '';          // ← grab nonce
	const $form   = $('#bwpp-settings-form');
	const $prev   = $('#bwpp-preview');
	const $char   = $('#bwpp-char');
	const $spin   = $('#bwpp-spinner');
	const $btn    = $('#bwpp-test');
	const $result = $('#bwpp-result');

	const send = (path) =>
		wp.apiRequest({ path, method:'POST', data:$form.serialize(), nonce });

	const refresh = () => {
		$spin.addClass('is-active');
		send('/bwpp/v1/preview').done(r => {
			$prev.text(r.text);
			$char.text(r.length);
		}).always(() => $spin.removeClass('is-active'));
	};

	$form.on('input', '[name="bwpp_settings[bwp_post_prefix]"],[name="bwpp_settings[bwp_hashtags]"]', refresh);
	refresh();

	$btn.on('click', e => {
		e.preventDefault();
		$result.text(''); $spin.addClass('is-active');
		send('/bwpp/v1/testpost').done(() => {
			$result.text('✓ OK').css('color','green');
		}).fail(jq => {
			const msg = jq.responseJSON?.message || 'Error';
			$result.text(msg).css('color','red');
		}).always(() => $spin.removeClass('is-active'));
	});
})();
// EOF
