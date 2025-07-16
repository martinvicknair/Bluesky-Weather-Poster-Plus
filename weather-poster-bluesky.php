<?php
/*
Plugin Name: Weather Poster Bluesky
Description: Posts weather updates (with optional webcam photo) from clientraw.txt to Bluesky. Supports two accounts, facets, and automatic image resize/upload.
Version: 0.2.1
Author: Martin Vicknair
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once plugin_dir_path( __FILE__ ) . 'class-wpb-clientraw-parser.php';
require_once plugin_dir_path( __FILE__ ) . 'class-wpb-bluesky-poster.php';

/* --------------------------------------------------------------------------
 * REGISTER OPTIONS
 * ------------------------------------------------------------------------*/
function wpb_register_settings() {

	$core_opts = [
		'wpb_bluesky_username',
		'wpb_bluesky_app_password',
		'wpb_bluesky_enable_second',
		'wpb_bluesky_username2',
		'wpb_bluesky_app_password2',
		'wpb_clientraw_url',
		'wpb_station_url',
		'wpb_station_display_text',
		'wpb_webcam_image_url',
		'wpb_webcam_display_text',
		'wpb_frequency',
		'wpb_units',
		'wpb_post_prefix',
		'wpb_first_post_hour',
		'wpb_first_post_minute',
		'wpb_hashtags',
	];
	foreach ( $core_opts as $o ) {
		register_setting( 'wpb_settings_group', $o );
	}
	foreach ( wpb_get_field_option_keys() as $k ) {
		register_setting( 'wpb_settings_group', $k );
	}
}
add_action( 'admin_init', 'wpb_register_settings' );

/* checkbox field keys */
function wpb_get_field_option_keys() {
	return [
		'wpb_include_temperature',
		'wpb_include_windchill',
		'wpb_include_humidex',
		'wpb_include_dew_point',
		'wpb_include_max_temp',
		'wpb_include_min_temp',
		'wpb_include_wind_direction',
		'wpb_include_wind_speed',
		'wpb_include_max_gust',
		'wpb_include_humidity',
		'wpb_include_pressure',
		'wpb_include_rain_today',
		'wpb_include_weather_desc',
	];
}

/* friendly next-scheduled string */
function wpb_get_next_scheduled_post_time() {
	$t = wp_next_scheduled( 'wpb_cron_event' );
	if ( ! $t ) { return 'Not scheduled.'; }
	$tz = get_option( 'timezone_string' );
	$dt = new DateTime( "@$t" );
	if ( $tz ) { $dt->setTimezone( new DateTimeZone( $tz ) ); }
	return $dt->format( 'Y-m-d H:i:s (T)' );
}

/* --------------------------------------------------------------------------
 * ADMIN MENU / PAGE
 * ------------------------------------------------------------------------*/
function wpb_add_settings_page() {
	add_options_page(
		'Weather Poster Bluesky',
		'Weather Poster Bluesky',
		'manage_options',
		'weather-poster-bluesky',
		'wpb_render_settings_page'
	);
}
add_action( 'admin_menu', 'wpb_add_settings_page' );

function wpb_render_settings_page() {

	/* preview / response placeholders */
	$preview          = '';
	$response         = '';
	$response_class   = 'notice-info';
	$file_age_warning = '';

	/* Handle “Send Test Post” button */
	if ( isset( $_POST['wpb_test_post'] ) ) {
		wpb_get_test_post_preview_and_response(
			$preview,
			$response,
			$response_class,
			$file_age_warning,
			true      // force_post
		);
	}

	?>
	<div class="wrap">
		<h1>Weather Poster Bluesky</h1>

		<form method="post" action="options.php">
			<?php
			settings_fields( 'wpb_settings_group' );
			do_settings_sections( 'wpb_settings_group' );
			include __DIR__ . '/admin-form-layout.php';   /* giant settings table */
			submit_button();
			?>
		</form>

		<h2>Test Post</h2>
		<form method="post">
			<?php submit_button( 'Send Test Post', 'secondary', 'wpb_test_post' ); ?>
		</form>

		<?php if ( $file_age_warning ) : ?>
			<div class="notice notice-warning"><p><?php echo esc_html( $file_age_warning ); ?></p></div>
		<?php endif; ?>

		<div class="notice notice-info" style="white-space:pre-wrap;">
			<strong>Post Preview:</strong>
			<?php echo "\n" . esc_html( $preview ); ?>
		</div>

		<div class="notice <?php echo esc_attr( $response_class ); ?>" style="white-space:pre-wrap;">
			<strong>API Response:</strong>
			<?php echo "\n" . esc_html( $response ); ?>
		</div>

		<div class="notice notice-info"><strong>Next scheduled post:</strong> <?php echo esc_html( wpb_get_next_scheduled_post_time() ); ?></div>
	</div>
	<?php
}

/* --------------------------------------------------------------------------
 * FORMATTER  (unchanged – use your prior working version)
 * ------------------------------------------------------------------------*/
function wpb_format_weather_output_with_facets( $d, $station_url = '' ) {
	/* … existing implementation … */
}

/* --------------------------------------------------------------------------
 * LOW-LEVEL UNIT HELPERS
 * ------------------------------------------------------------------------*/
function wpb_c_to_f( $c )       { return round( ( $c * 9 / 5 ) + 32, 1 ); }
function wpb_knots_to_kmh( $k ) { return round( 1.852 * $k, 1 ); }
function wpb_knots_to_mph( $k ) { return round( 1.15078 * $k, 1 ); }
function wpb_mm_to_in( $mm )    { return round( 0.0393701 * $mm, 2 ); }
function wpb_hpa_to_inhg( $h )  { return round( 0.02953   * $h, 2 ); }

function wpb_get_remote_file_last_modified( $url ) {
	$h = @get_headers( $url, 1 );
	if ( ! $h || empty( $h['Last-Modified'] ) ) { return false; }
	$lm = is_array( $h['Last-Modified'] ) ? end( $h['Last-Modified'] ) : $h['Last-Modified'];
	return strtotime( $lm ) ?: false;
}

/* --------------------------------------------------------------------------
 * CRON SCHEDULING  (identical logic, re-registered for safety)
 * ------------------------------------------------------------------------*/
function wpb_schedule_event() {
	wpb_clear_scheduled_event();
	$int  = (int) get_option( 'wpb_frequency', 1 ) * 3600;
	$h    = (int) get_option( 'wpb_first_post_hour', 0 );
	$m    = (int) get_option( 'wpb_first_post_minute', 0 );
	$tz   = get_option( 'timezone_string' );
	$now  = current_time( 'timestamp' );
	$dt   = new DateTime( 'now', $tz ? new DateTimeZone( $tz ) : null );
	$dt->setTime( $h, $m, 0 );
	$first = $dt->getTimestamp();
	while ( $first <= $now ) { $first += $int; }
	wp_schedule_event( $first, 'wpb_custom_interval', 'wpb_cron_event' );
}
add_filter( 'cron_schedules', function ( $s ) {
	$s['wpb_custom_interval'] = [
		'interval' => (int) get_option( 'wpb_frequency', 1 ) * 3600,
		'display'  => 'WPB custom interval',
	];
	return $s;
} );
function wpb_clear_scheduled_event() {
	if ( $t = wp_next_scheduled( 'wpb_cron_event' ) ) {
		wp_unschedule_event( $t, 'wpb_cron_event' );
	}
}
register_activation_hook( __FILE__,  'wpb_schedule_event' );
register_deactivation_hook( __FILE__, 'wpb_clear_scheduled_event' );

add_action( 'wpb_cron_event', function () {
	$parser = new WPB_Clientraw_Parser();
	$data   = $parser->parse( get_option( 'wpb_clientraw_url' ) );
	if ( ! $data ) { return; }
	$post = wpb_format_weather_output_with_facets( $data, get_option( 'wpb_station_url' ) );
	wpb_post_to_bluesky_accounts( $post );
} );

/* --------------------------------------------------------------------------
 * AJAX LIVE PREVIEW / CHAR-COUNT  (keep original handlers)
 * ------------------------------------------------------------------------*/


/* --------------------------------------------------------------------------
 * AJAX, CONVERSION HELPERS, FORMATTER, etc.
 * ------------------------------------------------------------------------*/

function wpb_get_test_post_preview_and_response( &$preview, &$response, &$class, &$age_warn, $force = false ) {

	$preview   = '';
	$response  = '';
	$class     = 'notice-info';
	$age_warn  = '';

	$clientraw = get_option( 'wpb_clientraw_url' );
	$station   = get_option( 'wpb_station_url', '' );
	$freq      = (int) get_option( 'wpb_frequency', 1 );
	$max_age   = $freq * 3600;

	$lm = wpb_get_remote_file_last_modified( $clientraw );
	$now = time();

	if ( ! $force && $lm && ( $now - $lm ) > $max_age ) {
		$age = $now - $lm;
		$preview   = "No new updates (clientraw.txt is older than interval).";
		$age_warn  = "clientraw.txt age: " . gmdate( 'H:i:s', $age );
		$class     = 'notice-warning';
		return false;
	}

	try {
		$parser = new WPB_Clientraw_Parser();
		$data   = $parser->parse( $clientraw );
		if ( ! $data ) {
			throw new Exception( 'Unable to parse clientraw.txt.' );
		}

		$post_struct = wpb_format_weather_output_with_facets( $data, $station );
		$preview     = $post_struct['text'];
		$results     = wpb_post_to_bluesky_accounts( $post_struct );

		/* ==== safe result formatting ==== */
		if ( is_array( $results ) ) {
			$response = '';
			foreach ( $results as $acc => $resp ) {
				$response .= $acc . ': ';
				$response .= ( $resp === true ) ? 'Success!' : (string) $resp;
				$response .= "\n";
			}
			$class = 'notice-success';
		} else {
			$response = ( $results === true || $results === null )
				? 'Success!'
				: (string) $results;
			$class = 'notice-success';
		}
		return true;

	} catch ( Exception $e ) {
		$response = $e->getMessage();
		$class    = 'notice-error';
		return false;
	}
}

/* --------------------------------------------------------------------------
 * POST-TO-BLUESKY (multi-account, facets, inline image)  <<< UPDATED
 * ------------------------------------------------------------------------*/
function wpb_post_to_bluesky_accounts( $post_struct ) {

	$u1   = get_option( 'wpb_bluesky_username' );
	$p1   = get_option( 'wpb_bluesky_app_password' );
	$u2   = get_option( 'wpb_bluesky_username2' );
	$p2   = get_option( 'wpb_bluesky_app_password2' );
	$en2  = get_option( 'wpb_bluesky_enable_second', '' );

	$settings = get_option( 'wpb_settings' );
	if ( ! is_array( $settings ) ) {
		$settings = [];
	}
	$backup = $settings;

	$results = [];

	/* -- primary -- */
	if ( $u1 && $p1 ) {
		$settings['wpb_bluesky_username'] = $u1;
		$settings['wpb_bluesky_password'] = $p1;
		update_option( 'wpb_settings', $settings );
		try {
			$results['Account 1'] = ( new WPB_Bluesky_Poster() )
				->post_struct_to_bluesky( $post_struct, true );
		} catch ( Exception $e ) {
			$results['Account 1'] = $e->getMessage();
		}
	}

	/* -- secondary (optional) -- */
	if ( 'on' === $en2 && $u2 && $p2 ) {
		$settings['wpb_bluesky_username'] = $u2;
		$settings['wpb_bluesky_password'] = $p2;
		update_option( 'wpb_settings', $settings );
		try {
			$results['Account 2'] = ( new WPB_Bluesky_Poster() )
				->post_struct_to_bluesky( $post_struct, true );
		} catch ( Exception $e ) {
			$results['Account 2'] = $e->getMessage();
		}
	}

	update_option( 'wpb_settings', $backup );
	return $results;
}
// --------------------------------------------------------------------------

// ---------- WP Cron: schedule, clear, and handle scheduled posts ----------
function wpb_schedule_event()
{
    wpb_clear_scheduled_event();
    $frequency = intval(get_option('wpb_frequency', 1));
    $interval_seconds = $frequency * 3600;
    $hour = intval(get_option('wpb_first_post_hour', 0));
    $minute = intval(get_option('wpb_first_post_minute', 0));
    $tz = get_option('timezone_string');
    $now = current_time('timestamp');
    $dt = new DateTime('now', $tz ? new DateTimeZone($tz) : null);
    $dt->setTime($hour, $minute, 0);
    $first_post = $dt->getTimestamp();
    while ($first_post <= $now) {
        $first_post += $interval_seconds;
    }
    wp_schedule_event($first_post, 'wpb_custom_interval', 'wpb_cron_event');
}
add_filter('cron_schedules', function ($schedules) {
    $frequency = intval(get_option('wpb_frequency', 1));
    $interval_seconds = $frequency * 3600;
    $schedules['wpb_custom_interval'] = array(
        'interval' => $interval_seconds,
        'display' => "Every $frequency hour(s)"
    );
    return $schedules;
});
function wpb_clear_scheduled_event()
{
    $timestamp = wp_next_scheduled('wpb_cron_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'wpb_cron_event');
    }
}
register_deactivation_hook(__FILE__, 'wpb_clear_scheduled_event');
add_action('update_option_wpb_frequency', 'wpb_schedule_event');
add_action('update_option_wpb_first_post_hour', 'wpb_schedule_event');
add_action('update_option_wpb_first_post_minute', 'wpb_schedule_event');
register_activation_hook(__FILE__, 'wpb_schedule_event');

add_action('wpb_cron_event', 'wpb_cron_handler');
function wpb_cron_handler()
{
    $result = wpb_post_weather_update();
    if ($result === true) {
        update_option('wpb_last_post_time', current_time('timestamp'));
    }
}

// ---------- AJAX: Live post preview (returns formatted preview) ----------
add_action('wp_ajax_wpb_live_post_preview', 'wpb_live_post_preview_ajax');
function wpb_live_post_preview_ajax()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Not allowed');
    }
    $clientraw_url = get_option('wpb_clientraw_url');
    $station_url = get_option('wpb_station_url', '');
    $post_prefix = sanitize_text_field($_POST['post_prefix'] ?? get_option('wpb_post_prefix', 'Weather Update'));
    $units = $_POST['units'] ?? get_option('wpb_units', 'both');
    $hashtags = sanitize_text_field($_POST['hashtags'] ?? get_option('wpb_hashtags', ''));
    $station_display = sanitize_text_field($_POST['station_display_text'] ?? get_option('wpb_station_display_text', 'Live Station'));
    $webcam_url = isset($_POST['webcam_image_url']) ? trim(sanitize_text_field($_POST['webcam_image_url'])) : get_option('wpb_webcam_image_url', '');
    $webcam_display = isset($_POST['webcam_display_text']) ? trim(sanitize_text_field($_POST['webcam_display_text'])) : get_option('wpb_webcam_display_text', 'Webcam Snapshot');
    $fields = wpb_get_field_option_keys();
    $include = [];
    foreach ($fields as $key) {
        $include[$key] = (isset($_POST[$key]) && $_POST[$key] === 'on') ? 'on' : 'off';
    }
    $old_prefix = get_option('wpb_post_prefix');
    $old_units = get_option('wpb_units');
    $old_hashtags = get_option('wpb_hashtags');
    $old_station_display = get_option('wpb_station_display_text');
    $old_webcam_url = get_option('wpb_webcam_image_url');
    $old_webcam_display = get_option('wpb_webcam_display_text');
    $old_fields = [];
    foreach ($fields as $key) $old_fields[$key] = get_option($key);

    update_option('wpb_post_prefix', $post_prefix, false);
    update_option('wpb_units', $units, false);
    update_option('wpb_hashtags', $hashtags, false);
    update_option('wpb_station_display_text', $station_display, false);
    update_option('wpb_webcam_image_url', $webcam_url, false);
    update_option('wpb_webcam_display_text', $webcam_display, false);
    foreach ($fields as $key) {
        update_option($key, $include[$key], false);
    }

    try {
        if (!$clientraw_url) throw new Exception("No clientraw file available to parse");
        $parser = new WPB_Clientraw_Parser();
        $data = $parser->parse($clientraw_url);
        if (!$data || empty($data)) throw new Exception("No clientraw file available to parse");
        $post_struct = wpb_format_weather_output_with_facets($data, $station_url);
        wp_send_json_success(['preview' => $post_struct['text']]);
    } catch (Exception $e) {
        wp_send_json_error(['preview' => 'No clientraw file available to parse']);
    } finally {
        update_option('wpb_post_prefix', $old_prefix, false);
        update_option('wpb_units', $old_units, false);
        update_option('wpb_hashtags', $old_hashtags, false);
        update_option('wpb_station_display_text', $old_station_display, false);
        update_option('wpb_webcam_image_url', $old_webcam_url, false);
        update_option('wpb_webcam_display_text', $old_webcam_display, false);
        foreach ($fields as $key) {
            update_option($key, $old_fields[$key], false);
        }
    }
}

// ---------- AJAX: Live character count estimation ----------
add_action('wp_ajax_wpb_estimate_char_count', 'wpb_estimate_char_count_ajax');
function wpb_estimate_char_count_ajax()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Not allowed');
    }
    $post_prefix = sanitize_text_field($_POST['post_prefix'] ?? 'Weather Update');
    $units = $_POST['units'] ?? 'both';
    $hashtags = sanitize_text_field($_POST['hashtags'] ?? '');
    $station_display = sanitize_text_field($_POST['station_display_text'] ?? get_option('wpb_station_display_text', 'Live Station'));
    $webcam_url = isset($_POST['webcam_image_url']) ? trim(sanitize_text_field($_POST['webcam_image_url'])) : get_option('wpb_webcam_image_url', '');
    $webcam_display = isset($_POST['webcam_display_text']) ? trim(sanitize_text_field($_POST['webcam_display_text'])) : get_option('wpb_webcam_display_text', 'Webcam Snapshot');
    $fields = wpb_get_field_option_keys();
    $include = [];
    foreach ($fields as $key) {
        $include[$key] = (isset($_POST[$key]) && $_POST[$key] === 'on');
    }
    $dummy_data = [
        'temperature' => 16.3,
        'wind_direction' => 225,
        'wind_speed' => 5,
        'humidity' => 79,
        'pressure' => 1010,
        'rain_today' => 0,
        'windchill' => 12.0,
        'humidex' => 18.4,
        'max_temp' => 18.2,
        'min_temp' => 12.3,
        'dew_point' => 10.1,
        'max_gust' => 11,
        'weather_desc' => 'Cloudy with a chance of rain'
    ];
    $station_url = 'https://station.url/';
    $old_prefix = get_option('wpb_post_prefix');
    $old_units = get_option('wpb_units');
    $old_hashtags = get_option('wpb_hashtags');
    $old_station_display = get_option('wpb_station_display_text');
    $old_webcam_url = get_option('wpb_webcam_image_url');
    $old_webcam_display = get_option('wpb_webcam_display_text');
    $old_fields = [];
    foreach ($fields as $key) $old_fields[$key] = get_option($key);

    update_option('wpb_post_prefix', $post_prefix, false);
    update_option('wpb_units', $units, false);
    update_option('wpb_hashtags', $hashtags, false);
    update_option('wpb_station_display_text', $station_display, false);
    update_option('wpb_webcam_image_url', $webcam_url, false);
    update_option('wpb_webcam_display_text', $webcam_display, false);
    foreach ($fields as $key) {
        update_option($key, $include[$key] ? 'on' : 'off', false);
    }
    $post_struct = wpb_format_weather_output_with_facets($dummy_data, $station_url);
    $count = mb_strlen($post_struct['text']);

    update_option('wpb_post_prefix', $old_prefix, false);
    update_option('wpb_units', $old_units, false);
    update_option('wpb_hashtags', $old_hashtags, false);
    update_option('wpb_station_display_text', $old_station_display, false);
    update_option('wpb_webcam_image_url', $old_webcam_url, false);
    update_option('wpb_webcam_display_text', $old_webcam_display, false);
    foreach ($fields as $key) {
        update_option($key, $old_fields[$key], false);
    }
    wp_send_json_success(['count' => $count, 'preview' => $post_struct['text']]);
}

?>