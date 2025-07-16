<?php
/*
Plugin Name: Weather Poster Bluesky
Description: Automatically posts weather updates from clientraw.txt to Bluesky social network, including clickable station URL, hashtags, and optional embedded webcam image.
Version: 0.1.0
Author: Martin Vicknair - https://github.com/martinvicknair
This plugin is a fork of [Bluesky Weather Poster](https://github.com/TheLich2112/bluesky-weather-poster) by Marcus Hazel-McGown, originally licensed under the GPL v2 or later.
*/

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'class-wpb-bluesky-poster.php';
require_once plugin_dir_path(__FILE__) . 'class-wpb-clientraw-parser.php';

function wpb_register_settings()
{
    register_setting('wpb_settings_group', 'wpb_bluesky_username');
    register_setting('wpb_settings_group', 'wpb_bluesky_app_password');
    register_setting('wpb_settings_group', 'wpb_bluesky_enable_second');
    register_setting('wpb_settings_group', 'wpb_bluesky_username2');
    register_setting('wpb_settings_group', 'wpb_bluesky_app_password2');
    register_setting('wpb_settings_group', 'wpb_clientraw_url');
    register_setting('wpb_settings_group', 'wpb_station_url');
    register_setting('wpb_settings_group', 'wpb_station_display_text');
    register_setting('wpb_settings_group', 'wpb_webcam_image_url');
    register_setting('wpb_settings_group', 'wpb_webcam_display_text');
    register_setting('wpb_settings_group', 'wpb_frequency');
    register_setting('wpb_settings_group', 'wpb_units');
    register_setting('wpb_settings_group', 'wpb_post_prefix');
    register_setting('wpb_settings_group', 'wpb_first_post_hour');
    register_setting('wpb_settings_group', 'wpb_first_post_minute');
    register_setting('wpb_settings_group', 'wpb_hashtags');
    $fields = wpb_get_field_option_keys();
    foreach ($fields as $field_key) {
        register_setting('wpb_settings_group', $field_key);
    }
}
add_action('admin_init', 'wpb_register_settings');

function wpb_get_field_option_keys()
{
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

function wpb_get_next_scheduled_post_time()
{
    $next = wp_next_scheduled('wpb_cron_event');
    if (!$next) {
        return "Not scheduled.";
    }
    $tz = get_option('timezone_string');
    $dt = new DateTime("@$next");
    if ($tz) {
        $dt->setTimezone(new DateTimeZone($tz));
        return $dt->format("Y-m-d H:i:s (T)");
    } else {
        $offset = get_option('gmt_offset', 0) * 3600;
        return gmdate("Y-m-d H:i:s", $next + $offset) . " (site offset)";
    }
}

function wpb_add_settings_page()
{
    add_options_page(
        'Weather Poster Bluesky',
        'Weather Poster Bluesky',
        'manage_options',
        'bluesky-weather-poster-plus',
        'wpb_render_settings_page'
    );
}
add_action('admin_menu', 'wpb_add_settings_page');

function wpb_render_settings_page()
{
    $wpb_units = get_option('wpb_units', 'both');
    $wpb_post_prefix = get_option('wpb_post_prefix', 'Weather Update');
    $wpb_hashtags = get_option('wpb_hashtags', '');
    $wpb_first_post_hour = get_option('wpb_first_post_hour', '0');
    $wpb_first_post_minute = get_option('wpb_first_post_minute', '0');
    $wpb_station_display_text = get_option('wpb_station_display_text', 'Live Station');
    $wpb_webcam_image_url = get_option('wpb_webcam_image_url', '');
    $wpb_webcam_display_text = get_option('wpb_webcam_display_text', 'Webcam Snapshot');
    $wpb_preview = '';
    $wpb_response = '';
    $wpb_response_class = 'notice-info';
    $wpb_file_age_warning = '';

    if (isset($_POST['wpb_test_post'])) {
        $result = wpb_get_test_post_preview_and_response($wpb_preview, $wpb_response, $wpb_response_class, $wpb_file_age_warning, true);
    }

    $fields = wpb_get_field_option_keys();
    $options = [];
    foreach ($fields as $key) {
        $options[$key] = get_option($key, 'on');
    }
    $selected_frequency = esc_attr(get_option('wpb_frequency', '1'));
    $next_post_time = wpb_get_next_scheduled_post_time();
    $enable_second = get_option('wpb_bluesky_enable_second', '');
    $second_checked = ($enable_second === 'on');

?>
    <div class="wrap">
        <h1>Weather Poster Bluesky Settings</h1>
        <form method="post" action="options.php" id="wpb-settings-form">
            <?php settings_fields('wpb_settings_group'); ?>
            <?php do_settings_sections('wpb_settings_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="wpb_bluesky_username">Bluesky Username</label></th>
                    <td><input type="text" name="wpb_bluesky_username" value="<?php echo esc_attr(get_option('wpb_bluesky_username')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wpb_bluesky_app_password">Bluesky App Password</label></th>
                    <td><input type="password" name="wpb_bluesky_app_password" value="<?php echo esc_attr(get_option('wpb_bluesky_app_password')); ?>" class="regular-text" autocomplete="new-password" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Second Bluesky Account</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpb_bluesky_enable_second" id="wpb_bluesky_enable_second" <?php checked($second_checked, true); ?> />
                            Enable posting to a second Bluesky account
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wpb_bluesky_username2">Second Bluesky Username</label></th>
                    <td><input type="text" name="wpb_bluesky_username2" value="<?php echo esc_attr(get_option('wpb_bluesky_username2')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wpb_bluesky_app_password2">Second Bluesky App Password</label></th>
                    <td><input type="password" name="wpb_bluesky_app_password2" value="<?php echo esc_attr(get_option('wpb_bluesky_app_password2')); ?>" class="regular-text" autocomplete="new-password" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wpb_clientraw_url">clientraw.txt URL</label></th>
                    <td><input type="text" name="wpb_clientraw_url" value="<?php echo esc_attr(get_option('wpb_clientraw_url')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wpb_station_url">Station URL (must start with http:// or https://)</label></th>
                    <td><input type="text" name="wpb_station_url" value="<?php echo esc_attr(get_option('wpb_station_url')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wpb_station_display_text">Station Link Display Text</label></th>
                    <td><input type="text" name="wpb_station_display_text" value="<?php echo esc_attr($wpb_station_display_text); ?>" class="regular-text" />
                        <p class="description">Text shown for your station link (e.g. "Live Station").</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wpb_webcam_image_url">Latest Webcam Image URL</label></th>
                    <td>
                        <input type="text" name="wpb_webcam_image_url" value="<?php echo esc_attr($wpb_webcam_image_url); ?>" class="regular-text" />
                        <p class="description">Enter a direct image URL (e.g., https://example.com/latest.jpg).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wpb_webcam_display_text">Webcam Display Text</label></th>
                    <td>
                        <input type="text" name="wpb_webcam_display_text" value="<?php echo esc_attr($wpb_webcam_display_text); ?>" class="regular-text" />
                        <p class="description">Alt text for the image. Optional but recommended for accessibility.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wpb_frequency">Posting Frequency</label></th>
                    <td>
                        <select name="wpb_frequency" id="wpb_frequency">
                            <option value="1" <?php selected($selected_frequency, '1'); ?>>Every 1 hour</option>
                            <option value="2" <?php selected($selected_frequency, '2'); ?>>Every 2 hours</option>
                            <option value="3" <?php selected($selected_frequency, '3'); ?>>Every 3 hours</option>
                            <option value="6" <?php selected($selected_frequency, '6'); ?>>Every 6 hours</option>
                            <option value="12" <?php selected($selected_frequency, '12'); ?>>Every 12 hours</option>
                            <option value="24" <?php selected($selected_frequency, '24'); ?>>Every 24 hours</option>
                        </select>
                        <br>
                        <label>
                            Post at (hour:minute):
                            <select name="wpb_first_post_hour" id="wpb_first_post_hour">
                                <?php for ($h = 0; $h < 24; $h++) {
                                    printf('<option value="%d"%s>%02d</option>', $h, selected($wpb_first_post_hour, $h, false), $h);
                                } ?>
                            </select>
                            :
                            <select name="wpb_first_post_minute" id="wpb_first_post_minute">
                                <?php for ($m = 0; $m < 60; $m++) {
                                    printf('<option value="%d"%s>%02d</option>', $m, selected($wpb_first_post_minute, $m, false), $m);
                                } ?>
                            </select>
                        </label>
                        <p class="description">Choose posting frequency and the hour:minute for each post (24-hour clock).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wpb_units">Units</label></th>
                    <td>
                        <select name="wpb_units" id="wpb_units">
                            <option value="metric" <?php selected($wpb_units, 'metric'); ?>>Metric (°C, km/h, mm, hPa)</option>
                            <option value="imperial" <?php selected($wpb_units, 'imperial'); ?>>Imperial (°F, mph, in, inHg)</option>
                            <option value="both" <?php selected($wpb_units, 'both'); ?>>Both</option>
                        </select>
                        <p class="description">Select which units to use in the post output. Default is "Both".</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wpb_post_prefix">Post Prefix Text</label></th>
                    <td>
                        <input type="text" name="wpb_post_prefix" id="wpb_post_prefix" value="<?php echo esc_attr($wpb_post_prefix); ?>" class="regular-text" />
                        <p class="description">This text will appear at the start of every post (e.g. "Current conditions:").</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wpb_hashtags">Hashtags</label></th>
                    <td>
                        <input type="text" name="wpb_hashtags" id="wpb_hashtags" value="<?php echo esc_attr($wpb_hashtags); ?>" class="regular-text" />
                        <p class="description">Comma-separated (e.g. <code>weather,climate,MyTown</code>). Will be appended as hashtags to each post (in order).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Post Content Fields</th>
                    <td>
                        <fieldset>
                            <legend><strong>Temperature &amp; Feel</strong></legend>
                            <label><input type="checkbox" name="wpb_include_temperature" <?php checked($options['wpb_include_temperature'], 'on'); ?> /> Temperature</label><br>
                            <label><input type="checkbox" name="wpb_include_windchill" <?php checked($options['wpb_include_windchill'], 'on'); ?> /> Windchill</label><br>
                            <label><input type="checkbox" name="wpb_include_humidex" <?php checked($options['wpb_include_humidex'], 'on'); ?> /> Humidex</label><br>
                            <label><input type="checkbox" name="wpb_include_dew_point" <?php checked($options['wpb_include_dew_point'], 'on'); ?> /> Dew Point</label><br>
                            <label><input type="checkbox" name="wpb_include_max_temp" <?php checked($options['wpb_include_max_temp'], 'on'); ?> /> Max Temperature Today</label><br>
                            <label><input type="checkbox" name="wpb_include_min_temp" <?php checked($options['wpb_include_min_temp'], 'on'); ?> /> Min Temperature Today</label>
                        </fieldset>
                        <fieldset>
                            <legend><strong>Wind</strong></legend>
                            <label><input type="checkbox" name="wpb_include_wind_direction" <?php checked($options['wpb_include_wind_direction'], 'on'); ?> /> Wind Direction</label><br>
                            <label><input type="checkbox" name="wpb_include_wind_speed" <?php checked($options['wpb_include_wind_speed'], 'on'); ?> /> Wind Speed</label><br>
                            <label><input type="checkbox" name="wpb_include_max_gust" <?php checked($options['wpb_include_max_gust'], 'on'); ?> /> Max Gust Today</label>
                        </fieldset>
                        <fieldset>
                            <legend><strong>Other</strong></legend>
                            <label><input type="checkbox" name="wpb_include_humidity" <?php checked($options['wpb_include_humidity'], 'on'); ?> /> Humidity</label><br>
                            <label><input type="checkbox" name="wpb_include_pressure" <?php checked($options['wpb_include_pressure'], 'on'); ?> /> Pressure</label><br>
                            <label><input type="checkbox" name="wpb_include_rain_today" <?php checked($options['wpb_include_rain_today'], 'on'); ?> /> Rain Today</label><br>
                            <label><input type="checkbox" name="wpb_include_weather_desc" <?php checked($options['wpb_include_weather_desc'], 'on'); ?> /> Weather Description</label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <hr>
        <h2>Test Post</h2>
        <form method="post">
            <?php submit_button('Send Test Post', 'secondary', 'wpb_test_post'); ?>
        </form>
        <?php if ($wpb_file_age_warning): ?>
            <div class="notice notice-warning">
                <strong>File Age Warning:</strong>
                <pre style="white-space: pre-wrap; word-break: break-word;"><?php echo esc_html($wpb_file_age_warning); ?></pre>
            </div>
        <?php endif; ?>
        <div class="notice notice-info" style="margin-top: 1.5em;">
            <strong>Post Preview:</strong>
            <pre id="wpb-live-preview" style="white-space: pre-wrap; word-break: break-word;"><?php echo esc_html($wpb_preview); ?></pre>
        </div>
        <div id="wpb-char-count-display" class="notice notice-info" style="margin-top:1.5em;"></div>
        <div class="notice <?php echo esc_attr($wpb_response_class); ?>">
            <strong>API Response:</strong>
            <pre style="white-space: pre-wrap; word-break: break-word;"><?php echo (is_array($wpb_response) ? print_r($wpb_response, true) : ($wpb_response !== '' ? esc_html($wpb_response) : '(no response)')); ?></pre>
        </div>
        <div class="notice notice-info" style="margin-top:1.5em;">
            <strong>Next Scheduled Post:</strong>
            <pre><?php echo esc_html($next_post_time); ?></pre>
        </div>
    </div>
    <script>
        jQuery(document).ready(function($) {
            function updateCharCount() {
                var data = {
                    action: 'wpb_estimate_char_count',
                    post_prefix: $('#wpb_post_prefix').val(),
                    units: $('#wpb_units').val(),
                    hashtags: $('#wpb_hashtags').val(),
                    station_display_text: $('#wpb_station_display_text').val(),
                    webcam_image_url: $('#wpb_webcam_image_url').val(),
                    webcam_display_text: $('#wpb_webcam_display_text').val()
                };
                $('input[type=checkbox][name^="wpb_include_"]').each(function() {
                    data[$(this).attr('name')] = $(this).is(':checked') ? 'on' : '';
                });
                $.post(ajaxurl, data, function(resp) {
                    if (resp.success) {
                        var count = resp.data.count;
                        var limit = 300;
                        var color = (count > limit) ? '#b00' : '#222';
                        $('#wpb-char-count-display').html(
                            '<strong>Estimated post character count:</strong> ' +
                            count + ' / ' + limit +
                            (count > limit ?
                                '<br><span style="color:#b00;">Warning: This may exceed Bluesky’s 300 character limit and may be truncated or rejected.</span>' :
                                ''
                            )
                        ).css('color', color);
                    }
                });
            }

            function updateLivePreview() {
                var data = {
                    action: 'wpb_live_post_preview',
                    post_prefix: $('#wpb_post_prefix').val(),
                    units: $('#wpb_units').val(),
                    hashtags: $('#wpb_hashtags').val(),
                    station_display_text: $('#wpb_station_display_text').val(),
                    webcam_image_url: $('#wpb_webcam_image_url').val(),
                    webcam_display_text: $('#wpb_webcam_display_text').val()
                };
                $('input[type=checkbox][name^="wpb_include_"]').each(function() {
                    data[$(this).attr('name')] = $(this).is(':checked') ? 'on' : '';
                });
                $.post(ajaxurl, data, function(resp) {
                    if (resp.success) {
                        $('#wpb-live-preview').text(resp.data.preview);
                    } else {
                        $('#wpb-live-preview').text('No clientraw file available to parse');
                    }
                }).fail(function() {
                    $('#wpb-live-preview').text('No clientraw file available to parse');
                });
            }
            $('#wpb_post_prefix, #wpb_units, #wpb_hashtags, #wpb_station_display_text, #wpb_webcam_image_url, #wpb_webcam_display_text').on('input change', function() {
                updateCharCount();
                updateLivePreview();
            });
            $('input[type=checkbox][name^="wpb_include_"]').on('change', function() {
                updateCharCount();
                updateLivePreview();
            });
            updateCharCount();
            updateLivePreview();
        });
    </script>
<?php
}

// ---------- HELPERS, POST FORMATTING, CRON, AJAX, POSTING, ETC ----------

function wpb_format_weather_output_with_facets($data, $station_url = '')
{
    $units = get_option('wpb_units', 'both');
    $prefix = get_option('wpb_post_prefix', 'Weather Update');
    $hashtags = get_option('wpb_hashtags', '');
    $station_display = trim(get_option('wpb_station_display_text', 'Live Station'));
    if (isset($_POST['station_display_text'])) {
        $station_display = trim(sanitize_text_field($_POST['station_display_text']));
    }
    $fields = wpb_get_field_option_keys();
    $include = [];
    foreach ($fields as $key) {
        $include[$key] = get_option($key, 'on') === 'on';
    }
    $lines = [];
    $lines[] = "🌤️ " . trim($prefix);
    $lines[] = "";

    if ($include['wpb_include_temperature']) {
        $temp_c = isset($data['temperature']) ? $data['temperature'] : null;
        $temp_f = $temp_c !== null ? wpb_c_to_f($temp_c) : null;
        $str = "🌡️ Temp: ";
        if ($units === 'metric') {
            $str .= ($temp_c !== null ? "{$temp_c}°C" : 'N/A');
        } elseif ($units === 'imperial') {
            $str .= ($temp_f !== null ? "{$temp_f}°F" : 'N/A');
        } else {
            $str .= ($temp_c !== null && $temp_f !== null) ? "{$temp_c}°C ({$temp_f}°F)" : 'N/A';
        }
        $lines[] = $str;
    }
    if ($include['wpb_include_wind_direction'] || $include['wpb_include_wind_speed']) {
        $wind_str = "💨 Wind:";
        if ($include['wpb_include_wind_direction']) {
            $wind_dir = isset($data['wind_direction']) ? $data['wind_direction'] : null;
            $wind_dir_compass = $wind_dir !== null ? wpb_degrees_to_compass($wind_dir) : null;
            $wind_str .= ($wind_dir_compass !== null ? " $wind_dir_compass" : "");
        }
        if ($include['wpb_include_wind_speed']) {
            $wind_knots = isset($data['wind_speed']) ? $data['wind_speed'] : null;
            $wind_kmh = $wind_knots !== null ? wpb_knots_to_kmh($wind_knots) : null;
            $wind_mph = $wind_knots !== null ? wpb_knots_to_mph($wind_knots) : null;
            if ($units === 'metric') {
                $wind_str .= ($wind_kmh !== null ? " {$wind_kmh} km/h" : "");
            } elseif ($units === 'imperial') {
                $wind_str .= ($wind_mph !== null ? " {$wind_mph} mph" : "");
            } else {
                $wind_str .= ($wind_kmh !== null && $wind_mph !== null) ? " {$wind_kmh} km/h ({$wind_mph} mph)" : "";
            }
        }
        $lines[] = trim($wind_str);
    }
    if ($include['wpb_include_humidity']) {
        $humidity = isset($data['humidity']) ? $data['humidity'] : null;
        $lines[] = "💧 Humidity: " . ($humidity !== null ? "{$humidity}%" : 'N/A');
    }
    if ($include['wpb_include_pressure']) {
        $pressure_hpa = isset($data['pressure']) ? $data['pressure'] : null;
        $pressure_inhg = $pressure_hpa !== null ? wpb_hpa_to_inhg($pressure_hpa) : null;
        $str = "🌬️ Pressure: ";
        if ($units === 'metric') {
            $str .= ($pressure_hpa !== null ? "{$pressure_hpa} hPa" : 'N/A');
        } elseif ($units === 'imperial') {
            $str .= ($pressure_inhg !== null ? "{$pressure_inhg} inHg" : 'N/A');
        } else {
            $str .= ($pressure_hpa !== null && $pressure_inhg !== null) ? "{$pressure_hpa} hPa ({$pressure_inhg} inHg)" : 'N/A';
        }
        $lines[] = $str;
    }
    if ($include['wpb_include_rain_today']) {
        $rain_mm = isset($data['rain_today']) ? $data['rain_today'] : null;
        $rain_in = $rain_mm !== null ? wpb_mm_to_in($rain_mm) : null;
        $str = "☔ Rain Today: ";
        if ($units === 'metric') {
            $str .= ($rain_mm !== null ? "{$rain_mm} mm" : 'N/A');
        } elseif ($units === 'imperial') {
            $str .= ($rain_in !== null ? "{$rain_in} in" : 'N/A');
        } else {
            $str .= ($rain_mm !== null && $rain_in !== null) ? "{$rain_mm} mm ({$rain_in} in)" : 'N/A';
        }
        $lines[] = $str;
    }
    if ($include['wpb_include_windchill']) {
        $windchill_c = isset($data['windchill']) ? $data['windchill'] : null;
        $windchill_f = $windchill_c !== null ? wpb_c_to_f($windchill_c) : null;
        $str = "🥶 Windchill: ";
        if ($units === 'metric') {
            $str .= ($windchill_c !== null ? "{$windchill_c}°C" : 'N/A');
        } elseif ($units === 'imperial') {
            $str .= ($windchill_f !== null ? "{$windchill_f}°F" : 'N/A');
        } else {
            $str .= ($windchill_c !== null && $windchill_f !== null) ? "{$windchill_c}°C ({$windchill_f}°F)" : 'N/A';
        }
        $lines[] = $str;
    }
    if ($include['wpb_include_humidex']) {
        $humidex_c = isset($data['humidex']) ? $data['humidex'] : null;
        $humidex_f = $humidex_c !== null ? wpb_c_to_f($humidex_c) : null;
        $str = "🥵 Humidex: ";
        if ($units === 'metric') {
            $str .= ($humidex_c !== null ? "{$humidex_c}°C" : 'N/A');
        } elseif ($units === 'imperial') {
            $str .= ($humidex_f !== null ? "{$humidex_f}°F" : 'N/A');
        } else {
            $str .= ($humidex_c !== null && $humidex_f !== null) ? "{$humidex_c}°C ({$humidex_f}°F)" : 'N/A';
        }
        $lines[] = $str;
    }
    if ($include['wpb_include_dew_point']) {
        $dew_c = isset($data['dew_point']) ? $data['dew_point'] : null;
        $dew_f = $dew_c !== null ? wpb_c_to_f($dew_c) : null;
        $str = "🟦 Dew Point: ";
        if ($units === 'metric') {
            $str .= ($dew_c !== null ? "{$dew_c}°C" : 'N/A');
        } elseif ($units === 'imperial') {
            $str .= ($dew_f !== null ? "{$dew_f}°F" : 'N/A');
        } else {
            $str .= ($dew_c !== null && $dew_f !== null) ? "{$dew_c}°C ({$dew_f}°F)" : 'N/A';
        }
        $lines[] = $str;
    }
    if ($include['wpb_include_max_temp']) {
        $max_c = isset($data['max_temp']) ? $data['max_temp'] : null;
        $max_f = $max_c !== null ? wpb_c_to_f($max_c) : null;
        $str = "🔺 Max Temp: ";
        if ($units === 'metric') {
            $str .= ($max_c !== null ? "{$max_c}°C" : 'N/A');
        } elseif ($units === 'imperial') {
            $str .= ($max_f !== null ? "{$max_f}°F" : 'N/A');
        } else {
            $str .= ($max_c !== null && $max_f !== null) ? "{$max_c}°C ({$max_f}°F)" : 'N/A';
        }
        $lines[] = $str;
    }
    if ($include['wpb_include_min_temp']) {
        $min_c = isset($data['min_temp']) ? $data['min_temp'] : null;
        $min_f = $min_c !== null ? wpb_c_to_f($min_c) : null;
        $str = "🔻 Min Temp: ";
        if ($units === 'metric') {
            $str .= ($min_c !== null ? "{$min_c}°C" : 'N/A');
        } elseif ($units === 'imperial') {
            $str .= ($min_f !== null ? "{$min_f}°F" : 'N/A');
        } else {
            $str .= ($min_c !== null && $min_f !== null) ? "{$min_c}°C ({$min_f}°F)" : 'N/A';
        }
        $lines[] = $str;
    }
    if ($include['wpb_include_max_gust']) {
        $max_gust_knots = isset($data['max_gust']) ? $data['max_gust'] : null;
        $max_gust_kmh = $max_gust_knots !== null ? wpb_knots_to_kmh($max_gust_knots) : null;
        $max_gust_mph = $max_gust_knots !== null ? wpb_knots_to_mph($max_gust_knots) : null;
        $str = "🌪️ Max Gust: ";
        if ($units === 'metric') {
            $str .= ($max_gust_kmh !== null ? "{$max_gust_kmh} km/h" : 'N/A');
        } elseif ($units === 'imperial') {
            $str .= ($max_gust_mph !== null ? "{$max_gust_mph} mph" : 'N/A');
        } else {
            $str .= ($max_gust_kmh !== null && $max_gust_mph !== null) ? "{$max_gust_kmh} km/h ({$max_gust_mph} mph)" : 'N/A';
        }
        $lines[] = $str;
    }
    if ($include['wpb_include_weather_desc']) {
        $desc = isset($data['weather_desc']) ? $data['weather_desc'] : null;
        if ($desc !== null && $desc !== '') {
            $lines[] = "📝 " . $desc;
        }
    }

    $post = implode("\n", $lines);
    $facets = [];

    // Only add the link if the URL is valid
    if ($station_url && filter_var($station_url, FILTER_VALIDATE_URL)) {
        $link_text = $station_display ?: $station_url;
        $post .= "\n\n🔗 $link_text";
        $link_start = mb_strpos($post, $link_text, 0, '8bit');
        if ($link_start !== false) {
            $facets[] = [
                'index' => [
                    'byteStart' => $link_start,
                    'byteEnd' => $link_start + strlen($link_text),
                ],
                'features' => [[
                    '$type' => 'app.bsky.richtext.facet#link',
                    'uri' => $station_url
                ]]
            ];
        }
    }

    // Add hashtags (if present) as clickable tags at the end
    $hashtags = trim($hashtags);
    $hashtag_line = '';
    if ($hashtags) {
        $tags = array_filter(array_map('trim', explode(',', $hashtags)));
        foreach ($tags as $tag) {
            $tag_clean = ltrim($tag, '#');
            if ($tag_clean !== '') {
                $hashtag_line .= '#' . preg_replace('/[^A-Za-z0-9_]/', '', $tag_clean) . ' ';
            }
        }
        $hashtag_line = trim($hashtag_line);
        if ($hashtag_line) {
            $hashtag_offset = mb_strlen($post, '8bit');
            $post .= "\n\n" . $hashtag_line;
            $offset = $hashtag_offset + 2;
            foreach (explode(' ', $hashtag_line) as $hash) {
                if (strpos($hash, '#') === 0 && strlen($hash) > 1) {
                    $start = mb_strpos($post, $hash, $offset, '8bit');
                    if ($start !== false) {
                        $end = $start + strlen($hash);
                        $facets[] = [
                            'index' => [
                                'byteStart' => $start,
                                'byteEnd' => $end,
                            ],
                            'features' => [[
                                '$type' => 'app.bsky.richtext.facet#tag',
                                'tag' => ltrim($hash, '#')
                            ]]
                        ];
                        $offset = $end;
                    }
                }
            }
        }
    }

    // Webcam image (if present and valid image URL)
    $webcam_url = trim(get_option('wpb_webcam_image_url', ''));
    $webcam_display = trim(get_option('wpb_webcam_display_text', 'Webcam Snapshot'));
    if (isset($_POST['webcam_image_url'])) {
        $webcam_url = trim(sanitize_text_field($_POST['webcam_image_url']));
    }
    if (isset($_POST['webcam_display_text'])) {
        $webcam_display = trim(sanitize_text_field($_POST['webcam_display_text']));
    }
    $embed = null;
    if ($webcam_url && filter_var($webcam_url, FILTER_VALIDATE_URL)) {
        // Do NOT append any text about the image to the post body!
        $embed = [
            'image_url' => $webcam_url,
            'alt' => $webcam_display ?: 'Webcam Image'
        ];
    }

    return ['text' => $post, 'facets' => $facets, 'embed' => $embed];
}

// ---------- Unit Conversion Helpers ----------
function wpb_c_to_f($c)
{
    return round(($c * 9 / 5) + 32, 1);
}
function wpb_kmh_to_mph($kmh)
{
    return round($kmh * 0.621371, 1);
}
function wpb_knots_to_kmh($knots)
{
    return round($knots * 1.852, 1);
}
function wpb_knots_to_mph($knots)
{
    return round($knots * 1.15078, 1);
}
function wpb_mm_to_in($mm)
{
    return round($mm * 0.0393701, 2);
}
function wpb_hpa_to_inhg($hpa)
{
    return round($hpa * 0.0295299830714, 2);
}
function wpb_degrees_to_compass($degrees)
{
    $dirs = [
        'N',
        'NNE',
        'NE',
        'ENE',
        'E',
        'ESE',
        'SE',
        'SSE',
        'S',
        'SSW',
        'SW',
        'WSW',
        'W',
        'WNW',
        'NW',
        'NNW'
    ];
    $degrees = floatval($degrees);
    return $dirs[round($degrees / 22.5) % 16];
}
function wpb_get_remote_file_last_modified($url)
{
    $headers = @get_headers($url, 1);
    if (!$headers || !isset($headers['Last-Modified'])) {
        return false;
    }
    $lm = is_array($headers['Last-Modified']) ? end($headers['Last-Modified']) : $headers['Last-Modified'];
    $timestamp = strtotime($lm);
    return $timestamp !== false ? $timestamp : false;
}

// ---------- Preview/Test Post Handler ----------
function wpb_get_test_post_preview_and_response(&$preview, &$response, &$response_class, &$file_age_warning, $force_post = false)
{
    $preview = '';
    $response = '';
    $response_class = 'notice-info';
    $file_age_warning = '';

    $clientraw_url = get_option('wpb_clientraw_url');
    $station_url = get_option('wpb_station_url', '');
    $frequency = intval(get_option('wpb_frequency', 1));
    $max_age = $frequency * 3600;

    $last_modified = wpb_get_remote_file_last_modified($clientraw_url);
    $now = time();
    if (!$force_post && $last_modified !== false) {
        $age = $now - $last_modified;
        if ($age > $max_age) {
            $preview = "No new updates (clientraw.txt is older than your post interval: $frequency hour(s)).";
            $file_age_warning = "Warning: clientraw.txt is older than your post interval (" . gmdate("H:i:s", $age) . " old, max allowed " . gmdate("H:i:s", $max_age) . ").\nNo post will be sent to Bluesky.";
            $response = '';
            $response_class = 'notice-warning';
            return false;
        }
    } elseif (!$force_post && $last_modified === false) {
        $file_age_warning = "Could not determine clientraw.txt file age (Last-Modified HTTP header not found).";
    }

    try {
        $parser = new WPB_Clientraw_Parser();
        $data = $parser->parse($clientraw_url);
        $post_struct = wpb_format_weather_output_with_facets($data, $station_url);
        $preview = $post_struct['text'];

        $results = wpb_post_to_bluesky_accounts($post_struct);

        if (is_array($results)) {
            $response = "";
            foreach ($results as $acc => $resp) {
                $response .= "$acc: ";
                if (is_array($resp)) {
                    $response .= print_r($resp, true);
                } else {
                    $response .= $resp;
                }
                $response .= "\n";
            }
            $response_class = 'notice-success';
        } elseif ($results === null || $results === true) {
            $response = 'Success (no additional details from API)';
            $response_class = 'notice-success';
        } else {
            $response = $results;
            $response_class = 'notice-success';
        }
        return true;
    } catch (Exception $e) {
        $response = $e->getMessage();
        $response_class = 'notice-error';
        return false;
    }
}

// ---------- Actually Post the Update (for cron and test post), using clickable facets ----------
function wpb_post_weather_update()
{
    $clientraw_url = get_option('wpb_clientraw_url');
    $station_url = get_option('wpb_station_url', '');
    $frequency = intval(get_option('wpb_frequency', 1));
    $max_age = $frequency * 3600;

    if (!$clientraw_url) return false;
    $last_modified = wpb_get_remote_file_last_modified($clientraw_url);
    $now = time();
    if ($last_modified !== false) {
        $age = $now - $last_modified;
        if ($age > $max_age) {
            $status = "No new updates (clientraw.txt is older than your post interval: $frequency hour(s)).";
            return wpb_post_to_bluesky_accounts(['text' => $status, 'facets' => []]);
        }
    }
    $parser = new WPB_Clientraw_Parser();
    $data = $parser->parse($clientraw_url);
    if (!$data || empty($data)) {
        return false;
    }
    $post_struct = wpb_format_weather_output_with_facets($data, $station_url);
    return wpb_post_to_bluesky_accounts($post_struct);
}

// ---------- Post to both Bluesky accounts if enabled ----------
function wpb_post_to_bluesky_accounts($post_struct)
{
    $username1 = get_option('wpb_bluesky_username');
    $app_password1 = get_option('wpb_bluesky_app_password');
    $enable_second = get_option('wpb_bluesky_enable_second', '');
    $username2 = get_option('wpb_bluesky_username2');
    $app_password2 = get_option('wpb_bluesky_app_password2');
    $results = [];

    if ($username1 && $app_password1) {
        try {
            $poster1 = new Bluesky_Poster($username1, $app_password1);
            $results['Account 1'] = $poster1->post_status(
                $post_struct['text'],
                $post_struct['facets'],
                $post_struct['embed'] ?? null
            );
        } catch (Exception $e) {
            $results['Account 1'] = $e->getMessage();
        }
    }
    if ($enable_second === 'on' && $username2 && $app_password2) {
        try {
            $poster2 = new Bluesky_Poster($username2, $app_password2);
            $results['Account 2'] = $poster2->post_status(
                $post_struct['text'],
                $post_struct['facets'],
                $post_struct['embed'] ?? null
            );
        } catch (Exception $e) {
            $results['Account 2'] = $e->getMessage();
        }
    }
    return $results;
}

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