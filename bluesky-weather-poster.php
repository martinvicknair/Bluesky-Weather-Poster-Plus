<?php
/*
Plugin Name: Bluesky Weather Poster
Description: Posts weather updates from clientraw.txt to Bluesky social network
Version: 1.0
Author: Marcus Hazel-McGown - MM0ZIF
Homepage: https://mm0zif.radio - Contact: marcus@mm0zif.radio 
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include required files
require_once __DIR__ . '/class-bluesky-poster.php';
require_once __DIR__ . '/class-clientraw-parser.php';

class BlueskyWeatherPosterPlugin {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('bluesky_weather_post_event', [$this, 'post_weather_update']);
        add_action('wp_ajax_test_bluesky_post', [$this, 'post_weather_update']);
    }

    public function add_admin_menu() {
        add_options_page(
            'Bluesky Weather Poster Settings',
            'Bluesky Poster',
            'manage_options',
            'bluesky-weather-poster',
            [$this, 'settings_page']
        );
    }

    public function register_settings() {
        register_setting('bluesky_weather_poster_options', 'bluesky_username');
        register_setting('bluesky_weather_poster_options', 'bluesky_password');
        register_setting('bluesky_weather_poster_options', 'clientraw_url');
        register_setting('bluesky_weather_poster_options', 'weather_live_url');
        register_setting('bluesky_weather_poster_options', 'post_frequency');

        add_settings_section(
            'bluesky_weather_poster_main',
            '',
            null,
            'bluesky-weather-poster'
        );
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h2>Bluesky Weather Poster Settings</h2>
            <div class="notice notice-info">
                <p>Configure your Bluesky credentials and weather data sources below. Use the Test Post button to verify your settings.</p>
            </div>
            <form method="post" action="options.php">
                <?php 
                settings_fields('bluesky_weather_poster_options');
                do_settings_sections('bluesky-weather-poster');
                ?>
                <table class="form-table">
                    <tr>
                        <th>Bluesky Username</th>
                        <td>
                            <input type="text" name="bluesky_username" class="regular-text" 
                                   value="<?php echo esc_attr(get_option('bluesky_username')); ?>" />
                            <p class="description">Your Bluesky account username</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Bluesky Password</th>
                        <td>
                            <input type="password" name="bluesky_password" class="regular-text" 
                                   value="<?php echo esc_attr(get_option('bluesky_password')); ?>" />
                            <p class="description">Your Bluesky account password</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Clientraw.txt URL</th>
                        <td>
                            <input type="url" name="clientraw_url" class="regular-text" 
                                   value="<?php echo esc_attr(get_option('clientraw_url')); ?>" />
                            <p class="description">Full URL to your clientraw.txt file</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Live Weather URL</th>
                        <td>
                            <input type="url" name="weather_live_url" class="regular-text" 
                                   value="<?php echo esc_attr(get_option('weather_live_url')); ?>" />
                            <p class="description">URL to your live weather station page</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Post Frequency</th>
                        <td>
                            <select name="post_frequency">
                                <option value="1" <?php selected(get_option('post_frequency'), '1'); ?>>Every hour</option>
                                <option value="2" <?php selected(get_option('post_frequency'), '2'); ?>>Every 2 hours</option>
                                <option value="3" <?php selected(get_option('post_frequency'), '3'); ?>>Every 3 hours</option>
                                <option value="6" <?php selected(get_option('post_frequency'), '6'); ?>>Every 6 hours</option>
                            </select>
                            <p class="description">How often to post weather updates</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h3>Test Your Configuration</h3>
                <p>Click the button below to test your settings and see the output.</p>
                <button class="button button-primary" onclick="testPost()">Test Post</button>
                <div id="test-result" style="margin-top: 15px;"></div>
            </div>
        </div>

        <style>
        .debug-output {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            margin-top: 10px;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
        }
        </style>

        <script>
        function testPost() {
            jQuery('#test-result').html('<div class="notice notice-info"><p><span class="dashicons dashicons-update spinning"></span> Testing post...</p></div>');
            jQuery.post(ajaxurl, {
                action: 'test_bluesky_post'
            }).done(function(response) {
                let output = '<div class="notice notice-success"><p><strong>Test post successful!</strong></p></div>';
                output += '<div class="debug-output">';
                output += '<strong>Weather Data:</strong>\n' + response.data.weather + '\n\n';
                output += '<strong>API Response:</strong>\n' + JSON.stringify(response.data.api_response, null, 2);
                output += '</div>';
                jQuery('#test-result').html(output);
            }).fail(function(xhr, status, error) {
                jQuery('#test-result').html(
                    '<div class="notice notice-error">' +
                    '<p><strong>Error:</strong> ' + error + '</p>' +
                    '<p>Check your settings and try again.</p>' +
                    '</div>'
                );
            });
        }
        </script>
        <?php
    }

    public function post_weather_update() {
        try {
            $clientraw = new ClientrawParser(get_option('clientraw_url'));
            $weatherUpdate = $clientraw->formatWeatherUpdate();
            
            // Add #weather hashtag
            $weatherUpdate .= " #weather";
            
            $poster = new BlueskyPoster(
                get_option('bluesky_username'),
                get_option('bluesky_password')
            );

            $response = $poster->postContent(
                $weatherUpdate,
                '',
                get_option('weather_live_url')
            );
            
            if (wp_doing_ajax()) {
                wp_send_json_success([
                    'weather' => $weatherUpdate,
                    'api_response' => $response,
                    'timestamp' => current_time('mysql')
                ]);
            }
            
        } catch (Exception $e) {
            if (wp_doing_ajax()) {
                wp_send_json_error([
                    'message' => $e->getMessage(),
                    'timestamp' => current_time('mysql')
                ]);
            }
        }
    }

    public function activate() {
        if (!wp_next_scheduled('bluesky_weather_post_event')) {
            wp_schedule_event(time(), 'hourly', 'bluesky_weather_post_event');
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook('bluesky_weather_post_event');
    }
}

// Initialize the plugin using singleton pattern
$plugin = BlueskyWeatherPosterPlugin::get_instance();

// Register activation and deactivation hooks
register_activation_hook(__FILE__, [$plugin, 'activate']);
register_deactivation_hook(__FILE__, [$plugin, 'deactivate']);
