<?php

/**
 * File: includes/Core/BlueskyPoster.php
 * Bluesky API wrapper – handles login, optional blob upload, and posting.
 *
 * Supports one or two accounts.  If Formatter supplied a webcam embed as
 * an “external” link, this class fetches the JPEG/PNG, uploads it as a blob,
 * and converts the payload to an in-line `app.bsky.embed.images` gallery so
 * the snapshot appears directly in the feed.
 *
 * @package BWPP\Core
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

use WP_Error;

final class BlueskyPoster
{

    /*--------------------------------------------------------------------
	 * Public entry
	 *------------------------------------------------------------------*/
    /**
     * Post to the primary (and optional second) account.
     *
     * @param array $payload  Built by Formatter::build().
     * @param array $settings Sanitized bwpp_settings option.
     * @return true|WP_Error
     */
    public static function send(array $payload, array $settings)
    {

        $accounts = [
            [
                'handle' => $settings['bwp_bsky_handle']  ?? '',
                'app_pw' => $settings['bwp_bsky_app_pw']  ?? '',
            ],
        ];

        if (! empty($settings['bwp_second_enable'])) {
            $accounts[] = [
                'handle' => $settings['bwp_second_handle'] ?? '',
                'app_pw' => $settings['bwp_second_app_pw'] ?? '',
            ];
        }

        foreach ($accounts as $acct) {
            $err = self::post_single_account($acct['handle'], $acct['app_pw'], $payload);
            if (is_wp_error($err)) {
                return $err;                // stop on first failure
            }
        }
        return true;
    }

    /*--------------------------------------------------------------------
	 * Internal helpers
	 *------------------------------------------------------------------*/
    /**
     * Ensure an image is ≤ 1 MB by down-scaling if needed.
     *
     * @param string $raw        Original image bytes.
     * @param string &$mime_out  Updated mime (always image/jpeg after resize).
     * @return string            Safe bytes ready for upload.
     */
    private static function shrink_if_needed(string $raw, string &$mime_out): string
    {

        // already under 1 MB → return untouched
        if (strlen($raw) <= 1_000_000) {
            return $raw;
        }
        $tmp = wp_tempnam();                       // create temp file
        file_put_contents($tmp, $raw);

        $editor = wp_get_image_editor($tmp);
        if (is_wp_error($editor)) {            // fallback: send original
            return $raw;
        }

        // constrain longest edge to 1280 px
        $size = $editor->get_size();
        $max  = max($size['width'], $size['height']);
        if ($max > 1280) {
            $editor->resize(
                $size['width'] > $size['height'] ? 1280 : null,
                $size['height'] > $size['width'] ? 1280 : null,
                false
            );
        }

        $editor->set_quality(75);
        $shrunken = $editor->save(null, 'image/jpeg');
        if (! is_wp_error($shrunken) && filesize($shrunken['path']) <= 1_000_000) {
            $mime_out = 'image/jpeg';
            return file_get_contents($shrunken['path']);
        }
        // couldn’t get under 1 MB → return original
        return $raw;
    }

    private static function post_single_account(string $handle, string $app_pw, array $payload)
    {

        if (empty($handle) || empty($app_pw)) {
            return new WP_Error('bwpp_creds', __('Missing Bluesky credentials.', 'bwpp'));
        }

        $token = self::get_token($handle, $app_pw);
        if (is_wp_error($token)) {
            return $token;
        }

        /* ----- convert external webcam link to inline image embed ---------- */
        if (
            isset($payload['embed']['external']['uri']) &&
            filter_var($payload['embed']['external']['uri'], FILTER_VALIDATE_URL)
        ) {

            $img_url = $payload['embed']['external']['uri'];
            $img_res = wp_remote_get($img_url, ['timeout' => 15]);

            if (! is_wp_error($img_res) && wp_remote_retrieve_response_code($img_res) === 200) {

                $mime = wp_remote_retrieve_header($img_res, 'content-type') ?: 'image/jpeg';
                $bytes = wp_remote_retrieve_body($img_res);

                /* NEW: shrink if needed */
                $bytes = self::shrink_if_needed($bytes, $mime);

                $blob = self::upload_blob($token, $mime, $bytes);
                if (! is_wp_error($blob) && isset($blob['blob'])) {
                    $payload['embed'] = [
                        '$type'  => 'app.bsky.embed.images',
                        'images' => [
                            [
                                'alt'   => $payload['embed']['external']['title'] ?? __('Webcam snapshot', 'bwpp'),
                                'image' => $blob['blob'],
                            ],
                        ],
                    ];
                }
            }
        }
        /* ------------------------------------------------------------------ */

        /* ------------------------------------------------------------ */

        $api  = 'https://bsky.social/xrpc/com.atproto.repo.createRecord';
        $body = [
            'repo'       => $handle,
            'collection' => 'app.bsky.feed.post',
            'record'     => array_merge(
                $payload,
                [
                    '$type'     => 'app.bsky.feed.post',
                    'createdAt' => gmdate('c'),
                ]
            ),
        ];

        $res = wp_remote_post(
            $api,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 20,
                'body'    => wp_json_encode($body),
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }
        if (wp_remote_retrieve_response_code($res) >= 300) {
            return new WP_Error('bwpp_post', __('Bluesky post failed', 'bwpp'), $res);
        }
        return true;
    }

    /**
     * Upload a blob to Bluesky and return its descriptor.
     *
     * @param string $token JWT from get_token().
     * @param string $mime  MIME type, e.g. image/jpeg.
     * @param string $data  Raw file bytes.
     * @return array|WP_Error
     */
    private static function upload_blob(string $token, string $mime, string $data)
    {

        $resp = wp_remote_post(
            'https://bsky.social/xrpc/com.atproto.repo.uploadBlob',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => $mime,
                ],
                'timeout' => 20,
                'body'    => $data,
            ]
        );

        if (is_wp_error($resp)) {
            return $resp;
        }
        if (wp_remote_retrieve_response_code($resp) >= 300) {
            return new WP_Error('bwpp_blob', __('Blob upload failed', 'bwpp'), $resp);
        }

        return json_decode(wp_remote_retrieve_body($resp), true);
    }

    /**
     * Get (or cache) a JWT for a given handle/app-password.
     *
     * @return string|WP_Error
     */
    private static function get_token(string $handle, string $app_pw)
    {

        $key   = 'bwpp_bsky_token_' . md5($handle);
        $token = get_transient($key);
        if ($token) {
            return $token;
        }

        $res = wp_remote_post(
            'https://bsky.social/xrpc/com.atproto.server.createSession',
            [
                'timeout' => 15,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode([
                    'identifier' => $handle,
                    'password'   => $app_pw,
                ]),
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }
        if (wp_remote_retrieve_response_code($res) >= 300) {
            return new WP_Error('bwpp_login', __('Bluesky login failed', 'bwpp'), $res);
        }

        $data  = json_decode(wp_remote_retrieve_body($res), true);
        $token = $data['accessJwt'] ?? '';

        if (! $token) {
            return new WP_Error('bwpp_token', __('No token returned', 'bwpp'));
        }

        set_transient($key, $token, 30 * MINUTE_IN_SECONDS);

        return $token;
    }
}
// EOF
