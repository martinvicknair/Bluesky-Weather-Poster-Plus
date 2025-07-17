<?php

/**
 * Lightweight wrapper around the Bluesky AT Protocol API.
 *
 * Uses the official **com.atproto** XRPC endpoints:
 *  - /com.atproto.server.createSession  (login → jwt, refresh)
 *  - /com.atproto.repo.createRecord     (create a post record)
 *
 * The class abstracts authentication and token caching so callers just invoke
 * `BlueskyPoster::post_status( $text, $facets, $embed )`.
 *
 * @package BWPP\Core
 */

declared('ABSPATH') || exit;

namespace BWPP\Core;

use WP_Error;

final class BlueskyPoster
{

    /* --------------------------------------------------------------------- */
    /* –– CONSTANTS & SETTINGS KEYS ––                                       */
    /* --------------------------------------------------------------------- */

    private const DEFAULT_ATP_HOST = 'https://bsky.social';

    private const TRANSIENT_TOKEN  = 'bwpp_bsky_token';

    /* --------------------------------------------------------------------- */
    /* –– PUBLIC API ––                                                      */
    /* --------------------------------------------------------------------- */

    /**
     * Post a status to Bluesky.
     *
     * @param string $text   The body text (max ~300 chars).
     * @param array  $facets Optional facets array (mentions, links) as per AT
     *                       Proto lexicon.
     * @param array  $embed  Optional embed block (images, external links,...).
     *
     * @return true|WP_Error  Bool true on success, WP_Error on failure.
     */
    public static function post_status(string $text, array $facets = [], array $embed = null)
    {
        $text = wp_strip_all_tags(wp_unslash($text));

        $token = self::get_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $settings = get_option('bwpp_settings', []);
        $did      = $settings['bwp_bsky_did'] ?? null; // Creator DID – optional.
        $repo     = $did ?: $settings['bwp_bsky_handle'] ?? '';
        if (empty($repo)) {
            return new WP_Error('bwpp_missing_repo', __('Bluesky handle is missing', 'bwpp'));
        }

        $host  = $settings['bwp_bsky_host'] ?? self::DEFAULT_ATP_HOST;
        $route = '/xrpc/com.atproto.repo.createRecord';

        // Build record JSON.
        $record = [
            '$type'   => 'app.bsky.feed.post',
            'text'    => $text,
            'createdAt' => gmdate('c'),
        ];
        if (! empty($facets)) {
            $record['facets'] = $facets; // Assumed pre-validated.
        }
        if (! empty($embed)) {
            $record['embed'] = $embed;
        }

        $payload = [
            'repo'   => $repo,
            'collection' => 'app.bsky.feed.post',
            'record' => $record,
        ];

        $response = wp_remote_post($host . $route, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            $body = wp_remote_retrieve_body($response);
            return new WP_Error('bwpp_bsky_http', sprintf(__('Bluesky HTTP %d: %s', 'bwpp'), $code, $body));
        }

        return true;
    }

    /* --------------------------------------------------------------------- */
    /* –– TOKEN MANAGEMENT ––                                                */
    /* --------------------------------------------------------------------- */

    /**
     * Retrieve (and refresh if needed) the JWT access token.
     *
     * @return string|WP_Error  Access token or WP_Error on failure.
     */
    private static function get_token()
    {
        $cached = get_transient(self::TRANSIENT_TOKEN);
        if ($cached) {
            return $cached;
        }

        $settings = get_option('bwpp_settings', []);
        $handle   = $settings['bwp_bsky_handle']   ?? '';
        $app_pw   = $settings['bwp_bsky_app_pw']   ?? '';
        $host     = $settings['bwp_bsky_host']     ?? self::DEFAULT_ATP_HOST;

        if (empty($handle) || empty($app_pw)) {
            return new WP_Error('bwpp_missing_creds', __('Bluesky credentials incomplete', 'bwpp'));
        }

        $auth = self::request_token($handle, $app_pw, $host);
        if (is_wp_error($auth)) {
            return $auth;
        }

        // Cache for 30 minutes or whatever the API returns (expiresIn).
        set_transient(self::TRANSIENT_TOKEN, $auth['accessJwt'], 30 * MINUTE_IN_SECONDS);

        return $auth['accessJwt'];
    }

    /**
     * Perform login and return token array.
     *
     * @param string $identifier handle or email.
     * @param string $password   Bluesky app password.
     * @param string $host       AT Proto host.
     *
     * @return array|WP_Error
     */
    private static function request_token(string $identifier, string $password, string $host)
    {
        $route = '/xrpc/com.atproto.server.createSession';

        $response = wp_remote_post($host . $route, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'identifier' => $identifier,
                'password'   => $password,
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            return new WP_Error('bwpp_auth_http', sprintf(__('Auth HTTP %d', 'bwpp'), $code));
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        if (! $json || empty($json['accessJwt'])) {
            return new WP_Error('bwpp_auth_json', __('Invalid auth response', 'bwpp'));
        }

        return $json;
    }
}
// EOF