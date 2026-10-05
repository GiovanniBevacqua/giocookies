<?php
/**
 * Consent log storage.
 *
 * @package GioCookies
 */

namespace GioCookies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consent log table access.
 *
 * Direct queries are required here: the log lives in a custom table and is
 * never cached (rows are written once and read only in the admin).
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
 */
class DB {

	const SCHEMA_OPTION = 'giocookies_schema_version';

	/**
	 * Option written by pre-release builds (before the first public version).
	 */
	const LEGACY_OPTION = 'giocookies_db_version';

	/**
	 * Full table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Set the table name.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'giocookies';
	}

	/**
	 * Table name accessor.
	 *
	 * @return string
	 */
	public function table() {
		return $this->table_name;
	}

	/**
	 * Create or update the table when the stored schema version is older.
	 */
	public function maybe_upgrade() {
		$installed = (int) get_option( self::SCHEMA_OPTION, 0 );
		if ( $installed >= GIOCOOKIES_SCHEMA_VERSION ) {
			return;
		}

		$this->create_table();

		// Pre-release builds stored full IP addresses and two options that are no longer used.
		$legacy = (string) get_option( self::LEGACY_OPTION, '' );
		if ( '' !== $legacy && version_compare( $legacy, '1.2.0', '<' ) ) {
			$this->anonymize_legacy_ips();
			delete_option( 'giocookies_api_key' );
			delete_option( 'giocookies_show_decline' );
		}
		delete_option( self::LEGACY_OPTION );

		update_option( self::SCHEMA_OPTION, GIOCOOKIES_SCHEMA_VERSION );
	}

	/**
	 * Create or update the table with dbDelta (existing rows are kept).
	 */
	public function create_table() {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  consent_id varchar(36) NOT NULL DEFAULT '',
  user_ip varchar(45) NOT NULL DEFAULT '',
  consent_given tinyint(1) NOT NULL DEFAULT 0,
  decision varchar(20) NOT NULL DEFAULT '',
  cookie_preferences text,
  consent_version varchar(32) NOT NULL DEFAULT '',
  consent_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
  PRIMARY KEY  (id),
  KEY consent_id (consent_id),
  KEY consent_date (consent_date)
) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Rows written by pre-release builds contain full IP addresses: anonymize them.
	 */
	private function anonymize_legacy_ips() {
		global $wpdb;

		// IPv4: zero the last octet in SQL.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET user_ip = CONCAT(SUBSTRING_INDEX(user_ip, '.', 3), '.0') WHERE user_ip LIKE %s AND user_ip NOT LIKE %s",
				$this->table_name,
				'%.%.%.%',
				'%:%'
			)
		);

		// IPv6 (and IPv4-mapped IPv6): anonymize distinct values in PHP.
		$ips = $wpdb->get_col(
			$wpdb->prepare( 'SELECT DISTINCT user_ip FROM %i WHERE user_ip LIKE %s', $this->table_name, '%:%' )
		);
		foreach ( (array) $ips as $ip ) {
			$anon = self::anonymize_ip( $ip );
			if ( $anon !== $ip ) {
				$wpdb->update( $this->table_name, array( 'user_ip' => $anon ), array( 'user_ip' => $ip ), array( '%s' ), array( '%s' ) );
			}
		}
	}

	/**
	 * Anonymize an IP: IPv4 last octet zeroed, IPv6 last 80 bits zeroed.
	 *
	 * @param string $ip Raw IP.
	 * @return string
	 */
	public static function anonymize_ip( $ip ) {
		$ip = (string) $ip;
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		return (string) wp_privacy_anonymize_ip( $ip );
	}

	/**
	 * Insert a log row.
	 *
	 * @param array $data Row data (already validated).
	 * @return int|false Inserted rows or false.
	 */
	public function log_consent( $data ) {
		global $wpdb;

		return $wpdb->insert(
			$this->table_name,
			array(
				'consent_id'         => isset( $data['consent_id'] ) ? $data['consent_id'] : '',
				'user_ip'            => isset( $data['user_ip'] ) ? $data['user_ip'] : '',
				'consent_given'      => empty( $data['consent_given'] ) ? 0 : 1,
				'decision'           => isset( $data['decision'] ) ? $data['decision'] : '',
				'cookie_preferences' => isset( $data['cookie_preferences'] ) ? wp_json_encode( $data['cookie_preferences'] ) : '',
				'consent_version'    => isset( $data['consent_version'] ) ? $data['consent_version'] : '',
				'consent_date'       => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Delete rows older than N days.
	 *
	 * @param int $days Retention in days.
	 * @return int|false Deleted rows.
	 */
	public function purge_older_than( $days ) {
		global $wpdb;

		$days = absint( $days );
		if ( $days < 1 ) {
			return 0;
		}

		// Rows store site-local time (current_time( 'mysql' )), so compare in the site timezone.
		$cutoff = wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		return $wpdb->query(
			$wpdb->prepare( 'DELETE FROM %i WHERE consent_date < %s', $this->table_name, $cutoff )
		);
	}

	/**
	 * Normalize list/export filters into prepare() arguments.
	 *
	 * @param array $filters search, decision.
	 * @return array Arguments for the static WHERE clause.
	 */
	private function filter_args( $filters ) {
		global $wpdb;
		$decision = isset( $filters['decision'] ) ? (string) $filters['decision'] : '';
		$search   = isset( $filters['search'] ) ? (string) $filters['search'] : '';
		return array(
			$decision,
			$decision,
			$search,
			'%' . $wpdb->esc_like( $search ) . '%',
		);
	}

	/**
	 * Count rows.
	 *
	 * @param array $filters Filters.
	 * @return int
	 */
	public function count( $filters = array() ) {
		global $wpdb;
		list( $d1, $d2, $s1, $s2 ) = $this->filter_args( $filters );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE ( %s = '' OR decision = %s ) AND ( %s = '' OR consent_id LIKE %s )",
				$this->table_name,
				$d1,
				$d2,
				$s1,
				$s2
			)
		);
	}

	/**
	 * Fetch a page of rows.
	 *
	 * @param array  $filters Filters.
	 * @param int    $limit   Limit.
	 * @param int    $offset  Offset.
	 * @param string $order   ASC|DESC (by id).
	 * @return array
	 */
	public function get_rows( $filters = array(), $limit = 20, $offset = 0, $order = 'DESC' ) {
		global $wpdb;
		list( $d1, $d2, $s1, $s2 ) = $this->filter_args( $filters );
		$limit  = max( 1, (int) $limit );
		$offset = max( 0, (int) $offset );

		if ( 'ASC' === strtoupper( $order ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE ( %s = '' OR decision = %s ) AND ( %s = '' OR consent_id LIKE %s ) ORDER BY id ASC LIMIT %d OFFSET %d",
					$this->table_name,
					$d1,
					$d2,
					$s1,
					$s2,
					$limit,
					$offset
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE ( %s = '' OR decision = %s ) AND ( %s = '' OR consent_id LIKE %s ) ORDER BY id DESC LIMIT %d OFFSET %d",
					$this->table_name,
					$d1,
					$d2,
					$s1,
					$s2,
					$limit,
					$offset
				),
				ARRAY_A
			);
		}
		return (array) $rows;
	}

	/**
	 * Drop the table (uninstall).
	 */
	public function drop_table() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $this->table_name ) );
	}
}
