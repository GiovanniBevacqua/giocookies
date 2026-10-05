<?php
/**
 * Plugin bootstrap.
 *
 * @package GioCookies
 */

namespace GioCookies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin container.
 */
class Plugin {

	const CRON_HOOK = 'giocookies_purge_log';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Consent log storage.
	 *
	 * @var DB
	 */
	public $db;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Frontend consent manager.
	 *
	 * @var ConsentManager
	 */
	public $consent;

	/**
	 * Script blocker.
	 *
	 * @var ScriptBlocker
	 */
	public $blocker;

	/**
	 * Admin screens.
	 *
	 * @var Admin|null
	 */
	public $admin;

	/**
	 * Asset version based on the file modification time, so browsers refresh changed files.
	 *
	 * @param string $relative Path relative to the plugin folder.
	 * @return string
	 */
	public static function asset_version( $relative ) {
		$file = GIOCOOKIES_DIR . ltrim( $relative, '/' );
		return file_exists( $file ) ? GIOCOOKIES_VERSION . '.' . filemtime( $file ) : GIOCOOKIES_VERSION;
	}

	/**
	 * Get the instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire everything up.
	 */
	private function __construct() {
		$this->db       = new DB();
		$this->settings = new Settings();
		$this->consent  = new ConsentManager();
		$this->blocker  = new ScriptBlocker();

		if ( is_admin() ) {
			$this->admin = new Admin( $this->settings, $this->db );
		}

		// Schema upgrade on load: deployments without re-activation are covered too.
		$this->db->maybe_upgrade();

		add_action( 'init', array( __CLASS__, 'schedule_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'purge_log' ) );

		// WP Consent API: declare compliance (no-op when the API is not installed).
		add_filter( 'wp_consent_api_registered_' . plugin_basename( GIOCOOKIES_PLUGIN_FILE ), '__return_true' );
	}

	/**
	 * Ensure the daily purge is scheduled.
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback: delete log rows older than the retention period.
	 */
	public function purge_log() {
		$this->db->purge_older_than( (int) Settings::get( 'giocookies_log_retention' ) );
	}

	/**
	 * Activation: create the table, enable the banner on fresh installs, schedule cron.
	 */
	public static function activate() {
		$db = new DB();
		$db->maybe_upgrade();

		add_option( 'giocookies_enable', '1' );

		self::schedule_cron();
	}

	/**
	 * Deactivation: remove the cron event.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
