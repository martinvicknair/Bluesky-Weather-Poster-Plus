<?php

/**
 * File: includes/Admin/Settings.php
 * Admin → Settings page, Settings API registration, live preview assets.
 *
 * @package BWPP\Admin
 */

namespace BWPP\Admin;

defined('ABSPATH') || exit;

use BWPP\Core\Cron;

final class Settings
{

    /*--------------------------------------------------------------------
	 * Singleton
	 *------------------------------------------------------------------*/
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /*--------------------------------------------------------------------
	 * Constants / IDs
	 *------------------------------------------------------------------*/
    public const OPTION_KEY = 'bwpp_settings';
    private const PAGE_SLUG = 'bwpp-settings';

    private const SECTION_GENERAL  = 'bwpp_section_general';
    private const SECTION_SCHEDULE = 'bwpp_section_schedule';
    private const SECTION_POST     = 'bwpp_section_post';

    // ── General
    private const FIELD_HANDLE         = 'bwp_bsky_handle';
    private const FIELD_APP_PW         = 'bwp_bsky_app_pw';

    private const FIELD_SECOND_TOGGLE  = 'bwp_second_enable';
    private const FIELD_SECOND_HANDLE  = 'bwp_second_handle';
    private const FIELD_SECOND_APP_PW  = 'bwp_second_app_pw';

    private const FIELD_CLIENTRAW      = 'bwp_clientraw_url';
    private const FIELD_STATION        = 'bwp_station_url';
    private const FIELD_STATION_TEXT   = 'bwp_station_text';

    private const FIELD_WEBCAM_URL     = 'bwp_webcam_url';
    private const FIELD_WEBCAM_ALT     = 'bwp_webcam_alt';

    // ── Schedule
    private const FIELD_FREQ_PRESET    = 'bwp_freq_preset';
    private const FIELD_HOUR           = 'bwp_first_post_hour';
    private const FIELD_MIN            = 'bwp_first_post_minute';

    // ── Post
    private const FIELD_UNITS          = 'bwp_units';
    private const FIELD_PREFIX         = 'bwp_post_prefix';
    private const FIELD_TAGS           = 'bwp_hashtags';
    private const FIELD_FIELDS         = 'bwp_content_fields';

    /*--------------------------------------------------------------------
	 * Constructor – hooks & assets
	 *------------------------------------------------------------------*/
    private function __construct()
    {
        add_action('admin_menu',            [$this, 'add_menu']);
        add_action('admin_init',            [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    /** Enqueue JS only on our settings screen */
    public function enqueue(string $hook): void
    {
        if ('settings_page_' . self::PAGE_SLUG !== $hook) {
            return;
        }

        // Ensures `window.wpApiSettings.nonce` is present:
        wp_enqueue_script('wp-api-request');

        wp_enqueue_script(
            'bwpp-admin',
            plugins_url('assets/js/bwpp-admin.js', BWPP_PATH . 'bluesky-weather-poster-plus.php'),
            ['wp-api-request', 'jquery'],
            BWPP_VERSION,
            true
        );
    }

    /*--------------------------------------------------------------------
	 * Menu
	 *------------------------------------------------------------------*/
    public function add_menu(): void
    {
        add_options_page(
            __('Bluesky Weather Poster Plus', 'bwpp'),
            __('Bluesky Weather Poster Plus', 'bwpp'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    /*--------------------------------------------------------------------
	 * Settings API
	 *------------------------------------------------------------------*/
    public function register_settings(): void
    {

        register_setting('bwpp', self::OPTION_KEY, [$this, 'sanitize']);

        /* ---------- General section --------------------------------- */
        add_settings_section(
            self::SECTION_GENERAL,
            __('General', 'bwpp'),
            static fn() => print '<p>' . esc_html__('Connection details & source URLs.', 'bwpp') . '</p>',
            self::PAGE_SLUG
        );

        // primary account
        $this->add_field(
            self::FIELD_HANDLE,
            __('Bluesky Handle', 'bwpp'),
            self::SECTION_GENERAL,
            'text'
        );
        $this->add_field(
            self::FIELD_APP_PW,
            __('Bluesky App Password', 'bwpp'),
            self::SECTION_GENERAL,
            'password'
        );

        // second account
        $this->add_field(
            self::FIELD_SECOND_TOGGLE,
            __('Enable posting to a second Bluesky account', 'bwpp'),
            self::SECTION_GENERAL,
            'checkbox'
        );
        $this->add_field(
            self::FIELD_SECOND_HANDLE,
            __('Second Bluesky Handle', 'bwpp'),
            self::SECTION_GENERAL,
            'text'
        );
        $this->add_field(
            self::FIELD_SECOND_APP_PW,
            __('Second Bluesky App Password', 'bwpp'),
            self::SECTION_GENERAL,
            'password'
        );

        // URLs
        $this->add_field(
            self::FIELD_CLIENTRAW,
            __('clientraw.txt URL', 'bwpp'),
            self::SECTION_GENERAL,
            'url'
        );
        $this->add_field(
            self::FIELD_STATION,
            __('Station URL', 'bwpp'),
            self::SECTION_GENERAL,
            'url'
        );
        $this->add_field(
            self::FIELD_STATION_TEXT,
            __('Station Link Display Text', 'bwpp'),
            self::SECTION_GENERAL,
            'text'
        );

        // webcam
        $this->add_field(
            self::FIELD_WEBCAM_URL,
            __('Latest Webcam Image URL', 'bwpp'),
            self::SECTION_GENERAL,
            'url'
        );
        $this->add_field(
            self::FIELD_WEBCAM_ALT,
            __('Webcam Display Text (alt)', 'bwpp'),
            self::SECTION_GENERAL,
            'text'
        );

        /* ---------- Schedule section -------------------------------- */
        add_settings_section(
            self::SECTION_SCHEDULE,
            __('Schedule', 'bwpp'),
            static fn() => print '<p>' . esc_html__('Posting frequency & first-run time.', 'bwpp') . '</p>',
            self::PAGE_SLUG
        );

        $presets = [
            '1'  => __('Every hour', 'bwpp'),
            '3'  => __('Every 3 hours', 'bwpp'),
            '6'  => __('Every 6 hours', 'bwpp'),
            '12' => __('Every 12 hours', 'bwpp'),
            '24' => __('Every 24 hours', 'bwpp'),
        ];
        $this->add_field(
            self::FIELD_FREQ_PRESET,
            __('Posting Frequency', 'bwpp'),
            self::SECTION_SCHEDULE,
            'select',
            ['options' => $presets]
        );

        // dropdowns 00-23 / 00-59
        $this->add_field(
            self::FIELD_HOUR,
            __('First Post Hour', 'bwpp'),
            self::SECTION_SCHEDULE,
            'select',
            [
                'options' => array_combine(
                    range(0, 23),
                    array_map(fn($h) => sprintf('%02d', $h), range(0, 23))
                ),
            ]
        );
        $this->add_field(
            self::FIELD_MIN,
            __('First Post Minute', 'bwpp'),
            self::SECTION_SCHEDULE,
            'select',
            [
                'options' => array_combine(
                    range(0, 59),
                    array_map(fn($m) => sprintf('%02d', $m), range(0, 59))
                ),
            ]
        );

        /* ---------- Post formatting section ------------------------- */
        add_settings_section(
            self::SECTION_POST,
            __('Post Formatting', 'bwpp'),
            static fn() => print '<p>' . esc_html__('Units, prefix, hashtags, data fields.', 'bwpp') . '</p>',
            self::PAGE_SLUG
        );

        $this->add_field(
            self::FIELD_UNITS,
            __('Units', 'bwpp'),
            self::SECTION_POST,
            'select',
            ['options' => [
                'metric'   => __('Metric', 'bwpp'),
                'imperial' => __('Imperial', 'bwpp'),
                'both'     => __('Both', 'bwpp'),
            ]]
        );
        $this->add_field(
            self::FIELD_PREFIX,
            __('Post Prefix', 'bwpp'),
            self::SECTION_POST,
            'text'
        );
        $this->add_field(
            self::FIELD_TAGS,
            __('Hashtags (comma-separated)', 'bwpp'),
            self::SECTION_POST,
            'text'
        );

        $boxes = [
            'temp'       => __('Temperature', 'bwpp'),
            'windchill'  => __('Wind-chill', 'bwpp'),
            'humidex'    => __('Humidex', 'bwpp'),
            'dew'        => __('Dew Point', 'bwpp'),
            'temp_max'   => __('Max Temp Today', 'bwpp'),
            'temp_min'   => __('Min Temp Today', 'bwpp'),
            'wind_dir'   => __('Wind Direction', 'bwpp'),
            'wind_speed' => __('Wind Speed', 'bwpp'),
            'wind_gust'  => __('Max Gust Today', 'bwpp'),
            'humidity'   => __('Humidity', 'bwpp'),
            'pressure'   => __('Pressure', 'bwpp'),
            'rain'       => __('Rain Today', 'bwpp'),
            'desc'       => __('Weather Description', 'bwpp'),
        ];
        $this->add_field(
            self::FIELD_FIELDS,
            __('Post Content Fields', 'bwpp'),
            self::SECTION_POST,
            'checkbox_group',
            ['options' => $boxes]
        );
    }

    /*--------------------------------------------------------------------
	 * Field helper
	 *------------------------------------------------------------------*/
    private function add_field(
        string $id,
        string $label,
        string $section,
        string $type,
        array $args = []
    ): void {

        $callback = function () use ($id, $type, $args) {

            $opt  = get_option(self::OPTION_KEY, []);
            $val  = $opt[$id] ?? '';
            $name = self::OPTION_KEY . '[' . esc_attr($id) . ']';

            switch ($type) {
                case 'checkbox':
                    printf(
                        '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
                        $name,
                        checked($val, '1', false),
                        esc_html__('Enable', 'bwpp')
                    );
                    break;

                case 'checkbox_group':
                    foreach ($args['options'] ?? [] as $key => $lab) {
                        $chk = ! empty($val[$key]);
                        printf(
                            '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
                            $name,
                            esc_attr($key),
                            checked($chk, true, false),
                            esc_html($lab)
                        );
                    }
                    break;

                case 'text':
                case 'password':
                case 'url':
                    printf(
                        '<input type="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
                        esc_attr($type),
                        $name,
                        esc_attr($val)
                    );
                    break;

                case 'select':
                    echo '<select name="' . $name . '">';
                    foreach ($args['options'] ?? [] as $k => $lab) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($k),
                            selected($val, $k, false),
                            esc_html($lab)
                        );
                    }
                    echo '</select>';
                    break;
            }
        };

        add_settings_field($id, $label, $callback, self::PAGE_SLUG, $section);
    }

    /*--------------------------------------------------------------------
	 * Sanitization
	 *------------------------------------------------------------------*/
    public function sanitize(array $in): array
    {

        $o = [];

        // primary creds
        $o[self::FIELD_HANDLE] = sanitize_text_field($in[self::FIELD_HANDLE] ?? '');
        $o[self::FIELD_APP_PW] = sanitize_text_field($in[self::FIELD_APP_PW] ?? '');

        // second account
        $o[self::FIELD_SECOND_TOGGLE] = empty($in[self::FIELD_SECOND_TOGGLE]) ? '' : '1';
        $o[self::FIELD_SECOND_HANDLE] = sanitize_text_field($in[self::FIELD_SECOND_HANDLE] ?? '');
        $o[self::FIELD_SECOND_APP_PW] = sanitize_text_field($in[self::FIELD_SECOND_APP_PW] ?? '');

        // urls
        $o[self::FIELD_CLIENTRAW]    = esc_url_raw($in[self::FIELD_CLIENTRAW] ?? '');
        $o[self::FIELD_STATION]      = esc_url_raw($in[self::FIELD_STATION] ?? '');
        $o[self::FIELD_STATION_TEXT] = sanitize_text_field($in[self::FIELD_STATION_TEXT] ?? '');
        $o[self::FIELD_WEBCAM_URL]   = esc_url_raw($in[self::FIELD_WEBCAM_URL] ?? '');
        $o[self::FIELD_WEBCAM_ALT]   = sanitize_text_field($in[self::FIELD_WEBCAM_ALT] ?? '');

        // schedule
        $h = (int) ($in[self::FIELD_HOUR] ?? 0);
        $m = (int) ($in[self::FIELD_MIN] ?? 0);
        $o[self::FIELD_HOUR] = max(0, min(23, $h));
        $o[self::FIELD_MIN]  = max(0, min(59, $m));

        $preset = (int) ($in[self::FIELD_FREQ_PRESET] ?? 1);
        $o[self::FIELD_FREQ_PRESET] = in_array($preset, [1, 3, 6, 12, 24], true) ? $preset : 1;

        // formatting
        $units = $in[self::FIELD_UNITS] ?? 'both';
        $o[self::FIELD_UNITS] = in_array($units, ['metric', 'imperial', 'both'], true) ? $units : 'both';

        $o[self::FIELD_PREFIX] = sanitize_text_field($in[self::FIELD_PREFIX] ?? 'Current conditions:');
        $o[self::FIELD_TAGS]   = sanitize_text_field($in[self::FIELD_TAGS] ?? '');

        // content boxes
        $o[self::FIELD_FIELDS] = [];
        if (isset($in[self::FIELD_FIELDS]) && is_array($in[self::FIELD_FIELDS])) {
            foreach ($in[self::FIELD_FIELDS] as $key => $on) {
                if ($on) {
                    $o[self::FIELD_FIELDS][sanitize_key($key)] = 1;
                }
            }
        }
        if (empty($o[self::FIELD_FIELDS])) { // always include temp
            $o[self::FIELD_FIELDS]['temp'] = 1;
        }

        // reschedule cron
        Cron::register($o);

        return $o;
    }

    /*--------------------------------------------------------------------
	 * Render – adds preview + test-post pane
	 *------------------------------------------------------------------*/
    public function render(): void
    { ?>
        <div class="wrap">
            <h1><?php esc_html_e('Bluesky Weather Poster Plus', 'bwpp'); ?></h1>

            <form id="bwpp-settings-form" method="post" action="options.php">
                <?php
                settings_fields('bwpp');
                do_settings_sections(self::PAGE_SLUG);
                ?>
                <hr>
                <p>
                    <strong><?php esc_html_e('Live preview:', 'bwpp'); ?></strong>
                    <span id="bwpp-spinner" class="spinner" style="float:none;margin:0 5px;"></span>
                </p>
                <pre id="bwpp-preview" style="background:#fafafa;padding:8px;border:1px solid #ddd;"></pre>
                <p><?php esc_html_e('Characters:', 'bwpp'); ?>
                    <span id="bwpp-char">0</span>/300
                </p>

                <p>
                    <button type="button" class="button button-secondary" id="bwpp-test">
                        <?php esc_html_e('Send Test Post', 'bwpp'); ?>
                    </button>
                    <span id="bwpp-result" style="margin-left:10px;font-weight:bold;"></span>
                </p>

                <?php submit_button(); ?>
            </form>
        </div>
<?php }
}
// EOF
