<?php
/**
 * Consent log list table.
 *
 * @package GioCookies
 */

namespace GioCookies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Paginated consent log.
 */
class ConsentLogTable extends \WP_List_Table {

	/**
	 * Storage.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Active filters.
	 *
	 * @var array
	 */
	private $filters;

	/**
	 * Constructor.
	 *
	 * @param DB    $db      Storage.
	 * @param array $filters search, decision.
	 */
	public function __construct( DB $db, array $filters ) {
		parent::__construct(
			array(
				'singular' => 'consent',
				'plural'   => 'consents',
				'ajax'     => false,
			)
		);
		$this->db      = $db;
		$this->filters = $filters;
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'consent_date'    => __( 'Date', 'giocookies' ),
			'consent_id'      => __( 'Consent ID', 'giocookies' ),
			'decision'        => __( 'Decision', 'giocookies' ),
			'analytics'       => __( 'Analytics', 'giocookies' ),
			'marketing'       => __( 'Marketing', 'giocookies' ),
			'consent_version' => __( 'Version', 'giocookies' ),
			'user_ip'         => __( 'IP (anonymized)', 'giocookies' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array( 'consent_date' => array( 'consent_date', true ) );
	}

	/**
	 * Load the current page.
	 */
	public function prepare_items() {
		$per_page = 20;
		$total    = $this->db->count( $this->filters );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sorting.
		$order = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'consent_date' );
		$this->items           = $this->db->get_rows( $this->filters, $per_page, ( $this->get_pagenum() - 1 ) * $per_page, $order );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		esc_html_e( 'No consent recorded yet.', 'giocookies' );
	}

	/**
	 * Decision filter and CSV export button.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$labels = self::decision_labels();
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="giocookies-filter-decision"><?php esc_html_e( 'Filter by decision', 'giocookies' ); ?></label>
			<select name="decision" id="giocookies-filter-decision">
				<option value=""><?php esc_html_e( 'All decisions', 'giocookies' ); ?></option>
				<?php foreach ( $labels as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $this->filters['decision'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'giocookies' ), '', 'filter_action', false ); ?>
			<?php
			$export_url = wp_nonce_url(
				add_query_arg(
					array_filter(
						array(
							'action'   => Admin::EXPORT_ACTION,
							's'        => $this->filters['search'],
							'decision' => $this->filters['decision'],
						)
					),
					admin_url( 'admin-post.php' )
				),
				Admin::EXPORT_ACTION
			);
			?>
			<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'giocookies' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Decision labels.
	 *
	 * @return array
	 */
	private static function decision_labels() {
		return array(
			'accepted' => __( 'Accepted all', 'giocookies' ),
			'declined' => __( 'Rejected all', 'giocookies' ),
			'custom'   => __( 'Custom', 'giocookies' ),
		);
	}

	/**
	 * Column output.
	 *
	 * @param array  $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		$prefs = json_decode( (string) $item['cookie_preferences'], true );
		$prefs = is_array( $prefs ) ? $prefs : array();

		switch ( $column_name ) {
			case 'decision':
				$labels = self::decision_labels();
				if ( isset( $labels[ $item['decision'] ] ) ) {
					return esc_html( $labels[ $item['decision'] ] );
				}
				// Rows written by pre-release builds have no decision type.
				return empty( $item['consent_given'] ) ? esc_html__( 'Rejected', 'giocookies' ) : esc_html__( 'Accepted', 'giocookies' );

			case 'analytics':
			case 'marketing':
				return empty( $prefs[ $column_name ] )
					? '<span aria-hidden="true">&#10005;</span><span class="screen-reader-text">' . esc_html__( 'No', 'giocookies' ) . '</span>'
					: '<span aria-hidden="true">&#10003;</span><span class="screen-reader-text">' . esc_html__( 'Yes', 'giocookies' ) . '</span>';

			case 'consent_id':
				return '' !== $item['consent_id'] ? '<code>' . esc_html( $item['consent_id'] ) . '</code>' : '&mdash;';

			case 'consent_version':
			case 'user_ip':
				return '' !== (string) $item[ $column_name ] ? esc_html( $item[ $column_name ] ) : '&mdash;';

			case 'consent_date':
				return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['consent_date'] ) );
		}
		return '';
	}
}
