<?php
/**
 * File: includes/Admin/Settings.php
 * Admin → Settings page + Settings API registration.
 * This class exposes a single “Bluesky Weather Poster Plus” page under
 * **Settings ▸ Bluesky Weather Poster Plus**. All plugin options are stored in
 * a *single* serialized array (`bwpp_settings`) for cleanliness. Sanitization
 * happens in `sanitize()`. Schedule changes automatically trigger cron
 * rescheduling via Cron::maybe_attach_settings_listener().
 *
 * @package BWPP\Admin
 */

namespace BWPP\Admin;

defined( 'ABSPATH' ) || exit;

use BWPP\Core\Cron;

final class Settings {

    /* --------------------------------------------------------------------- */
    /* Constants & IDs                                                      */
    /* --------------------------------------------------------------------- */

    public const OPTION_KEY = 'bwpp_settings';
    private const PAGE_SLUG = 'bwpp-settings';

    private const SECTION_GENERAL  = 'bwpp_section_general';
    private const SECTION_SCHEDULE = 'bwpp_section_schedule';
    private const SECTION_POST     = 'bwpp_section_post';

    private const FIELD_HANDLE     = 'bwp_bsky_handle';
    private const FIELD_APP_PW     = 'bwp_bsky_app_pw';
    private const FIELD_CLIENTRAW  = 'bwp_clientraw_url';
    private const FIELD_STATION    = 'bwp_station_url';
    private const FIELD_FREQ       = 'bwp_post_frequency';
    private const FIELD_HOUR       = 'bwp_first_post_hour';
    private const FIELD_MIN        = 'bwp_first_post_minute';
    private const FIELD_UNITS      = 'bwp_units';
    private const FIELD_PREFIX     = 'bwp_post_prefix';
    private const FIELD_TAGS       = 'bwp_hashtags';

    /* --------------------------------------------------------------------- */
    /* Constructor                                                           */
    /* --------------------------------------------------------------------- */

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /* --------------------------------------------------------------------- */
    /* Menu                                                                  */
    /* --------------------------------------------------------------------- */

    public function add_menu(): void {
        add_options_page(
            __( 'Bluesky Weather Poster Plus', 'bwpp' ),
            __( 'Bluesky Weather Poster Plus', 'bwpp' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    /* --------------------------------------------------------------------- */
    /* Settings registration                                                 */
    /* --------------------------------------------------------------------- */

    public function register_settings(): void {
        register_setting( 'bwpp', self::OPTION_KEY, [ $this, 'sanitize' ] );

        // General Section
        add_settings_section(
            self::SECTION_GENERAL,
            __( 'General', 'bwpp' ),
            static fn() => print '<p>' . esc_html__( 'Connection details & source URLs.', 'bwpp' ) . '</p>',
            self::PAGE_SLUG
        );
        $this->add_field( self::FIELD_HANDLE, __( 'Bluesky Handle', 'bwpp' ), self::SECTION_GENERAL, 'text' );
        $this->add_field( self::FIELD_APP_PW, __( 'Bluesky App Password', 'bwpp' ), self::SECTION_GENERAL, 'password' );
        $this->add_field( self::FIELD_CLIENTRAW, __( 'clientraw.txt URL', 'bwpp' ), self::SECTION_GENERAL, 'url' );
        $this->add_field( self::FIELD_STATION, __( 'Station URL', 'bwpp' ), self::SECTION_GENERAL, 'url' );

        // Schedule Section
        add_settings_section(
            self::SECTION_SCHEDULE,
            __( 'Schedule', 'bwpp' ),
            static fn() => print '<p>' . esc_html__( 'How often to post and at what time.', 'bwpp' ) . '</p>',
            self::PAGE_SLUG
        );
        $this->add_field( self::FIELD_FREQ, __( 'Posting Frequency (hours)', 'bwpp' ), self::SECTION_SCHEDULE, 'number', [ 'min' => 1, 'max' => 24 ] );
        $this->add_field( self::FIELD_HOUR, __( 'First Post Hour (0-23)', 'bwpp' ), self::SECTION_SCHEDULE, 'number', [ 'min' => 0, 'max' => 23 ] );
        $this->add_field( self::FIELD_MIN, __( 'First Post Minute (0-59)', 'bwpp' ), self::SECTION_SCHEDULE, 'number', [ 'min' => 0, 'max' => 59 ] );

        // Post formatting Section
        add_settings_section(
            self::SECTION_POST,
            __( 'Post Formatting', 'bwpp' ),
            static fn() => print '<p>' . esc_html__( 'Units, prefix, hashtags.', 'bwpp' ) . '</p>',
            self::PAGE_SLUG
        );
        $this->add_field( self::FIELD_UNITS, __( 'Units', 'bwpp' ), self::SECTION_POST, 'select', [
            'options' => [
                'metric'   => __( 'Metric', 'bwpp' ),
                'imperial' => __( 'Imperial', 'bwpp' ),
                'both'     => __( 'Both', 'bwpp' ),
            ],
        ] );
        $this->add_field( self::FIELD_PREFIX, __( 'Post Prefix', 'bwpp' ), self::SECTION_POST, 'text' );
        $this->add_field( self::FIELD_TAGS, __( 'Hashtags (comma-separated)', 'bwpp' ), self::SECTION_POST, 'text' );
    }

    /* --------------------------------------------------------------------- */
    /* Field helper                                                          */
    /* --------------------------------------------------------------------- */

    private function add_field( string $id, string $label, string $section, string $type, array $args = [] ): void {
        $callback = function () use ( $id, $type, $args ) {
            $options = get_option( self::OPTION_KEY, [] );
            $val     = $options[ $id ] ?? '';
            $name    = self::OPTION_KEY . '[' . esc_attr( $id ) . ']';

            switch ( $type ) {
                case 'text':
                case 'password':
                case 'url':
                case 'number':
                    $extra = '';
                    foreach ( $args as $k => $v ) {
                        if ( in_array( $k, [ 'min', 'max', 'step' ], true ) ) {
                            $extra .= sprintf( ' %s="%s"', esc_attr( $k ), esc_attr( $v ) );
                        }
                    }
                    printf( '<input type="%1$s" name="%2$s" value="%3$s" class="regular-text" %4$s />', esc_attr( $type ), $name, esc_attr( $val ), $extra );
                    break;
                case 'select':
                    $options_arr = $args['options'] ?? [];
                    echo '<select name="' . $name . '">';
                    foreach ( $options_arr as $key => $lab ) {
                        printf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $val, $key, false ), esc_html( $lab ) );
                    }
                    echo '</select>';
                    break;
            }
        };

        add_settings_field( $id, $label, $callback, self::PAGE_SLUG, $section );
    }

    /* --------------------------------------------------------------------- */
    /* Sanitization                                                          */
    /* --------------------------------------------------------------------- */

    public function sanitize( array $input ): array {
        $out = [];

        $out[ self::FIELD_HANDLE ]    = sanitize_text_field( $input[ self::FIELD_HANDLE ] ?? '' );
        $out[ self::FIELD_APP_PW ]    = sanitize_text_field( $input[ self::FIELD_APP_PW ] ?? '' );
        $out[ self::FIELD_CLIENTRAW ] = esc_url_raw( $input[ self::FIELD_CLIENTRAW ] ?? '' );
        $out[ self::FIELD_STATION ]   = esc_url_raw( $input[ self::FIELD_STATION ] ?? '' );

        $out[ self::FIELD_FREQ ] = max( 1, min( 24, (int) ( $input[ self::FIELD_FREQ ] ?? 1 ) ) );
        $out[ self::FIELD_HOUR ] = max( 0, min( 23, (int) ( $input[ self::FIELD_HOUR ] ?? 0 ) ) );
        $out[ self::FIELD_MIN ]  = max( 0, min( 59, (int) ( $input[ self::FIELD_MIN ] ?? 0 ) ) );

        $units_allowed = [ 'metric', 'imperial', 'both' ];
        $units         = $input[ self::FIELD_UNITS ] ?? 'both';
        $out[ self::FIELD_UNITS ] = in_array( $units, $units_allowed, true ) ? $units : 'both';

        $out[ self::FIELD_PREFIX ] = sanitize_text_field( $input[ self::FIELD_PREFIX ] ?? 'Weather Update' );
        $out[ self::FIELD_TAGS ]   = sanitize_text_field( $input[ self::FIELD_TAGS ] ?? '' );

        return $out;
    }

    /* --------------------------------------------------------------------- */
    /* Render page                                                           */
    /* --------------------------------------------------------------------- */

    public function render(): void { ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Bluesky Weather Poster Plus', 'bwpp' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( '
