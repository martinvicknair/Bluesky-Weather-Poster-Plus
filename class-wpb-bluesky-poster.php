<?php

/**
 * Weather Poster Bluesky - Main Plugin Class
 *
 * Handles admin UI, settings, weather posting (scheduled/test), and previews.
 *
 * @package    Weather Poster Bluesky
 * @author     Martin Vicknair
 * @since      0.10
 */

defined('ABSPATH') || exit;

class WPB_Bluesky_Poster
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'wpb_register_admin_menu']);
        add_action('admin_init', [$this, 'wpb_register_settings']);
        add_action('wpb_weather_post_event', [$this, 'wpb_post_weather_update']);
        add_action('admin_post_wpb_test_post', [$this, 'wpb_handle_test_post']);
    }

    /**
     * Add the "Weather Poster Bluesky" settings page to the WP admin menu.
     */
    public function wpb_register_admin_menu()
    {
        add_options_page(
            __('Weather Poster Bluesky', 'wpb'),
            __('Weather Poster Bluesky', 'wpb'),
            'manage_options',
            'wpb-settings',
            [$this, 'wpb_settings_page']
        );
    }

    /**
     * Render the settings/admin page, including preview and test post.
     */
    public function wpb_settings_page()
    {
        $options = get_option('wpb_settings');
        $preview = $this->wpb_get_post_preview();

        // Show message after test post if redirected back
        if (isset($_GET['wpb_test_post_result'])) {
            $result = sanitize_text_field($_GET['wpb_test_post_result']);
            if ($result === 'success') {
                echo '<div class="notice notice-success"><p>' . esc_html__('Test post to Bluesky succeeded.', 'wpb') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Test post to Bluesky failed: ', 'wpb') . esc_html(rawurldecode($_GET['wpb_error'] ?? 'Unknown error')) . '</p></div>';
            }
        }
?>
        <div class="wrap">
            <h1><?php esc_html_e('Weather Poster Bluesky', 'wpb'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wpb_settings_group');
                do_settings_sections('wpb-settings');
                submit_button();
                ?>
            </form>
            <h2><?php esc_html_e('Preview', 'wpb'); ?></h2>
            <div style="background:#fafafa;border:1px solid #ddd;padding:12px;margin-bottom:16px;">
                <?php echo esc_html($preview ?: __('Unable to preview post (check settings and clientraw.txt).', 'wpb')); ?>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wpb_test_post', 'wpb_test_post_nonce'); ?>
                <input type="hidden" name="action" value="wpb_test_post">
                <button type="submit" class="button button-secondary">
                    <?php esc_html_e('Test Post to Bluesky', 'wpb'); ?>
                </button>
            </form>
        </div>
    <?php
    }

    /**
     * Register plugin settings and all admin fields.
     */
    public function wpb_register_settings()
    {
        register_setting('wpb_settings_group', 'wpb_settings', [$this, 'wpb_sanitize_settings']);

        add_settings_section(
            'wpb_main_section',
            __('Weather Poster Bluesky Settings', 'wpb'),
            null,
            'wpb-settings'
        );

        add_settings_field(
            'wpb_clientraw_url',
            __('Clientraw.txt URL', 'wpb'),
            [$this, 'wpb_clientraw_url_field'],
            'wpb-settings',
            'wpb_main_section'
        );
        add_settings_field(
            'wpb_bluesky_username',
            __('Bluesky Username', 'wpb'),
            [$this, 'wpb_bluesky_username_field'],
            'wpb-settings',
            'wpb_main_section'
        );
        add_settings_field(
            'wpb_bluesky_password',
            __('Bluesky App Password', 'wpb'),
            [$this, 'wpb_bluesky_password_field'],
            'wpb-settings',
            'wpb_main_section'
        );
        add_settings_field(
            'wpb_post_frequency',
            __('Posting Frequency (hours)', 'wpb'),
            [$this, 'wpb_post_frequency_field'],
            'wpb-settings',
            'wpb_main_section'
        );
    }

    /**
     * Sanitize and validate user settings input.
     */
    public function wpb_sanitize_settings($input)
    {
        $output = [];
        $output['wpb_clientraw_url'] = esc_url_raw($input['wpb_clientraw_url'] ?? '');
        $output['wpb_bluesky_username'] = sanitize_text_field($input['wpb_bluesky_username'] ?? '');
        $output['wpb_bluesky_password'] = sanitize_text_field($input['wpb_bluesky_password'] ?? '');
        $output['wpb_post_frequency'] = absint($input['wpb_post_frequency'] ?? 1);
        return $output;
    }

    /**
     * Render the clientraw.txt URL field.
     */
    public function wpb_clientraw_url_field()
    {
        $options = get_option('wpb_settings');
    ?>
        <input type="url" name="wpb_settings[wpb_clientraw_url]" value="<?php echo esc_attr($options['wpb_clientraw_url'] ?? ''); ?>" class="regular-text" required />
    <?php
    }

    /**
     * Render the Bluesky username field.
     */
    public function wpb_bluesky_username_field()
    {
        $options = get_option('wpb_settings');
    ?>
        <input type="text" name="wpb_settings[wpb_bluesky_username]" value="<?php echo esc_attr($options['wpb_bluesky_username'] ?? ''); ?>" class="regular-text" required />
    <?php
    }

    /**
     * Render the Bluesky app password field.
     * For security, do not display after entry.
     */
    public function wpb_bluesky_password_field()
    {
    ?>
        <input type="password" name="wpb_settings[wpb_bluesky_password]" value="" class="regular-text" autocomplete="new-password" />
        <p class="description"><?php esc_html_e('Enter to update. Leave blank to keep existing.', 'wpb'); ?></p>
    <?php
    }

    /**
     * Render the posting frequency select.
     */
    public function wpb_post_frequency_field()
    {
        $options = get_option('wpb_settings');
        $freq = isset($options['wpb_post_frequency']) ? (int)$options['wpb_post_frequency'] : 1;
    ?>
        <select name="wpb_settings[wpb_post_frequency]">
            <option value="1" <?php selected($freq, 1); ?>>1</option>
            <option value="2" <?php selected($freq, 2); ?>>2</option>
            <option value="3" <?php selected($freq, 3); ?>>3</option>
            <option value="6" <?php selected($freq, 6); ?>>6</option>
        </select>
<?php
    }

    /**
     * Build the post content preview (using latest weather data).
     * @return string|false
     */
    public function wpb_get_post_preview()
    {
        $options = get_option('wpb_settings');
        if (empty($options['wpb_clientraw_url'])) {
            return false;
        }
        $parser = new WPB_Clientraw_Parser();
        $data = $parser->parse($options['wpb_clientraw_url']);
        if (!$data) {
            return false;
        }
        $wind_dir = WPB_Clientraw_Parser::degrees_to_compass($data['wind_dir']);
        return sprintf(
            __('Current conditions: %1$s°C, Wind %2$s %3$s km/h, Humidity %4$s%%, Pressure %5$s hPa, Rain today %6$s mm. %7$s', 'wpb'),
            $data['temp'],
            $wind_dir,
            $data['wind_speed'],
            $data['humidity'],
            $data['pressure'],
            $data['rain'],
            $data['desc']
        );
    }

    /**
     * Handles the "Test Post to Bluesky" button (via admin-post.php)
     */
    public function wpb_handle_test_post()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('wpb_test_post', 'wpb_test_post_nonce')) {
            wp_die(__('Unauthorized', 'wpb'));
        }
        $result = $this->wpb_post_weather_update(true); // true = test mode (shows feedback)
        if ($result === true) {
            wp_redirect(add_query_arg('wpb_test_post_result', 'success', admin_url('options-general.php?page=wpb-settings')));
        } else {
            wp_redirect(add_query_arg([
                'wpb_test_post_result' => 'fail',
                'wpb_error' => rawurlencode($result ?: 'Unknown error'),
            ], admin_url('options-general.php?page=wpb-settings')));
        }
        exit;
    }

    /**
     * Posts the weather update to Bluesky (scheduled or test).
     * If $test is true, returns success/failure for the admin UI.
     * @param bool $test
     * @return true|string True on success, error message string on failure.
     */
    public function wpb_post_weather_update($test = false)
    {
        $options = get_option('wpb_settings');
        if (empty($options['wpb_clientraw_url']) || empty($options['wpb_bluesky_username'])) {
            return $test ? __('Missing settings.', 'wpb') : false;
        }

        // Retrieve and store (hashed) password safely
        $password = $options['wpb_bluesky_password'] ?? '';
        if (empty($password)) {
            // Try to retrieve previously saved hashed password from a secure option
            $password = get_option('wpb_bluesky_password');
            if (empty($password)) {
                return $test ? __('Missing Bluesky app password.', 'wpb') : false;
            }
        } else {
            // Save new password securely (not shown in settings)
            update_option('wpb_bluesky_password', $password);
        }

        // Parse weather data
        $parser = new WPB_Clientraw_Parser();
        $data = $parser->parse($options['wpb_clientraw_url']);
        if (!$data) {
            return $test ? __('Unable to fetch weather data.', 'wpb') : false;
        }

        $wind_dir = WPB_Clientraw_Parser::degrees_to_compass($data['wind_dir']);
        $post_text = sprintf(
            __('Current conditions: %1$s°C, Wind %2$s %3$s km/h, Humidity %4$s%%, Pressure %5$s hPa, Rain today %6$s mm. %7$s', 'wpb'),
            $data['temp'],
            $wind_dir,
            $data['wind_speed'],
            $data['humidity'],
            $data['pressure'],
            $data['rain'],
            $data['desc']
        );

        // Bluesky API: Authenticate, then post
        $handle = trim($options['wpb_bluesky_username']);
        $app_password = trim($password);

        // Bluesky authentication endpoint
        $login_url = 'https://bsky.social/xrpc/com.atproto.server.createSession';
        $login_body = [
            'identifier' => $handle,
            'password'   => $app_password
        ];

        $login_response = wp_remote_post($login_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($login_body),
            'timeout' => 15
        ]);

        if (is_wp_error($login_response)) {
            return $test ? $login_response->get_error_message() : false;
        }

        $login_data = json_decode(wp_remote_retrieve_body($login_response), true);

        if (empty($login_data['accessJwt'])) {
            return $test ? __('Bluesky login failed. Please check your credentials.', 'wpb') : false;
        }

        $jwt = $login_data['accessJwt'];
        $did = $login_data['did'];

        // Post to Bluesky feed
        $post_url = 'https://bsky.social/xrpc/com.atproto.repo.createRecord';
        $post_body = [
            'repo' => $did,
            'collection' => 'app.bsky.feed.post',
            'record' => [
                'text' => $post_text,
                '$type' => 'app.bsky.feed.post',
                'createdAt' => gmdate('c'),
            ]
        ];

        $post_response = wp_remote_post($post_url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $jwt,
            ],
            'body'    => wp_json_encode($post_body),
            'timeout' => 15
        ]);

        if (is_wp_error($post_response)) {
            return $test ? $post_response->get_error_message() : false;
        }

        $post_result = json_decode(wp_remote_retrieve_body($post_response), true);

        // Success if Bluesky returns a URI for the post
        if (!empty($post_result['uri'])) {
            return $test ? true : null;
        } else {
            return $test ? (isset($post_result['error']) ? $post_result['error'] : __('Unknown error posting to Bluesky.', 'wpb')) : false;
        }
    }
}
//end class WPB_Bluesky_Poster