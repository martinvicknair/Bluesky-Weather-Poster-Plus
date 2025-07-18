<?php

/**
 * File: includes/Admin/Ajax.php
 * Registers REST routes for live preview & “Send Test Post”.
 *
 * @package BWPP\Admin
 */

namespace BWPP\Admin;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP_Error;
use BWPP\Core\{ClientrawParser, Formatter, BlueskyPoster};

final class Ajax
{

    public function __construct()
    {
        // If rest_api_init already ran, register right away; otherwise hook it.
        if (did_action('rest_api_init')) {
            $this->register_routes();
        } else {
            add_action('rest_api_init', [$this, 'register_routes']);
        }
    }

    /*--------------------------------------------------------------*/
    /* Route registration                                           */
    /*--------------------------------------------------------------*/
    public function register_routes(): void
    {
        register_rest_route('bwpp/v1', '/preview', [
            'methods'             => 'POST',
            'permission_callback' => fn() => current_user_can('manage_options'),
            'callback'            => [$this, 'rest_preview'],
        ]);

        register_rest_route('bwpp/v1', '/testpost', [
            'methods'             => 'POST',
            'permission_callback' => fn() => current_user_can('manage_options'),
            'callback'            => [$this, 'rest_testpost'],
        ]);
    }

    /*--------------------------------------------------------------*/
    /* Callbacks                                                    */
    /*--------------------------------------------------------------*/
    public function rest_preview(WP_REST_Request $req)
    {
        parse_str($req->get_body(), $form);
        $settings = Settings::instance()->sanitize($form['bwpp_settings'] ?? []);
        $data     = ClientrawParser::fetch($settings['bwp_clientraw_url'] ?? '') ?: [];
        $pay      = Formatter::build($data, $settings);

        return [
            'text'   => $pay['text'],
            'length' => mb_strlen($pay['text']),
        ];
    }

    public function rest_testpost(WP_REST_Request $req)
    {
        parse_str($req->get_body(), $form);
        $settings = Settings::instance()->sanitize($form['bwpp_settings'] ?? []);
        $data     = ClientrawParser::fetch($settings['bwp_clientraw_url'] ?? '') ?: [];
        $pay      = Formatter::build($data, $settings);
        $res      = BlueskyPoster::send($pay, $settings);

        if (is_wp_error($res)) {
            return new WP_Error('bwpp_fail', $res->get_error_message(), ['status' => 500]);
        }
        return ['message' => __('Post successful!', 'bwpp')];
    }
}
// EOF
