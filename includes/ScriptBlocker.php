<?php
/**
 * Blocks enqueued scripts until the visitor consents to their category.
 *
 * @package GioCookies
 */

namespace GioCookies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrites selected script tags to type="text/plain" data-giocookies-category="...".
 * The frontend script turns them back into executable scripts after consent.
 */
class ScriptBlocker {

	const CATEGORIES = array( 'analytics', 'marketing' );

	/**
	 * Handle => category map for this request.
	 *
	 * @var array<string,string>|null
	 */
	private $map = null;

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_filter( 'script_loader_tag', array( $this, 'filter_tag' ), 999, 2 );
	}

	/**
	 * Blocked handles (filterable).
	 *
	 * @return array<string,string>
	 */
	private function handles() {
		if ( null === $this->map ) {
			/**
			 * Map enqueued script handles to a consent category.
			 *
			 * Example: add_filter( 'giocookies_blocked_script_handles', function ( $handles ) {
			 *     $handles['my-analytics'] = 'analytics';
			 *     return $handles;
			 * } );
			 *
			 * @param array<string,string> $handles Handle => 'analytics'|'marketing'.
			 */
			$handles   = apply_filters( 'giocookies_blocked_script_handles', array() );
			$this->map = array();
			foreach ( (array) $handles as $handle => $category ) {
				if ( is_string( $handle ) && in_array( $category, self::CATEGORIES, true ) ) {
					$this->map[ $handle ] = $category;
				}
			}
		}
		return $this->map;
	}

	/**
	 * Rewrite the tag of a blocked handle (including its inline before/after scripts).
	 *
	 * The tag is always blocked, even when consent is already stored, so the
	 * output stays the same for every visitor and is safe for page caching.
	 *
	 * @param string $tag    Script HTML.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function filter_tag( $tag, $handle ) {
		if ( is_admin() || ! Settings::is_enabled() ) {
			return $tag;
		}
		$map = $this->handles();
		if ( empty( $map[ $handle ] ) ) {
			return $tag;
		}
		return self::block_markup( $tag, $map[ $handle ] );
	}

	/**
	 * Turn every executable <script> in the markup into a blocked one.
	 *
	 * @param string $html     Markup.
	 * @param string $category Consent category.
	 * @return string
	 */
	public static function block_markup( $html, $category ) {
		if ( ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $html;
		}

		$processor = new \WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag( array( 'tag_name' => 'script' ) ) ) {
			$type = $processor->get_attribute( 'type' );
			$type = is_string( $type ) ? strtolower( trim( $type ) ) : '';

			if ( '' !== $type && ! in_array( $type, array( 'text/javascript', 'application/javascript', 'module' ), true ) ) {
				continue; // Data blocks (JSON, import maps, templates) are left alone.
			}
			if ( 'module' === $type ) {
				$processor->set_attribute( 'data-giocookies-type', 'module' );
			}
			$processor->set_attribute( 'type', 'text/plain' );
			$processor->set_attribute( 'data-giocookies-category', $category );
		}
		return $processor->get_updated_html();
	}
}
