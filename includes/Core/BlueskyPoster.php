<?php

/**
 * File: includes/Core/BlueskyPoster.php
 * Low-level Bluesky API wrapper.
 *
 * • Logs in (and caches a token) for one **or two** accounts.
 * • Accepts the fully-formatted payload from Formatter::build()
 *   and creates the `app.bsky.feed.post` record.
 *
 * @package BWPP\Core
 */

namespace BWPP\Core;

defined('ABSPATH') || exit;

use WP_Error;

final class BlueskyPoster
{

    /*------------------------------------------------------------------*/
    /* Public – entry point                                             */
    /*------------------------------------------------------------------*/

    /**
     * Post to the primary (and optional second) account.
     *
     * @param array $payload ['text','facets','embed']
     * @param array $settings bwpp_settings
     *
     * @return true|WP_Error  true on success, WP_Error on first failure.
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
                return $err; // stop on first failure
            }
        }
        return true;
    }

    /*------------------------------------------------------------------*/
    /* Internal helpers                                                 */
    /*------------------------------------------------------------------*/

    /**
     * Post once for a given handle/password.
     *
     * @return true|WP_Error
     */
    private static function post_single_account(string $handle, string $app_pw, array $payload)
    {

        if (! $handle || ! $app_pw) {
            return new WP_Error('bwpp_missing_creds', __('Missing Bluesky credentials.', 'bwpp'));
        }

        $token = self::get_token($handle, $app_pw);
        if (is_wp_error($token)) {
            return $token;
        }

        $api = 'https://bsky.social/xrpc/com.atproto.repo.createRecord';
        $body = [
            'repo'       => $handle,
            'collection' => 'app.bsky.feed.post',
            'record'     => array_merge(
                $payload,
                [
                    '$type' => 'app.bsky.feed.post',
                    'createdAt' => gmdate('c'),
                ]
            ),
        ];

        $response = wp_remote_post(
            $api,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 15,
                'body'    => wp_json_encode($body),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }
        if (wp_remote_retrieve_response_code($response) >= 300) {
            return new WP_Error(
                'bwpp_post_failed',
                __('Bluesky post failed', 'bwpp'),
                $response
            );
        }
        return true;
    }

    /**
     * Get (or refresh) a JWT for this handle.
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

        $resp = wp_remote_post(
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

        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = wp_remote_retrieve_response_code($resp);
        if ($code >= 300) {
            return new WP_Error('bwpp_login_fail', __('Bluesky login failed', 'bwpp'), $resp);
        }

        $data  = json_decode(wp_remote_retrieve_body($resp), true);
        $token = $data['accessJwt'] ?? '';

        if (! $token) {
            return new WP_Error('bwpp_no_token', __('No token returned', 'bwpp'));
        }

        set_transient($key, $token, 60 * 30); // 30 min
        return $token;
    }
}
