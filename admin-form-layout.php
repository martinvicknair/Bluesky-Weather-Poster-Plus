<?php
/* This file outputs the big <table> of settings fields.
 * It’s included by weather-poster-bluesky.php to keep the bootstrap shorter.
 */

$opt = fn( $k, $d='' ) => get_option( $k, $d );
$checked = fn( $k ) => checked( $opt( $k, 'on' ), 'on', false );

/* shorthand variables so HTML is readable */
$units       = $opt( 'wpb_units', 'both' );
$freq        = $opt( 'wpb_frequency', '1' );
$enable2     = ( 'on' === $opt( 'wpb_bluesky_enable_second', '' ) );
$first_h     = $opt( 'wpb_first_post_hour', '0' );
$first_m     = $opt( 'wpb_first_post_minute', '0' );
?>
<table class="form-table">
	<tr>
		<th><label for="wpb_bluesky_username">Bluesky Username</label></th>
		<td><input type="text" name="wpb_bluesky_username" value="<?php echo esc_attr( $opt( 'wpb_bluesky_username' ) ); ?>" class="regular-text" /></td>
	</tr>
	<tr>
		<th><label for="wpb_bluesky_app_password">Bluesky App Password</label></th>
		<td><input type="password" name="wpb_bluesky_app_password" value="<?php echo esc_attr( $opt( 'wpb_bluesky_app_password' ) ); ?>" class="regular-text" autocomplete="new-password" /></td>
	</tr>

	<tr><th colspan="2"><hr></th></tr>

	<tr>
		<th><label for="wpb_bluesky_enable_second">Second account</label></th>
		<td>
			<label>
				<input type="checkbox" name="wpb_bluesky_enable_second" <?php checked( $enable2 ); ?>>
				Enable posting to a second Bluesky account
			</label>
		</td>
	</tr>
	<tr>
		<th><label for="wpb_bluesky_username2">2nd Username</label></th>
		<td><input type="text" name="wpb_bluesky_username2" value="<?php echo esc_attr( $opt( 'wpb_bluesky_username2' ) ); ?>" class="regular-text" /></td>
	</tr>
	<tr>
		<th><label for="wpb_bluesky_app_password2">2nd App Password</label></th>
		<td><input type="password" name="wpb_bluesky_app_password2" value="<?php echo esc_attr( $opt( 'wpb_bluesky_app_password2' ) ); ?>" class="regular-text" autocomplete="new-password" /></td>
	</tr>

	<tr><th colspan="2"><hr></th></tr>

	<tr>
		<th><label for="wpb_clientraw_url">clientraw.txt URL</label></th>
		<td><input type="url" name="wpb_clientraw_url" value="<?php echo esc_attr( $opt( 'wpb_clientraw_url' ) ); ?>" class="regular-text" required></td>
	</tr>
	<tr>
		<th><label for="wpb_station_url">Station URL</label></th>
		<td><input type="url" name="wpb_station_url" value="<?php echo esc_attr( $opt( 'wpb_station_url' ) ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="wpb_station_display_text">Station Link Text</label></th>
		<td><input type="text" name="wpb_station_display_text" value="<?php echo esc_attr( $opt( 'wpb_station_display_text', 'Live Station' ) ); ?>" class="regular-text"></td>
	</tr>

	<tr><th colspan="2"><hr></th></tr>

	<tr>
		<th><label for="wpb_webcam_image_url">Webcam Image URL</label></th>
		<td><input type="url" name="wpb_webcam_image_url" value="<?php echo esc_attr( $opt( 'wpb_webcam_image_url' ) ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="wpb_webcam_display_text">Webcam Alt Text</label></th>
		<td><input type="text" name="wpb_webcam_display_text" value="<?php echo esc_attr( $opt( 'wpb_webcam_display_text', 'Webcam Snapshot' ) ); ?>" class="regular-text"></td>
	</tr>

	<tr><th colspan="2"><hr></th></tr>

	<tr>
		<th><label for="wpb_frequency">Posting Frequency</label></th>
		<td>
			<select name="wpb_frequency" id="wpb_frequency">
				<?php foreach ( [1,2,3,6,12,24] as $v ) : ?>
					<option value="<?php echo $v; ?>" <?php selected( $freq, (string) $v ); ?>>Every <?php echo $v; ?> hour<?php echo $v>1?'s':''; ?></option>
				<?php endforeach; ?>
			</select>
			<br>
			<label>
				Post at:
				<select name="wpb_first_post_hour"><?php for ( $h=0;$h<24;$h++ ) printf( '<option value="%d"%s>%02d</option>', $h, selected($first_h,(string)$h,false), $h ); ?></select> :
				<select name="wpb_first_post_minute"><?php for ( $m=0;$m<60;$m++ ) printf( '<option value="%d"%s>%02d</option>', $m, selected($first_m,(string)$m,false), $m ); ?></select>
			</label>
		</td>
	</tr>

	<tr>
		<th><label for="wpb_units">Units</label></th>
		<td>
			<select name="wpb_units" id="wpb_units">
				<option value="metric"   <?php selected( $units,'metric' ); ?>>Metric</option>
				<option value="imperial" <?php selected( $units,'imperial' ); ?>>Imperial</option>
				<option value="both"     <?php selected( $units,'both' ); ?>>Both</option>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="wpb_post_prefix">Post Prefix</label></th>
		<td><input type="text" name="wpb_post_prefix" value="<?php echo esc_attr( $opt( 'wpb_post_prefix','Weather Update' ) ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="wpb_hashtags">Hashtags</label></th>
		<td><input type="text" name="wpb_hashtags" value="<?php echo esc_attr( $opt( 'wpb_hashtags' ) ); ?>" class="regular-text"></td>
	</tr>

	<tr><th colspan="2"><hr></th></tr>

	<tr><th>Post Content Fields</th><td>
		<fieldset style="margin-bottom:1em;"><legend><strong>Temperature &amp; Feel</strong></legend>
			<label><input type="checkbox" name="wpb_include_temperature"  <?php echo $checked('wpb_include_temperature');  ?>> Temperature</label><br>
			<label><input type="checkbox" name="wpb_include_windchill"    <?php echo $checked('wpb_include_windchill');    ?>> Windchill</label><br>
			<label><input type="checkbox" name="wpb_include_humidex"      <?php echo $checked('wpb_include_humidex');      ?>> Humidex</label><br>
			<label><input type="checkbox" name="wpb_include_dew_point"    <?php echo $checked('wpb_include_dew_point');    ?>> Dew Point</label><br>
			<label><input type="checkbox" name="wpb_include_max_temp"     <?php echo $checked('wpb_include_max_temp');     ?>> Max Temp Today</label><br>
			<label><input type="checkbox" name="wpb_include_min_temp"     <?php echo $checked('wpb_include_min_temp');     ?>> Min Temp Today</label>
		</fieldset>

		<fieldset style="margin-bottom:1em;"><legend><strong>Wind</strong></legend>
			<label><input type="checkbox" name="wpb_include_wind_direction" <?php echo $checked('wpb_include_wind_direction'); ?>> Direction</label><br>
			<label><input type="checkbox" name="wpb_include_wind_speed"     <?php echo $checked('wpb_include_wind_speed');     ?>> Speed</label><br>
			<label><input type="checkbox" name="wpb_include_max_gust"       <?php echo $checked('wpb_include_max_gust');       ?>> Max Gust Today</label>
		</fieldset>

		<fieldset><legend><strong>Other</strong></legend>
			<label><input type="checkbox" name="wpb_include_humidity"       <?php echo $checked('wpb_include_humidity');       ?>> Humidity</label><br>
			<label><input type="checkbox" name="wpb_include_pressure"       <?php echo $checked('wpb_include_pressure');       ?>> Pressure</label><br>
			<label><input type="checkbox" name="wpb_include_rain_today"     <?php echo $checked('wpb_include_rain_today');     ?>> Rain Today</label><br>
			<label><input type="checkbox" name="wpb_include_weather_desc"   <?php echo $checked('wpb_include_weather_desc');   ?>> Weather Description</label>
		</fieldset>
	</td></tr>
</table>
