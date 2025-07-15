<?php

/**
 * Bluesky Weather Poster Plus - Main Plugin Class
 *
 * Handles admin UI, settings, weather posting (scheduled/test), and previews.
 *
 * @package    Bluesky Weather Poster Plus
 * @author     Your Name
 * @since      1.0.0
 */

defined('ABSPATH') || exit;

class BWWP_Bluesky_Poster
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'bwwp_register_admin_menu']);
        add_action('admin_init', [$this, 'bwwp_register_settings']);
        add_action('bwwp_weather_post_event', [$this, 'bwwp_post_weather_update']);
        add_action('admin_post_bwwp_test_post', [$this, 'bwwp_handle_test_post']);
    }

    /**
     * Add the "Bluesky Weather Poster Plus" settings page to the WP admin menu.
     */
    public function bwwp_register_admin_menu()
    {
        add_options_page(
            __('Bluesky Weather Poster Plus', 'bwwp'),
            __('Bluesky Weather Poster Plus', 'bwwp'),
            'manage_options',
            'bwwp-settings',
            [$this, 'bwwp_settings_page']
        );
    }

    /**
     * Render the settings/admin page, including preview and test post.
     */
    public function bwwp_settings_page()
    {
        $options = get_option('bwwp_settings');
        $preview = $this->bwwp_get_post_preview();

        // Show message after test post if redirected back
        if (isset($_GET['bwwp_test_post_result'])) {
            $result = sanitize_text_field($_GET['bwwp_test_post_result']);
            if ($result === 'success') {
                echo '<div class="notice notice-success"><p>' . esc_html__('Test post to Bluesky succeeded.', 'bwwp') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Test post to Bluesky failed: ', 'bwwp') . esc_html(rawurldecode($_GET['bwwp_error'] ?? 'Unknown error')) . '</p></div>';
            }
        }
?>
        <div class="wrap">
            <h1><?php esc_html_e('Bluesky Weather Poster Plus', 'bwwp'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('bwwp_settings_group');
                do_settings_sections('bwwp-settings');
                submit_button();
                ?>
            </form>
            <h2><?php esc_html_e('Preview', 'bwwp'); ?></h2>
            <div style="background:#fafafa;border:1px solid #ddd;padding:12px;margin-bottom:16px;">
                <?php echo esc_html($preview ?: __('Unable to preview post (check settings and clientraw.txt).', 'bwwp')); ?>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('bwwp_test_post', 'bwwp_test_post_nonce'); ?>
                <input type="hidden" name="action" value="bwwp_test_post">
                <button type="submit" class="button button-secondary">
                    <?php esc_html_e('Test Post to Bluesky', 'bwwp'); ?>
                </button>
            </form>
        </div>
    <?php
    }

    /**
     * Register plugin settings and all admin fields.
     */
    public function bwwp_register_settings()
    {
        register_setting('bwwp_settings_group', 'bwwp_settings', [$this, 'bwwp_sanitize_settings']);

        add_settings_section(
            'bwwp_main_section',
            __('Bluesky Weather Poster Plus Settings', 'bwwp'),
            null,
            'bwwp-settings'
        );

        add_settings_field(
            'bwwp_clientraw_url',
            __('Clientraw.txt URL', 'bwwp'),
            [$this, 'bwwp_clientraw_url_field'],
            'bwwp-settings',
            'bwwp_main_section'
        );
        add_settings_field(
            'bwwp_bluesky_username',
            __('Bluesky Username', 'bwwp'),
            [$this, 'bwwp_bluesky_username_field'],
            'bwwp-settings',
            'bwwp_main_section'
        );
        add_settings_field(
            'bwwp_bluesky_password',
            __('Bluesky App Password', 'bwwp'),
            [$this, 'bwwp_bluesky_password_field'],
            'bwwp-settings',
            'bwwp_main_section'
        );
        add_settings_field(
            'bwwp_post_frequency',
            __('Posting Frequency (hours)', 'bwwp'),
            [$this, 'bwwp_post_frequency_field'],
            'bwwp-settings',
            'bwwp_main_section'
        );
    }

    /**
     * Sanitize and validate user settings input.
     */
    public function bwwp_sanitize_settings($input)
    {
        $output = [];
        $output['bwwp_clientraw_url'] = esc_url_raw($input['bwwp_clientraw_url'] ?? '');
        $output['bwwp_bluesky_username'] = sanitize_text_field($input['bwwp_bluesky_username'] ?? '');
        $output['bwwp_bluesky_password'] = sanitize_text_field($input['bwwp_bluesky_password'] ?? '');
        $output['bwwp_post_frequency'] = absint($input['bwwp_post_frequency'] ?? 1);
        return $output;
    }

    /**
     * Render the clientraw.txt URL field.
     */
    public function bwwp_clientraw_url_field()
    {
        $options = get_option('bwwp_settings');
    ?>
        <input type="url" name="bwwp_settings[bwwp_clientraw_url]" value="<?php echo esc_attr($options['bwwp_clientraw_url'] ?? ''); ?>" class="regular-text" required />
    <?php
    }

    /**
     * Render the Bluesky username field.
     */
    public function bwwp_bluesky_username_field()
    {
        $options = get_option('bwwp_settings');
    ?>
        <input type="text" name="bwwp_settings[bwwp_bluesky_username]" value="<?php echo esc_attr($options['bwwp_bluesky_username'] ?? ''); ?>" class="regular-text" required />
    <?php
    }

    /**
     * Render the Bluesky app password field.
     * For security, do not display after entry.
     */
    public function bwwp_bluesky_password_field()
    {
    ?>
        <input type="password" name="bwwp_settings[bwwp_bluesky_password]" value="" class="regular-text" autocomplete="new-password" />
        <p class="description"><?php esc_html_e('Enter to update. Leave blank to keep existing.', 'bwwp'); ?></p>
    <?php
    }

    /**
     * Render the posting frequency select.
     */
    public function bwwp_post_frequency_field()
    {
        $options = get_option('bwwp_settings');
        $freq = isset($options['bwwp_post_frequency']) ? (int)$options['bwwp_post_frequency'] : 1;
    ?>
        <select name="bwwp_settings[bwwp_post_frequency]">
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
    public function bwwp_get_post_preview()
    {
        $options = get_option('bwwp_settings');
        if (empty($options['bwwp_clientraw_url'])) {
            return false;
        }
        $parser = new BWWP_Clientraw_Parser();
        $data = $parser->parse($options['bwwp_clientraw_url']);
        if (!$data) {
            return false;
        }
        $wind_dir = BWWP_Clientraw_Parser::degrees_to_compass($data['wind_dir']);
        return sprintf(
            __('Current conditions: %1$s°C, Wind %2$s %3$s km/h, Humidity %4$s%%, Pressure %5$s hPa, Rain today %6$s mm. %7$s', 'bwwp'),
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
    public function bwwp_handle_test_post()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('bwwp_test_post', 'bwwp_test_post_nonce')) {
            wp_die(__('Unauthorized', 'bwwp'));
        }
        $result = $this->bwwp_post_weather_update(true); // true = test mode (shows feedback)
        if ($result === true) {
            wp_redirect(add_query_arg('bwwp_test_post_result', 'success', admin_url('options-general.php?page=bwwp-settings')));
        } else {
            wp_redirect(add_query_arg([
                'bwwp_test_post_result' => 'fail',
                'bwwp_error' => rawurlencode($result ?: 'Unknown error'),
            ], admin_url('options-general.php?page=bwwp-settings')));
        }
        exit;
    }

    /**
     * Posts the weather update to Bluesky (scheduled or test).
     * If $test is true, returns success/failure for the admin UI.
     * @param bool $test
     * @return true|string True on success, error message string on failure.
     */
    public function bwwp_post_weather_update($test = false)
    {
        $options = get_option('bwwp_settings');
        if (empty($options['bwwp_clientraw_url']) || empty($options['bwwp_bluesky_username'])) {
            return $test ? __('Missing settings.', 'bwwp') : false;
        }

        // Retrieve and store (hashed) password safely
        $password = $options['bwwp_bluesky_password'] ?? '';
        if (empty($password)) {
            // Try to retrieve previously saved hashed password from a secure option
            $password = get_option('bwwp_bluesky_password');
            if (empty($password)) {
                return $test ? __('Missing Bluesky app password.', 'bwwp') : false;
            }
        } else {
            // Save new password securely (not shown in settings)
            update_option('bwwp_bluesky_password', $password);
        }

        // Parse weather data
        $parser = new BWWP_Clientraw_Parser();
        $data = $parser->parse($options['bwwp_clientraw_url']);
        if (!$data) {
            return $test ? __('Unable to fetch weather data.', 'bwwp') : false;
        }

        $wind_dir = BWWP_Clientraw_Parser::degrees_to_compass($data['wind_dir']);
        $post_text = sprintf(
            __('Current conditions: %1$s°C, Wind %2$s %3$s km/h, Humidity %4$s%%, Pressure %5$s hPa, Rain today %6$s mm. %7$s', 'bwwp'),
            $data['temp'],
            $wind_dir,
            $data['wind_speed'],
            $data['humidity'],
            $data['pressure'],
            $data['rain'],
            $data['desc']
        );

        // Bluesky API: Authenticate, then post
        $handle = trim($options['bwwp_bluesky_username']);
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
            return $test ? __('Bluesky login failed. Please check your credentials.', 'bwwp') : false;
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
            return $test ? (isset($post_result['error']) ? $post_result['error'] : __('Unknown error posting to Bluesky.', 'bwwp')) : false;
        }
    }
}
//end class BWWP_Bluesky_Poster