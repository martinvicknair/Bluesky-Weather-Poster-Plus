<?php

/**
 * File: includes/Admin/Settings.php
 * Admin → Settings page + Settings API registration.
 * Adds all legacy-feature controls: second account, webcam image, station-link
 * text, frequency dropdown, and granular “post-content” checkboxes.
 *
 * @package BWPP\Admin
 */

namespace BWPP\Admin;

defined('ABSPATH') || exit;

use BWPP\Core\Cron;

final class Settings
{

    /*--------------------------------------------------------------------*/
    /* Constants / IDs                                                    */
    /*--------------------------------------------------------------------*/

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

    /*--------------------------------------------------------------------*/
    /* Constructor                                                        */
    /*--------------------------------------------------------------------*/

    public function __construct()
    {
        add_action('admin_menu',  [$this, 'add_menu']);
        add_action('admin_init',  [$this, 'register_settings']);
    }

    /*--------------------------------------------------------------------*/
    /* Menu                                                               */
    /*--------------------------------------------------------------------*/

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

    /*--------------------------------------------------------------------*/
    /* Settings API                                                       */
    /*--------------------------------------------------------------------*/

    public function register_settings(): void
    {
        register_setting('bwpp', self::OPTION_KEY, [$this, 'sanitize']);

        /* ---------- General section ----------------------------------- */
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

        // second account group
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

        // urls
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

        /* ---------- Schedule section ---------------------------------- */
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
        $this->add_field(
            self::FIELD_HOUR,
            __('First Post Hour (0–23)', 'bwpp'),
            self::SECTION_SCHEDULE,
            'number',
            ['min' => 0, 'max' => 23]
        );
        $this->add_field(
            self::FIELD_MIN,
            __('First Post Minute (0–59)', 'bwpp'),
            self::SECTION_SCHEDULE,
            'number',
            ['min' => 0, 'max' => 59]
        );

        /* ---------- Post-formatting section --------------------------- */
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

        // checkbox grid for content fields
        $boxes = [
            'temp'          => __('Temperature', 'bwpp'),
            'windchill'     => __('Wind-chill', 'bwpp'),
            'humidex'       => __('Humidex', 'bwpp'),
            'dew'           => __('Dew Point', 'bwpp'),
            'temp_max'      => __('Max Temperature Today', 'bwpp'),
            'temp_min'      => __('Min Temperature Today', 'bwpp'),
            'wind_dir'      => __('Wind Direction', 'bwpp'),
            'wind_speed'    => __('Wind Speed', 'bwpp'),
            'wind_gust'     => __('Max Gust Today', 'bwpp'),
            'humidity'      => __('Humidity', 'bwpp'),
            'pressure'      => __('Pressure', 'bwpp'),
            'rain'          => __('Rain Today', 'bwpp'),
            'desc'          => __('Weather Description', 'bwpp'),
        ];
        $this->add_field(
            self::FIELD_FIELDS,
            __('Post Content Fields', 'bwpp'),
            self::SECTION_POST,
            'checkbox_group',
            ['options' => $boxes]
        );
    }

    /*--------------------------------------------------------------------*/
    /* Field Helper                                                       */
    /*--------------------------------------------------------------------*/

    private function add_field(string $id, string $label, string $section, string $type, array $args = []): void
    {
        $callback = function () use ($id, $type, $args) {
            $opts = get_option(self::OPTION_KEY, []);
            $val  = $opts[$id] ?? '';
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
                    $options = $args['options'] ?? [];
                    foreach ($options as $key => $lab) {
                        $checked = ! empty($val[$key]);
                        printf(
                            '<label style="display:block; margin-bottom:4px;"><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
                            $name,
                            esc_attr($key),
                            checked($checked, true, false),
                            esc_html($lab)
                        );
                    }
                    break;

                case 'text':
                case 'password':
                case 'url':
                case 'number':
                    $extra = '';
                    foreach ($args as $k => $v) {
                        if (in_array($k, ['min', 'max', 'step'], true)) {
                            $extra .= sprintf(' %s="%s"', esc_attr($k), esc_attr($v));
                        }
                    }
                    printf(
                        '<input type="%1$s" name="%2$s" value="%3$s" class="regular-text" %4$s />',
                        esc_attr($type),
                        $name,
                        esc_attr($val),
                        $extra
                    );
                    break;

                case 'select':
                    $options = $args['options'] ?? [];
                    echo '<select name="' . $name . '">';
                    foreach ($options as $key => $lab) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($key),
                            selected($val, $key, false),
                            esc_html($lab)
                        );
                    }
                    echo '</select>';
                    break;
            }
        };

        add_settings_field($id, $label, $callback, self::PAGE_SLUG, $section);
    }

    /*--------------------------------------------------------------------*/
    /* Sanitization                                                       */
    /*--------------------------------------------------------------------*/

    public function sanitize(array $input): array
    {
        $out = [];

        // primary credentials
        $out[self::FIELD_HANDLE]  = sanitize_text_field($input[self::FIELD_HANDLE] ?? '');
        $out[self::FIELD_APP_PW]  = sanitize_text_field($input[self::FIELD_APP_PW] ?? '');

        // second account
        $out[self::FIELD_SECOND_TOGGLE] = empty($input[self::FIELD_SECOND_TOGGLE]) ? '' : '1';
        $out[self::FIELD_SECOND_HANDLE] = sanitize_text_field($input[self::FIELD_SECOND_HANDLE] ?? '');
        $out[self::FIELD_SECOND_APP_PW] = sanitize_text_field($input[self::FIELD_SECOND_APP_PW] ?? '');

        // urls
        $out[self::FIELD_CLIENTRAW]    = esc_url_raw($input[self::FIELD_CLIENTRAW] ?? '');
        $out[self::FIELD_STATION]      = esc_url_raw($input[self::FIELD_STATION] ?? '');
        $out[self::FIELD_STATION_TEXT] = sanitize_text_field($input[self::FIELD_STATION_TEXT] ?? '');

        $out[self::FIELD_WEBCAM_URL] = esc_url_raw($input[self::FIELD_WEBCAM_URL] ?? '');
        $out[self::FIELD_WEBCAM_ALT] = sanitize_text_field($input[self::FIELD_WEBCAM_ALT] ?? '');

        // schedule
        $preset = (int) ($input[self::FIELD_FREQ_PRESET] ?? 1);
        $out[self::FIELD_FREQ_PRESET] = in_array($preset, [1, 3, 6, 12, 24], true) ? $preset : 1;

        $out[self::FIELD_HOUR] = max(0, min(23, (int) ($input[self::FIELD_HOUR] ?? 0)));
        $out[self::FIELD_MIN]  = max(0, min(59, (int) ($input[self::FIELD_MIN] ?? 0)));

        // formatting
        $units_allowed = ['metric', 'imperial', 'both'];
        $units         = $input[self::FIELD_UNITS] ?? 'both';
        $out[self::FIELD_UNITS] = in_array($units, $units_allowed, true) ? $units : 'both';

        $out[self::FIELD_PREFIX] = sanitize_text_field($input[self::FIELD_PREFIX] ?? 'Current Conditions:');
        $out[self::FIELD_TAGS]   = sanitize_text_field($input[self::FIELD_TAGS] ?? '');

        // content checkboxes (always array)
        $out[self::FIELD_FIELDS] = [];
        $fields_raw = $input[self::FIELD_FIELDS] ?? [];
        if (is_array($fields_raw)) {
            foreach ($fields_raw as $key => $on) {
                if ($on) {
                    $out[self::FIELD_FIELDS][sanitize_key($key)] = 1;
                }
            }
        }
        // enforce at least temperature
        if (empty($out[self::FIELD_FIELDS])) {
            $out[self::FIELD_FIELDS]['temp'] = 1;
        }

        /* re-schedule cron on save */
        Cron::register($out);

        return $out;
    }

    /*--------------------------------------------------------------------*/
    /* Render                                                             */
    /*--------------------------------------------------------------------*/

    public function render(): void
    { ?>
        <div class="wrap">
            <h1><?php esc_html_e('Bluesky Weather Poster Plus', 'bwpp'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('bwpp');
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>
<?php }
}
// EOF