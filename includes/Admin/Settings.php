<?php

/**
 * Admin → Settings page + Settings API registration.
 *
 * This class exposes a single “Bluesky Weather Poster Plus” page under
 * **Settings ▸ Bluesky Weather Poster Plus**. All plugin options are stored in
 * a *single* serialized array (`bwpp_settings`) for cleanliness. Sanitization
 * happens in `sanitize()`; frequency and first‑run changes automatically
 * trigger Cron::register() via the listener in Cron::maybe_attach_settings_listener().
 *
 * @package BWPP\Admin
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

use BWPP\Core\Cron;

final class Settings
{

    /** Option key where everything is stored. */
    public const OPTION_KEY = 'bwpp_settings';

    /** Settings page slug. */
    private const PAGE_SLUG = 'bwpp-settings';

    /** Section IDs. */
    private const SECTION_GENERAL  = 'bwpp_section_general';
    private const SECTION_SCHEDULE = 'bwpp_section_schedule';
    private const SECTION_POST     = 'bwpp_section_post';

    /** Field IDs. */
    // Bluesky creds.
    private const FIELD_HANDLE  = 'bwp_bsky_handle';
    private const FIELD_APP_PW  = 'bwp_bsky_app_pw';
    // Weather / station.
    private const FIELD_CLIENTRAW = 'bwp_clientraw_url';
    private const FIELD_STATION   = 'bwp_station_url';
    // Schedule.
    private const FIELD_FREQ      = 'bwp_post_frequency';
    private const FIELD_HOUR      = 'bwp_first_post_hour';
    private const FIELD_MIN       = 'bwp_first_post_minute';
    // Post formatting.
    private const FIELD_UNITS     = 'bwp_units';
    private const FIELD_PREFIX    = 'bwp_post_prefix';
    private const FIELD_TAGS      = 'bwp_hashtags';

    /**
     * Constructor — wires hooks.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /* --------------------------------------------------------------------- */
    /* –– MENU ––                                                            */
    /* --------------------------------------------------------------------- */

    /**
     * Add options page under “Settings”.
     */
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

    /* --------------------------------------------------------------------- */
    /* –– SETTINGS REGISTRATION ––                                           */
    /* --------------------------------------------------------------------- */

    /**
     * Register settings, sections, and fields.
     */
    public function register_settings(): void
    {
        register_setting('bwpp', self::OPTION_KEY, [$this, 'sanitize']);

        /* General section – Bluesky creds + URLs. */
        add_settings_section(
            self::SECTION_GENERAL,
            __('General', 'bwpp'),
            fn() => print '<p>' . esc_html__('Required connection details.', 'bwpp') . '</p>',
            self::PAGE_SLUG
        );

        $this->add_field(self::FIELD_HANDLE, __('Bluesky Handle', 'bwpp'), self::SECTION_GENERAL, 'text');
        $this->add_field(self::FIELD_APP_PW, __('Bluesky App Password', 'bwpp'), self::SECTION_GENERAL, 'password');
        $this->add_field(self::FIELD_CLIENTRAW, __('clientraw.txt URL', 'bwpp'), self::SECTION_GENERAL, 'url');
        $this->add_field(self::FIELD_STATION, __('Station URL', 'bwpp'), self::SECTION_GENERAL, 'url');

        /* Schedule section. */
        add_settings_section(
            self::SECTION_SCHEDULE,
            __('Schedule', 'bwpp'),
            fn() => print '<p>' . esc_html__('How often and at what time the post should be sent.', 'bwpp') . '</p>',
            self::PAGE_SLUG
        );
        $this->add_field(self::FIELD_FREQ, __('Posting Frequency (hours)', 'bwpp'), self::SECTION_SCHEDULE, 'number', ['min' => 1, 'max' => 24]);
        $this->add_field(self::FIELD_HOUR, __('First Post Hour (0‑23)', 'bwpp'), self::SECTION_SCHEDULE, 'number', ['min' => 0, 'max' => 23]);
        $this->add_field(self::FIELD_MIN, __('First Post Minute (0‑59)', 'bwpp'), self::SECTION_SCHEDULE, 'number', ['min' => 0, 'max' => 59]);

        /* Post options. */
        add_settings_section(
            self::SECTION_POST,
            __('Post Formatting', 'bwpp'),
            fn() => print '<p>' . esc_html__('Control units, prefix text, tags.', 'bwpp') . '</p>',
            self::PAGE_SLUG
        );
        $this->add_field(self::FIELD_UNITS, __('Units', 'bwpp'), self::SECTION_POST, 'select', [
            'options' => [
                'metric'   => __('Metric', 'bwpp'),
                'imperial' => __('Imperial', 'bwpp'),
                'both'     => __('Both', 'bwpp'),
            ],
        ]);
        $this->add_field(self::FIELD_PREFIX, __('Post Prefix', 'bwpp'), self::SECTION_POST, 'text');
        $this->add_field(self::FIELD_TAGS, __('Hashtags (comma‑separated)', 'bwpp'), self::SECTION_POST, 'text');
    }

    /**
     * Helper to register an individual field.
     */
    private function add_field(string $id, string $label, string $section, string $type, array $args = []): void
    {
        $cb = function () use ($id, $type, $args) {
            $options = get_option(self::OPTION_KEY, []);
            $val     = $options[$id] ?? '';
            $name    = self::OPTION_KEY . '[' . esc_attr($id) . ']';

            switch ($type) {
                case 'password':
                case 'url':
                case 'number':
                case 'text':
                    $extra = '';
                    foreach ($args as $k => $v) {
                        if (in_array($k, ['min', 'max', 'step'], true)) {
                            $extra .= sprintf(' %s="%s"', esc_attr($k), esc_attr($v));
                        }
                    }
                    printf('<input type="%1$s" name="%2$s" value="%3$s" class="regular-text" %4$s/>', esc_attr($type), $name, esc_attr($val), $extra);
                    break;

                case 'select':
                    $options_arr = $args['options'] ?? [];
                    echo '<select name="' . $name . '">';
                    foreach ($options_arr as $key => $label) {
                        printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($val, $key, false), esc_html($label));
                    }
                    echo '</select>';
                    break;
            }
        };

        add_settings_field($id, $label, $cb, self::PAGE_SLUG, $section);
    }

    /* --------------------------------------------------------------------- */
    /* –– SANITIZATION ––                                                    */
    /* --------------------------------------------------------------------- */

    /**
     * Sanitize and validate settings.
     */
    public function sanitize(array $input): array
    {
        $output = [];

        // Handle / did.
        $output[self::FIELD_HANDLE] = sanitize_text_field($input[self::FIELD_HANDLE] ?? '');
        $output[self::FIELD_APP_PW] = sanitize_text_field($input[self::FIELD_APP_PW] ?? '');

        // URLs.
        $output[self::FIELD_CLIENTRAW] = esc_url_raw($input[self::FIELD_CLIENTRAW] ?? '');
        $output[self::FIELD_STATION]   = esc_url_raw($input[self::FIELD_STATION] ?? '');

        // Schedule (ints).
        $freq = (int) ($input[self::FIELD_FREQ] ?? 1);
        $output[self::FIELD_FREQ] = max(1, min(24, $freq));

        $hour = (int) ($input[self::FIELD_HOUR] ?? 0);
        $output[self::FIELD_HOUR] = max(0, min(23, $hour));

        $min = (int) ($input[self::FIELD_MIN] ?? 0);
        $output[self::FIELD_MIN] = max(0, min(59, $min));

        // Post opts.
        $units_allowed = ['metric', 'imperial', 'both'];
        $units         = $input[self::FIELD_UNITS] ?? 'both';
        $output[self::FIELD_UNITS] = in_array($units, $units_allowed, true) ? $units : 'both';

        $output[self::FIELD_PREFIX] = sanitize_text_field($input[self::FIELD_PREFIX] ?? 'Weather Update');
        $output[self::FIELD_TAGS]   = sanitize_text_field($input[self::FIELD_TAGS] ?? '');

        return $output;
    }

    /* --------------------------------------------------------------------- */
    /* –– RENDER ––                                                          */
    /* --------------------------------------------------------------------- */

    /**
     * Render the settings page.
     */
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
