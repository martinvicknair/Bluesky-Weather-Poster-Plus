<?php
/**
 * Weather Poster Bluesky – main poster / admin class
 */
defined( 'ABSPATH' ) || exit;

class WPB_Bluesky_Poster {

	/* ---------------------------------------------------------------------
	 *  Basic admin UI (unchanged from your previous copy)…  constructor,
	 *  menu, settings page, etc.  Omitted here for brevity; leave as is!
	 * -------------------------------------------------------------------*/

	/* ========  IMAGE-UPLOAD HELPERS  ======== */

	/** Resize/compress if >1 MB so blob upload passes limit */
	private function wpb_maybe_resize_image( $bin, $mime, $max = 1048576 ) {

		if ( strlen( $bin ) <= $max ) {
			return [ 'data' => $bin, 'mime' => $mime ];
		}

		$tmp = wp_tempnam( 'wpb_img_' );
		file_put_contents( $tmp, $bin );
		$ed = wp_get_image_editor( $tmp );
		if ( is_wp_error( $ed ) ) {
			@unlink( $tmp );
			return [ 'data' => $bin, 'mime' => $mime ];
		}

		$qual = 85;
		$w    = 1024;

		for ( $i = 0; $i < 5; $i ++ ) {
			if ( 'image/jpeg' === $mime ) {
				$ed->set_quality( $qual );
			} else {
				$sz = $ed->get_size();
				if ( $sz && $sz['width'] > $w ) {
					$ed->resize( $w, null, false );
				}
			}
			$save = $ed->save();
			if ( ! is_wp_error( $save ) && filesize( $save['path'] ) <= $max ) {
				$bin = file_get_contents( $save['path'] );
				@unlink( $save['path'] );
				break;
			}
			$qual -= 15;
			$w     = (int) ( $w * 0.8 );
		}
		@unlink( $tmp );
		return [ 'data' => $bin, 'mime' => $mime ];
	}

	/** Download remote image, upload via uploadBlob, return blob ref */
	private function wpb_upload_image_blob( $url, $jwt ) {

		$get  = wp_remote_get( $url, [ 'timeout' => 15 ] );
		if ( is_wp_error( $get ) ) {
			return $get;
		}
		$bin  = wp_remote_retrieve_body( $get );
		if ( ! $bin ) {
			return new WP_Error( 'wpb_dl', 'Download failed' );
		}

		$mime = wp_remote_retrieve_header( $get, 'content-type' );
		if ( ! $mime || ! preg_match( '#^image/(png|jpe?g|gif)$#i', $mime ) ) {
			$mime = 'image/jpeg';
		}

		$res  = $this->wpb_maybe_resize_image( $bin, $mime );
		$bin  = $res['data'];
		$mime = $res['mime'];

		$up = wp_remote_post(
			'https://bsky.social/xrpc/com.atproto.repo.uploadBlob',
			[
				'headers' => [
					'Content-Type'  => $mime,
					'Authorization' => 'Bearer ' . $jwt,
				],
				'body'    => $bin,
				'timeout' => 30,
			]
		);
		if ( is_wp_error( $up ) ) {
			return $up;
		}
		$j = json_decode( wp_remote_retrieve_body( $up ), true );
		return $j['blob'] ?? new WP_Error( 'wpb_blob', 'Upload failed' );
	}

	/* ========  CORE POST METHOD WITH FACETS & IMAGE  ======== */

	public function post_struct_to_bluesky( $post_struct, $test = false ) {

		$set = get_option( 'wpb_settings' );
		$un  = $set['wpb_bluesky_username'] ?? '';
		$pw  = $set['wpb_bluesky_password'] ?? get_option( 'wpb_bluesky_password' );
		if ( ! $un || ! $pw ) {
			return $test ? 'Missing credentials' : false;
		}

		// ---- 1.  login → JWT & DID ----
		$login = wp_remote_post(
			'https://bsky.social/xrpc/com.atproto.server.createSession',
			[
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( [ 'identifier' => $un, 'password' => $pw ] ),
				'timeout' => 15,
			]
		);
		if ( is_wp_error( $login ) ) {
			return $test ? $login->get_error_message() : false;
		}
		$L = json_decode( wp_remote_retrieve_body( $login ), true );
		if ( empty( $L['accessJwt'] ) ) {
			return $test ? 'Bluesky login failed' : false;
		}
		$jwt = $L['accessJwt'];
		$did = $L['did'];

		// ---- 2.  optional inline image ----
		$record = [
			'text'       => $post_struct['text'],
			'$type'      => 'app.bsky.feed.post',
			'createdAt'  => gmdate( 'c' ),
		];
		if ( ! empty( $post_struct['facets'] ) ) {
			$record['facets'] = $post_struct['facets'];
		}
		if ( ! empty( $post_struct['embed']['image_url'] ) ) {
			$blob = $this->wpb_upload_image_blob( $post_struct['embed']['image_url'], $jwt );
			if ( ! is_wp_error( $blob ) ) {
				$record['embed'] = [
					'$type'  => 'app.bsky.embed.images',
					'images' => [[
						'alt'   => $post_struct['embed']['alt'] ?? 'Image',
						'image' => $blob,
					]],
				];
			} else {
				error_log( '[WPB] blob upload failed: ' . $blob->get_error_message() );
			}
		}

		// ---- 3.  createRecord ----
		$res = wp_remote_post(
			'https://bsky.social/xrpc/com.atproto.repo.createRecord',
			[
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $jwt,
				],
				'body'    => wp_json_encode(
					[
						'repo'       => $did,
						'collection' => 'app.bsky.feed.post',
						'record'     => $record,
					]
				),
				'timeout' => 20,
			]
		);
		if ( is_wp_error( $res ) ) {
			return $test ? $res->get_error_message() : false;
		}
		$R = json_decode( wp_remote_retrieve_body( $res ), true );
		return ! empty( $R['uri'] ) ? ( $test ? true : null )
			: ( $test ? ( $R['error'] ?? 'Unknown Bluesky error' ) : false );
	}
}
// End of class WPB_Bluesky_Poster
// This class is used to post weather updates to Bluesky, handling image uploads and structured posts
// via the Bluesky API. 