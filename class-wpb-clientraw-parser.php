<?php
/**
 * Weather Poster Bluesky – clientraw.txt parser
 */
defined( 'ABSPATH' ) || exit;

class WPB_Clientraw_Parser {

	/**
	 * Parse a remote clientraw.txt into a tidy assoc array.
	 *
	 * @param string $url Full URL to clientraw.txt
	 * @return array|false
	 */
	public function parse( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		$raw = @file_get_contents( $url );
		if ( ! $raw ) {
			return false;
		}
		$parts = explode( ' ', trim( $raw ) );
		if ( count( $parts ) < 50 ) {
			return false;
		}

		return [
			/* core fields */
			'temp'        => (float) $parts[4],
			'wind_dir'    => (int)   $parts[3],
			'wind_speed'  => (float) $parts[1],
			'humidity'    => (int)   $parts[5],
			'pressure'    => (float) $parts[6],
			'rain'        => (float) $parts[7],
			'desc'        => $parts[49] ?? '',
		];
	}

	/** Convert degrees → compass (e.g. 225 → "SW") */
	public static function degrees_to_compass( $deg ) {
		$dirs = [ 'N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW' ];
		return $dirs[ ( (int) round( $deg / 22.5 ) ) % 16 ];
	}
}
// End of class WPB_Clientraw_Parser
// This class is used to parse the clientraw.txt file from a weather station