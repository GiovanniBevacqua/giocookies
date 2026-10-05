<?php
/**
 * Admin screens: menu, consent log, CSV export, privacy policy text.
 *
 * @package GioCookies
 */

namespace GioCookies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin controller.
 */
class Admin {

	const LOG_PAGE      = 'giocookies-log';
	const EXPORT_ACTION = 'giocookies_export_log';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Storage.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Admin page hook suffixes.
	 *
	 * @var string[]
	 */
	private $hooks = array();

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 * @param DB       $db       Storage.
	 */
	public function __construct( Settings $settings, DB $db ) {
		$this->settings = $settings;
		$this->db       = $db;

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'export_csv' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GIOCOOKIES_PLUGIN_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Register the menu (top-level "GioCookies" with Settings and Consent log).
	 */
	public function add_menu() {
		$this->hooks[] = add_menu_page(
			__( 'GioCookies Settings', 'giocookies' ),
			'GioCookies',
			'manage_options',
			Settings::PAGE,
			array( $this->settings, 'settings_page' ),
			'dashicons-privacy',
			60
		);

		$this->hooks[] = add_submenu_page(
			Settings::PAGE,
			__( 'GioCookies Settings', 'giocookies' ),
			__( 'Settings', 'giocookies' ),
			'manage_options',
			Settings::PAGE,
			array( $this->settings, 'settings_page' )
		);

		$this->hooks[] = add_submenu_page(
			Settings::PAGE,
			__( 'Consent log', 'giocookies' ),
			__( 'Consent log', 'giocookies' ),
			'manage_options',
			self::LOG_PAGE,
			array( $this, 'log_page' )
		);
	}

	/**
	 * Enqueue admin CSS/JS on the plugin screens only.
	 *
	 * @param string $hook_suffix Current screen.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, $this->hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'giocookies-admin', GIOCOOKIES_URL . 'assets/css/admin.css', array( 'wp-color-picker' ), Plugin::asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'giocookies-admin', GIOCOOKIES_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), Plugin::asset_version( 'assets/js/admin.js' ), true );
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=' . Settings::PAGE ) ), esc_html__( 'Settings', 'giocookies' ) )
		);
		return $links;
	}

	/**
	 * Current list filters from the request (read-only screen, no state change).
	 *
	 * @return array{search:string,decision:string}
	 */
	private function request_filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$decision = isset( $_REQUEST['decision'] ) ? sanitize_key( wp_unslash( $_REQUEST['decision'] ) ) : '';
		// phpcs:enable

		return array(
			'search'   => substr( preg_replace( '/[^a-fA-F0-9-]/', '', $search ), 0, 36 ),
			'decision' => in_array( $decision, ConsentManager::DECISIONS, true ) ? $decision : '',
		);
	}

	/**
	 * Consent log screen.
	 */
	public function log_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$table = new ConsentLogTable( $this->db, $this->request_filters() );
		$table->prepare_items();
		?>
		<div class="wrap giocookies-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Consent log', 'giocookies' ); ?></h1>
			<hr class="wp-header-end">
			<p class="description">
				<?php
				printf(
					/* translators: %d: retention in days. */
					esc_html__( 'IP addresses are anonymized before storage. Entries older than %d days are deleted automatically.', 'giocookies' ),
					(int) Settings::get( 'giocookies_log_retention' )
				);
				if ( '1' !== (string) Settings::get( 'giocookies_log_enabled' ) ) {
					echo ' <strong>' . esc_html__( 'Logging is currently disabled.', 'giocookies' ) . '</strong>';
				}
				?>
			</p>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::LOG_PAGE ); ?>" />
				<?php
				$table->search_box( __( 'Search consent ID', 'giocookies' ), 'giocookies-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Stream the (filtered) log as CSV.
	 */
	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to export the consent log.', 'giocookies' ), 403 );
		}
		check_admin_referer( self::EXPORT_ACTION );

		$filters = $this->request_filters();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=giocookies-consent-log-' . gmdate( 'Y-m-d' ) . '.csv' );

		self::csv_line( array( 'id', 'date', 'consent_id', 'decision', 'necessary', 'analytics', 'marketing', 'consent_version', 'ip_anonymized' ) );

		$chunk  = 1000;
		$offset = 0;
		do {
			$rows = $this->db->get_rows( $filters, $chunk, $offset, 'DESC' );
			foreach ( $rows as $row ) {
				$prefs = json_decode( (string) $row['cookie_preferences'], true );
				$prefs = is_array( $prefs ) ? $prefs : array();
				self::csv_line(
					array(
						$row['id'],
						$row['consent_date'],
						$row['consent_id'],
						$row['decision'],
						empty( $prefs['necessary'] ) ? 0 : 1,
						empty( $prefs['analytics'] ) ? 0 : 1,
						empty( $prefs['marketing'] ) ? 0 : 1,
						$row['consent_version'],
						$row['user_ip'],
					)
				);
			}
			$offset += $chunk;
		} while ( count( $rows ) === $chunk );

		exit;
	}

	/**
	 * Output one CSV line.
	 *
	 * @param array $cells Cells.
	 */
	private static function csv_line( $cells ) {
		echo implode( ',', array_map( array( __CLASS__, 'csv_cell' ), $cells ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, every cell is escaped by csv_cell().
	}

	/**
	 * Escape one CSV cell (quotes + spreadsheet formula injection).
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}
		return '"' . str_replace( '"', '""', $value ) . '"';
	}

	/**
	 * Suggested text for Settings > Privacy.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content  = '<h2>' . esc_html__( 'Cookie consent', 'giocookies' ) . '</h2>';
		$content .= '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text added by GioCookies. Adapt it to the cookies and services your site actually uses.', 'giocookies' ) . '</p>';
		$content .= '<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'giocookies' ) . '</strong> ';
		$content .= esc_html__( 'When you make a choice in our cookie banner, we store it in two cookies on your device: giocookies_consent (the type of choice: accepted, declined or custom) and giocookies_preferences (your choice for each category, a random consent ID and the version of the cookie policy you agreed to). These cookies are necessary to remember your choice and expire after 12 months. You can change or withdraw your consent at any time using the cookie preferences button.', 'giocookies' ) . '</p>';
		$content .= '<p>' . esc_html__( 'To be able to demonstrate consent, we may keep a record of each choice on our server: the random consent ID, the choices made, the policy version, the date and time, and your IP address with the last part removed (anonymized). We do not store your full IP address or link this record to your name or account. Records are deleted automatically after the retention period configured on this site.', 'giocookies' ) . '</p>';

		wp_add_privacy_policy_content( 'GioCookies', wp_kses_post( $content ) );
	}
}
