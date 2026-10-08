<?php
/**
 * Settings: option registry, defaults, sanitization and the settings page.
 *
 * @package GioCookies
 */

namespace GioCookies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings API wrapper.
 */
class Settings {

	const GROUP = 'giocookies_settings';
	const PAGE  = 'giocookies-settings';

	const POSITIONS = array( 'bottom-left', 'bottom-right', 'bottom-bar' );

	/**
	 * Raw option defaults. Empty text values mean "use the translatable default".
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'giocookies_enable'           => '',
			'giocookies_title'            => '',
			'giocookies_message'          => '',
			'giocookies_label_reject'     => '',
			'giocookies_label_accept'     => '',
			'giocookies_label_save'       => '',
			'giocookies_label_customize'  => '',
			'giocookies_desc_necessary'   => '',
			'giocookies_desc_analytics'   => '',
			'giocookies_desc_marketing'   => '',
			'giocookies_label_necessary'  => '',
			'giocookies_label_analytics'  => '',
			'giocookies_label_marketing'  => '',
			'giocookies_cat_analytics'    => '1',
			'giocookies_cat_marketing'    => '1',
			'giocookies_privacy_page'     => 0,
			'giocookies_privacy_url'      => '',
			'giocookies_cookie_page'      => 0,
			'giocookies_cookie_url'       => '',
			'giocookies_position'         => 'bottom-left',
			'giocookies_show_bubble'      => '1',
			'giocookies_accent_color'     => '',
			'giocookies_accent_text'      => '',
			'giocookies_consent_version'  => '',
			'giocookies_log_enabled'      => '1',
			'giocookies_log_retention'    => 365,
			'giocookies_remove_data'      => '',
		);
	}

	/**
	 * Translatable fallbacks for the text options.
	 *
	 * @return array<string, string>
	 */
	public static function text_defaults() {
		return array(
			'giocookies_title'           => __( 'Cookie preferences', 'giocookies' ),
			'giocookies_message'         => __( 'We use cookies for analytics and marketing only if you allow it. Necessary cookies keep the website working. You can change your choice at any time.', 'giocookies' ),
			'giocookies_label_reject'    => __( 'Reject all', 'giocookies' ),
			'giocookies_label_accept'    => __( 'Accept all', 'giocookies' ),
			'giocookies_label_save'      => __( 'Save choices', 'giocookies' ),
			'giocookies_label_customize' => __( 'Customize', 'giocookies' ),
			'giocookies_label_necessary' => __( 'Necessary', 'giocookies' ),
			'giocookies_label_analytics' => __( 'Analytics', 'giocookies' ),
			'giocookies_label_marketing' => __( 'Marketing', 'giocookies' ),
			'giocookies_desc_necessary'  => __( 'Required for the website to work. Always active.', 'giocookies' ),
			'giocookies_desc_analytics'  => __( 'Help us understand how the website is used.', 'giocookies' ),
			'giocookies_desc_marketing'  => __( 'Used to measure and personalize advertising.', 'giocookies' ),
		);
	}

	/**
	 * Read an option with the plugin default.
	 *
	 * @param string $name Option name.
	 * @return mixed
	 */
	public static function get( $name ) {
		$defaults = self::defaults();
		return get_option( $name, isset( $defaults[ $name ] ) ? $defaults[ $name ] : '' );
	}

	/**
	 * Read a text option, falling back to its translatable default when empty.
	 *
	 * @param string $name Option name.
	 * @return string
	 */
	public static function text( $name ) {
		$value = (string) self::get( $name );
		if ( '' === trim( $value ) ) {
			$texts = self::text_defaults();
			$value = isset( $texts[ $name ] ) ? $texts[ $name ] : '';
		}

		/**
		 * Filter a banner text before output. Useful for multilingual setups
		 * that do not use WPML/Polylang string translation.
		 *
		 * @param string $value Text (saved value or translatable default).
		 * @param string $name  Option name, e.g. giocookies_message.
		 */
		return (string) apply_filters( 'giocookies_text', $value, $name );
	}

	/**
	 * Optional consent categories and whether each is enabled.
	 * "Necessary" is always shown and cannot be turned off.
	 *
	 * @return array{analytics:bool,marketing:bool}
	 */
	public static function categories() {
		return array(
			'analytics' => '1' === (string) self::get( 'giocookies_cat_analytics' ),
			'marketing' => '1' === (string) self::get( 'giocookies_cat_marketing' ),
		);
	}

	/**
	 * Whether the banner is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return '1' === (string) get_option( 'giocookies_enable' );
	}

	/**
	 * Resolve a policy link: selected page first, then custom URL.
	 *
	 * @param string $type 'privacy' or 'cookie'.
	 * @return string
	 */
	public static function policy_url( $type ) {
		$page_id = absint( self::get( "giocookies_{$type}_page" ) );
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			$link = get_permalink( $page_id );
			if ( $link ) {
				return $link;
			}
		}

		$url = (string) self::get( "giocookies_{$type}_url" );
		if ( '' === $url && 'privacy' === $type && function_exists( 'get_privacy_policy_url' ) ) {
			$url = get_privacy_policy_url();
		}
		return $url;
	}

	/**
	 * Current consent/policy version.
	 *
	 * @return string
	 */
	public static function consent_version() {
		return self::sanitize_version( self::get( 'giocookies_consent_version' ) );
	}

	/**
	 * Allowed HTML for the banner message.
	 *
	 * @return array
	 */
	public static function message_allowed_html() {
		return array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'strong' => array(),
			'em'     => array(),
			'br'     => array(),
		);
	}

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register options, sections and fields.
	 */
	public function register_settings() {
		$checkbox = array( $this, 'sanitize_checkbox' );
		$text     = 'sanitize_text_field';
		$textarea = 'sanitize_textarea_field';

		$options = array(
			'giocookies_enable'          => array( 'string', $checkbox ),
			'giocookies_consent_version' => array( 'string', array( __CLASS__, 'sanitize_version' ) ),
			'giocookies_title'           => array( 'string', $text ),
			'giocookies_message'         => array( 'string', array( $this, 'sanitize_message' ) ),
			'giocookies_label_reject'    => array( 'string', $text ),
			'giocookies_label_accept'    => array( 'string', $text ),
			'giocookies_label_save'      => array( 'string', $text ),
			'giocookies_label_customize' => array( 'string', $text ),
			'giocookies_desc_necessary'  => array( 'string', $textarea ),
			'giocookies_desc_analytics'  => array( 'string', $textarea ),
			'giocookies_desc_marketing'  => array( 'string', $textarea ),
			'giocookies_label_necessary' => array( 'string', $text ),
			'giocookies_label_analytics' => array( 'string', $text ),
			'giocookies_label_marketing' => array( 'string', $text ),
			'giocookies_cat_analytics'   => array( 'string', $checkbox ),
			'giocookies_cat_marketing'   => array( 'string', $checkbox ),
			'giocookies_privacy_page'    => array( 'integer', 'absint' ),
			'giocookies_privacy_url'     => array( 'string', 'esc_url_raw' ),
			'giocookies_cookie_page'     => array( 'integer', 'absint' ),
			'giocookies_cookie_url'      => array( 'string', 'esc_url_raw' ),
			'giocookies_position'        => array( 'string', array( $this, 'sanitize_position' ) ),
			'giocookies_show_bubble'     => array( 'string', $checkbox ),
			'giocookies_accent_color'    => array( 'string', array( $this, 'sanitize_color' ) ),
			'giocookies_accent_text'     => array( 'string', array( $this, 'sanitize_color' ) ),
			'giocookies_log_enabled'     => array( 'string', $checkbox ),
			'giocookies_log_retention'   => array( 'integer', array( $this, 'sanitize_retention' ) ),
			'giocookies_remove_data'     => array( 'string', $checkbox ),
		);

		$defaults = self::defaults();
		foreach ( $options as $name => $args ) {
			register_setting(
				self::GROUP,
				$name,
				array(
					'type'              => $args[0],
					'sanitize_callback' => $args[1],
					'default'           => $defaults[ $name ],
					'show_in_rest'      => false,
				)
			);
		}

	}

	/**
	 * Settings page tabs.
	 *
	 * @return array<string, string> Slug => label.
	 */
	public static function tabs() {
		return array(
			'overview'   => __( 'Overview', 'giocookies' ),
			'banner'     => __( 'Banner', 'giocookies' ),
			'categories' => __( 'Categories', 'giocookies' ),
			'appearance' => __( 'Appearance', 'giocookies' ),
			'privacy'    => __( 'Privacy', 'giocookies' ),
			'advanced'   => __( 'Advanced', 'giocookies' ),
		);
	}

	/**
	 * Tabs that show the live banner preview next to the fields.
	 *
	 * @var string[]
	 */
	const PREVIEW_TABS = array( 'banner', 'categories', 'appearance' );

	/**
	 * Tab requested in the URL (falls back to the overview).
	 *
	 * @return string
	 */
	private static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		return array_key_exists( $tab, self::tabs() ) ? $tab : 'overview';
	}

	/**
	 * URL of a settings tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function tab_url( $tab ) {
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Settings page markup: one form, one panel per tab.
	 * Without JavaScript every panel is shown, one after the other.
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current = self::current_tab();
		?>
		<div class="wrap giocookies-admin" data-current-tab="<?php echo esc_attr( $current ); ?>">
			<h1 class="giocookies-admin__title"><?php esc_html_e( 'GioCookies', 'giocookies' ); ?></h1>
			<?php settings_errors(); ?>

			<nav class="nav-tab-wrapper giocookies-tabs" role="tablist" aria-label="<?php esc_attr_e( 'GioCookies settings', 'giocookies' ); ?>">
				<?php foreach ( self::tabs() as $slug => $label ) : ?>
					<a href="<?php echo esc_url( self::tab_url( $slug ) ); ?>" id="giocookies-tab-<?php echo esc_attr( $slug ); ?>" class="nav-tab<?php echo $slug === $current ? ' nav-tab-active' : ''; ?>" role="tab" aria-controls="giocookies-panel-<?php echo esc_attr( $slug ); ?>" aria-selected="<?php echo $slug === $current ? 'true' : 'false'; ?>" data-tab="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php" class="giocookies-form">
				<?php settings_fields( self::GROUP ); ?>
				<div class="giocookies-layout<?php echo in_array( $current, self::PREVIEW_TABS, true ) ? ' has-preview' : ''; ?>">
					<div class="giocookies-panels">
						<?php
						foreach ( array_keys( self::tabs() ) as $slug ) {
							printf(
								'<section id="giocookies-panel-%1$s" class="giocookies-panel" role="tabpanel" aria-labelledby="giocookies-tab-%1$s" data-panel="%1$s" data-preview="%2$s">',
								esc_attr( $slug ),
								in_array( $slug, self::PREVIEW_TABS, true ) ? '1' : '0'
							);
							printf( '<h2 class="giocookies-panel__heading">%s</h2>', esc_html( self::tabs()[ $slug ] ) );
							call_user_func( array( $this, 'panel_' . $slug ) );
							echo '</section>';
						}
						?>
						<div class="giocookies-savebar">
							<?php submit_button( __( 'Save changes', 'giocookies' ), 'primary', 'submit', false ); ?>
						</div>
					</div>
					<?php $this->render_preview( $current ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Overview: banner switch, decisions of the last 30 days and setup checklist.
	 */
	private function panel_overview() {
		$enabled = self::is_enabled();
		?>
		<div class="giocookies-grid giocookies-grid--2">
			<div class="giocookies-card">
				<div class="giocookies-card__head">
					<h3><?php esc_html_e( 'Cookie banner', 'giocookies' ); ?></h3>
					<span class="giocookies-pill <?php echo $enabled ? 'is-on' : 'is-off'; ?>"><?php echo $enabled ? esc_html__( 'Active', 'giocookies' ) : esc_html__( 'Off', 'giocookies' ); ?></span>
				</div>
				<?php
				$this->field(
					'giocookies_enable',
					'',
					'checkbox',
					array( 'label' => __( 'Show the cookie banner and print the Google Consent Mode v2 defaults on the frontend.', 'giocookies' ) )
				);
				?>
				<p class="description"><?php esc_html_e( 'When visitors decide, GioCookies updates Consent Mode and sends a cookie_consent_update event to the dataLayer.', 'giocookies' ); ?></p>
			</div>

			<div class="giocookies-card">
				<div class="giocookies-card__head">
					<h3><?php esc_html_e( 'Last 30 days', 'giocookies' ); ?></h3>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin::LOG_PAGE ) ); ?>"><?php esc_html_e( 'Consent log', 'giocookies' ); ?></a>
				</div>
				<?php $this->render_stats(); ?>
			</div>
		</div>

		<div class="giocookies-card">
			<h3><?php esc_html_e( 'Setup checklist', 'giocookies' ); ?></h3>
			<ul class="giocookies-checklist">
				<?php
				foreach ( $this->checklist() as $item ) {
					printf(
						'<li class="%1$s"><span class="dashicons %2$s" aria-hidden="true"></span><span><strong>%3$s</strong> %4$s</span>%5$s</li>',
						$item['ok'] ? 'is-ok' : 'is-todo',
						$item['ok'] ? 'dashicons-yes-alt' : 'dashicons-marker',
						esc_html( $item['label'] ),
						esc_html( $item['text'] ),
						$item['tab'] ? sprintf( ' <a href="%1$s" data-tab-link="%2$s">%3$s</a>', esc_url( self::tab_url( $item['tab'] ) ), esc_attr( $item['tab'] ), esc_html__( 'Edit', 'giocookies' ) ) : ''
					);
				}
				?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Checklist items for the overview.
	 *
	 * @return array<int, array{label:string,text:string,ok:bool,tab:string}>
	 */
	private function checklist() {
		$enabled     = self::is_enabled();
		$privacy     = self::policy_url( 'privacy' );
		$cookie      = self::policy_url( 'cookie' );
		$log         = '1' === (string) self::get( 'giocookies_log_enabled' );
		$consent_api = function_exists( 'wp_has_consent' ) || class_exists( 'WP_CONSENT_API' );

		return array(
			array(
				'label' => __( 'Banner:', 'giocookies' ),
				'text'  => $enabled ? __( 'shown to visitors.', 'giocookies' ) : __( 'turned off. Turn it on above when the texts and links are ready.', 'giocookies' ),
				'ok'    => $enabled,
				'tab'   => '',
			),
			array(
				'label' => __( 'Privacy policy link:', 'giocookies' ),
				'text'  => $privacy ? $privacy : __( 'not set.', 'giocookies' ),
				'ok'    => '' !== $privacy,
				'tab'   => 'privacy',
			),
			array(
				'label' => __( 'Cookie policy link:', 'giocookies' ),
				'text'  => $cookie ? $cookie : __( 'not set.', 'giocookies' ),
				'ok'    => '' !== $cookie,
				'tab'   => 'privacy',
			),
			array(
				'label' => __( 'Consent log:', 'giocookies' ),
				/* translators: %d: number of days */
				'text'  => $log ? sprintf( _n( 'on, entries kept for %d day.', 'on, entries kept for %d days.', (int) self::get( 'giocookies_log_retention' ), 'giocookies' ), (int) self::get( 'giocookies_log_retention' ) ) : __( 'off.', 'giocookies' ),
				'ok'    => $log,
				'tab'   => 'privacy',
			),
			array(
				'label' => __( 'Google Consent Mode v2:', 'giocookies' ),
				'text'  => $enabled ? __( 'defaults printed at the top of the page head.', 'giocookies' ) : __( 'inactive while the banner is off.', 'giocookies' ),
				'ok'    => $enabled,
				'tab'   => 'advanced',
			),
			array(
				'label' => __( 'WP Consent API:', 'giocookies' ),
				'text'  => $consent_api ? __( 'detected. Choices are shared with plugins that support it.', 'giocookies' ) : __( 'not installed (optional).', 'giocookies' ),
				'ok'    => $consent_api,
				'tab'   => '',
			),
		);
	}

	/**
	 * Decisions of the last 30 days from the consent log.
	 */
	private function render_stats() {
		if ( '1' !== (string) self::get( 'giocookies_log_enabled' ) ) {
			echo '<p class="giocookies-empty">' . esc_html__( 'The consent log is off: turn it on in the Privacy tab to see how visitors decide.', 'giocookies' ) . '</p>';
			return;
		}

		$counts = Plugin::instance()->db->decision_counts( 30 );
		$total  = array_sum( $counts );
		if ( 0 === $total ) {
			echo '<p class="giocookies-empty">' . esc_html__( 'No decisions recorded yet.', 'giocookies' ) . '</p>';
			return;
		}

		$labels = array(
			'accepted' => __( 'Accepted all', 'giocookies' ),
			'declined' => __( 'Rejected all', 'giocookies' ),
			'custom'   => __( 'Custom choice', 'giocookies' ),
		);
		/* translators: %s: number of decisions */
		printf( '<p class="giocookies-stats__total">%s</p>', esc_html( sprintf( _n( '%s decision', '%s decisions', $total, 'giocookies' ), number_format_i18n( $total ) ) ) );
		echo '<ul class="giocookies-stats">';
		foreach ( $labels as $key => $label ) {
			$count   = isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0;
			$percent = (int) round( $count / $total * 100 );
			printf(
				'<li class="giocookies-stats__row is-%1$s"><span class="giocookies-stats__label">%2$s</span><span class="giocookies-stats__bar" aria-hidden="true"><span style="width:%3$d%%"></span></span><span class="giocookies-stats__value">%3$d%% <small>(%4$s)</small></span></li>',
				esc_attr( $key ),
				esc_html( $label ),
				(int) $percent,
				esc_html( number_format_i18n( $count ) )
			);
		}
		echo '</ul>';
	}

	/**
	 * Banner texts.
	 */
	private function panel_banner() {
		$texts = self::text_defaults();
		?>
		<p class="giocookies-panel__intro"><?php esc_html_e( 'Leave a field empty to use the default text shown in grey: it follows the site language and is translated automatically.', 'giocookies' ); ?></p>
		<div class="giocookies-card">
			<h3><?php esc_html_e( 'Message', 'giocookies' ); ?></h3>
			<?php
			$this->field( 'giocookies_title', __( 'Title', 'giocookies' ), 'text', array( 'placeholder' => $texts['giocookies_title'] ) );
			$this->field(
				'giocookies_message',
				__( 'Text', 'giocookies' ),
				'textarea',
				array(
					'placeholder' => $texts['giocookies_message'],
					'rows'        => 4,
					'description' => __( 'Allowed HTML: a, strong, em, br.', 'giocookies' ),
				)
			);
			?>
		</div>
		<div class="giocookies-card">
			<h3><?php esc_html_e( 'Buttons', 'giocookies' ); ?></h3>
			<div class="giocookies-grid giocookies-grid--2">
				<?php
				$this->field( 'giocookies_label_reject', __( 'Reject all', 'giocookies' ), 'text', array( 'placeholder' => $texts['giocookies_label_reject'] ) );
				$this->field( 'giocookies_label_accept', __( 'Accept all', 'giocookies' ), 'text', array( 'placeholder' => $texts['giocookies_label_accept'] ) );
				$this->field( 'giocookies_label_customize', __( 'Customize', 'giocookies' ), 'text', array( 'placeholder' => $texts['giocookies_label_customize'] ) );
				$this->field( 'giocookies_label_save', __( 'Save choices', 'giocookies' ), 'text', array( 'placeholder' => $texts['giocookies_label_save'] ) );
				?>
			</div>
			<p class="description"><?php esc_html_e( '"Reject all" and "Accept all" always have the same size and weight, as required by EU consent guidelines.', 'giocookies' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Categories: switch, name and description together for each category.
	 */
	private function panel_categories() {
		$texts      = self::text_defaults();
		$categories = array(
			'necessary' => array( '', __( 'Always active: these cookies keep the site working and do not need consent.', 'giocookies' ), '' ),
			'analytics' => array( 'giocookies_cat_analytics', __( 'Consent Mode: analytics_storage.', 'giocookies' ), __( 'Ask consent for analytics cookies', 'giocookies' ) ),
			'marketing' => array( 'giocookies_cat_marketing', __( 'Consent Mode: ad_storage, ad_user_data, ad_personalization.', 'giocookies' ), __( 'Ask consent for marketing cookies', 'giocookies' ) ),
		);
		?>
		<p class="giocookies-panel__intro"><?php esc_html_e( 'Turn off the categories your site does not use: they disappear from the banner and stay denied in Consent Mode. "Accept all" only grants the categories that are on.', 'giocookies' ); ?></p>
		<?php foreach ( $categories as $key => $category ) : ?>
			<?php
			$switch = $category[0];
			$on     = '' === $switch || '1' === (string) self::get( $switch );
			?>
			<div class="giocookies-card giocookies-category<?php echo $on ? '' : ' is-disabled'; ?>" data-category="<?php echo esc_attr( $key ); ?>">
				<div class="giocookies-card__head">
					<h3><?php echo esc_html( $texts[ 'giocookies_label_' . $key ] ); ?></h3>
					<?php if ( $switch ) : ?>
						<?php
						$this->field(
							$switch,
							'',
							'checkbox',
							array(
								'label'    => $category[2],
								'compact'  => true,
								'category' => $key,
							)
						);
						?>
					<?php else : ?>
						<span class="giocookies-pill is-on"><?php esc_html_e( 'Always active', 'giocookies' ); ?></span>
					<?php endif; ?>
				</div>
				<p class="description"><?php echo esc_html( $category[1] ); ?></p>
				<div class="giocookies-category__fields">
					<?php
					$this->field( 'giocookies_label_' . $key, __( 'Name in the banner', 'giocookies' ), 'text', array( 'placeholder' => $texts[ 'giocookies_label_' . $key ] ) );
					$this->field(
						'giocookies_desc_' . $key,
						__( 'Description', 'giocookies' ),
						'textarea',
						array(
							'placeholder' => $texts[ 'giocookies_desc_' . $key ],
							'rows'        => 2,
						)
					);
					?>
				</div>
			</div>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Appearance: position, floating button and colors.
	 */
	private function panel_appearance() {
		$position  = self::get( 'giocookies_position' );
		$positions = array(
			'bottom-left'  => __( 'Card, bottom left', 'giocookies' ),
			'bottom-right' => __( 'Card, bottom right', 'giocookies' ),
			'bottom-bar'   => __( 'Bar, full width', 'giocookies' ),
		);
		?>
		<div class="giocookies-card">
			<fieldset>
				<legend><h3><?php esc_html_e( 'Position', 'giocookies' ); ?></h3></legend>
				<div class="giocookies-positions">
					<?php foreach ( $positions as $key => $label ) : ?>
						<label class="giocookies-position">
							<input type="radio" name="giocookies_position" value="<?php echo esc_attr( $key ); ?>" <?php checked( $position, $key ); ?> data-gc-position />
							<span class="giocookies-position__thumb is-<?php echo esc_attr( $key ); ?>" aria-hidden="true"><span></span></span>
							<span class="giocookies-position__label"><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		</div>

		<div class="giocookies-card">
			<h3><?php esc_html_e( 'Floating button', 'giocookies' ); ?></h3>
			<?php
			$this->field(
				'giocookies_show_bubble',
				'',
				'checkbox',
				array(
					'label'       => __( 'Show the floating cookie button that reopens the preferences.', 'giocookies' ),
					'description' => __( 'If you hide it, give visitors another way to change their choice: the [giocookies_preferences] shortcode or any element with the data-giocookies-open attribute.', 'giocookies' ),
				)
			);
			?>
		</div>

		<div class="giocookies-card">
			<h3><?php esc_html_e( 'Colors', 'giocookies' ); ?></h3>
			<div class="giocookies-grid giocookies-grid--2">
				<?php
				$this->field( 'giocookies_accent_color', __( 'Accent', 'giocookies' ), 'color', array( 'description' => __( 'Buttons, switches and focus ring.', 'giocookies' ) ) );
				$this->field( 'giocookies_accent_text', __( 'Button text', 'giocookies' ), 'color', array( 'description' => __( 'Text on the accent buttons.', 'giocookies' ) ) );
				?>
			</div>
			<p class="description"><?php esc_html_e( 'Leave empty to use the plugin or theme colors. Themes can also style the banner with the --giocookies-* CSS custom properties.', 'giocookies' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Privacy: policy links, consent version and consent log.
	 */
	private function panel_privacy() {
		?>
		<div class="giocookies-card">
			<h3><?php esc_html_e( 'Policy links', 'giocookies' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Shown at the bottom of the banner. Choose a page, or leave "Custom URL" selected and enter an address: a selected page takes precedence.', 'giocookies' ); ?></p>
			<?php
			$this->field( 'giocookies_privacy_page', __( 'Privacy policy', 'giocookies' ), 'page_or_url', array( 'url_option' => 'giocookies_privacy_url' ) );
			$this->field( 'giocookies_cookie_page', __( 'Cookie policy', 'giocookies' ), 'page_or_url', array( 'url_option' => 'giocookies_cookie_url' ) );
			?>
		</div>

		<div class="giocookies-card">
			<h3><?php esc_html_e( 'Consent version', 'giocookies' ); ?></h3>
			<?php
			$this->field(
				'giocookies_consent_version',
				__( 'Version', 'giocookies' ),
				'text',
				array(
					'class'       => 'small-text giocookies-version',
					'description' => __( 'Optional. Change this value (for example from 1 to 2) when your cookie policy changes: visitors whose stored choice has a different version will see the banner again. Letters, numbers, dots, dashes and underscores only.', 'giocookies' ),
				)
			);
			?>
		</div>

		<div class="giocookies-card">
			<div class="giocookies-card__head">
				<h3><?php esc_html_e( 'Consent log', 'giocookies' ); ?></h3>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin::LOG_PAGE ) ); ?>"><?php esc_html_e( 'View the consent log', 'giocookies' ); ?></a>
			</div>
			<?php
			$this->field( 'giocookies_log_enabled', '', 'checkbox', array( 'label' => __( 'Store each choice: anonymized IP, consent ID, choices, version and date. The full IP address is never stored.', 'giocookies' ) ) );
			$this->field(
				'giocookies_log_retention',
				__( 'Keep entries for (days)', 'giocookies' ),
				'number',
				array(
					'min'         => 1,
					'max'         => 3650,
					'description' => __( 'Older entries are deleted automatically once a day.', 'giocookies' ),
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * Advanced: integration notes and uninstall.
	 */
	private function panel_advanced() {
		?>
		<div class="giocookies-card">
			<h3><?php esc_html_e( 'Google Tag Manager', 'giocookies' ); ?></h3>
			<p><?php esc_html_e( 'Keep the GTM snippet after wp_head(): GioCookies prints the Consent Mode defaults earlier, at the very top of the head. Then choose one of these setups in GTM:', 'giocookies' ); ?></p>
			<ol class="giocookies-steps">
				<li><?php esc_html_e( 'In each tag, open Advanced Settings → Consent Settings and require analytics_storage for analytics tags and ad_storage for advertising tags (Meta Pixel, Google Ads, LinkedIn…).', 'giocookies' ); ?></li>
				<li><?php esc_html_e( 'Or fire tags on the custom event cookie_consent_update with a condition on the Data Layer variable cookie_consent.analytics or cookie_consent.marketing equals true.', 'giocookies' ); ?></li>
			</ol>
			<p class="description"><?php esc_html_e( 'Tags that do not check consent fire even when visitors reject: the banner cannot block them on its own.', 'giocookies' ); ?></p>
		</div>

		<div class="giocookies-card">
			<h3><?php esc_html_e( 'Blocking scripts without GTM', 'giocookies' ); ?></h3>
			<p><?php esc_html_e( 'Hard-coded scripts: change the type and add the category. GioCookies runs them after consent.', 'giocookies' ); ?></p>
			<pre class="giocookies-code"><code>&lt;script type="text/plain" data-giocookies-category="marketing" src="https://example.com/pixel.js"&gt;&lt;/script&gt;</code></pre>
			<p><?php esc_html_e( 'Enqueued scripts: map their handles in your theme or plugin.', 'giocookies' ); ?></p>
			<pre class="giocookies-code"><code>add_filter( 'giocookies_blocked_script_handles', function ( $handles ) {
	$handles['my-analytics'] = 'analytics';
	return $handles;
} );</code></pre>
		</div>

		<div class="giocookies-card">
			<h3><?php esc_html_e( 'Reopening the banner', 'giocookies' ); ?></h3>
			<ul class="giocookies-list">
				<li><?php esc_html_e( 'Shortcode:', 'giocookies' ); ?> <code>[giocookies_preferences label="Cookie settings"]</code></li>
				<li><?php esc_html_e( 'Any element with the attribute:', 'giocookies' ); ?> <code>data-giocookies-open</code></li>
				<li><?php esc_html_e( 'JavaScript:', 'giocookies' ); ?> <code>window.GioCookies.open()</code></li>
			</ul>
		</div>

		<div class="giocookies-card">
			<h3><?php esc_html_e( 'Uninstall', 'giocookies' ); ?></h3>
			<?php $this->field( 'giocookies_remove_data', '', 'checkbox', array( 'label' => __( 'Delete all GioCookies settings and the consent log when the plugin is deleted.', 'giocookies' ) ) ); ?>
		</div>
		<?php
	}

	/**
	 * Live preview of the banner, built with the same classes and stylesheet as the frontend.
	 *
	 * @param string $current Current tab.
	 */
	private function render_preview( $current ) {
		$position   = self::get( 'giocookies_position' );
		$position   = in_array( $position, self::POSITIONS, true ) ? $position : 'bottom-left';
		$categories = self::categories();
		$accent     = (string) self::get( 'giocookies_accent_color' );
		$text       = (string) self::get( 'giocookies_accent_text' );
		$style      = '';
		if ( $accent ) {
			$style .= '--giocookies-accent:' . $accent . ';--giocookies-accent-hover:' . $accent . ';';
		}
		if ( $text ) {
			$style .= '--giocookies-accent-text:' . $text . ';';
		}
		?>
		<aside class="giocookies-preview" aria-label="<?php esc_attr_e( 'Banner preview', 'giocookies' ); ?>">
			<p class="giocookies-preview__title"><?php esc_html_e( 'Preview', 'giocookies' ); ?></p>
			<div class="giocookies-preview__frame is-<?php echo esc_attr( $position ); ?><?php echo 'categories' === $current ? ' is-customizing' : ''; ?>" inert>
				<div class="giocookies-preview__page" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
				<div class="giocookies-banner is-visible giocookies-preview__banner" style="<?php echo esc_attr( $style ); ?>">
					<p class="giocookies-title" data-gc-out="giocookies_title"><?php echo esc_html( self::text( 'giocookies_title' ) ); ?></p>
					<p class="giocookies-message" data-gc-out="giocookies_message"><?php echo wp_kses( self::text( 'giocookies_message' ), self::message_allowed_html() ); ?></p>
					<div class="giocookies-preferences">
						<?php foreach ( array( 'necessary', 'analytics', 'marketing' ) as $key ) : ?>
							<div class="giocookies-option" data-gc-category-row="<?php echo esc_attr( $key ); ?>"<?php echo ( 'necessary' !== $key && ! $categories[ $key ] ) ? ' hidden' : ''; ?>>
								<span class="giocookies-option__text">
									<strong data-gc-out="giocookies_label_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( self::text( 'giocookies_label_' . $key ) ); ?></strong>
									<span data-gc-out="giocookies_desc_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( self::text( 'giocookies_desc_' . $key ) ); ?></span>
								</span>
								<span class="giocookies-switch<?php echo 'necessary' === $key ? ' is-checked' : ''; ?>"></span>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="giocookies-actions">
						<span class="giocookies-btn" data-gc-out="giocookies_label_reject"><?php echo esc_html( self::text( 'giocookies_label_reject' ) ); ?></span>
						<span class="giocookies-btn" data-gc-out="giocookies_label_accept"><?php echo esc_html( self::text( 'giocookies_label_accept' ) ); ?></span>
						<span class="giocookies-btn giocookies-btn--outline giocookies-preview__save" data-gc-out="giocookies_label_save"><?php echo esc_html( self::text( 'giocookies_label_save' ) ); ?></span>
						<span class="giocookies-btn giocookies-btn--link giocookies-preview__customize" data-gc-out="giocookies_label_customize"><?php echo esc_html( self::text( 'giocookies_label_customize' ) ); ?></span>
					</div>
					<p class="giocookies-links"><span><?php esc_html_e( 'Privacy Policy', 'giocookies' ); ?></span> <span><?php esc_html_e( 'Cookie Policy', 'giocookies' ); ?></span></p>
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'Updates as you type. Your theme may change fonts and colors on the site.', 'giocookies' ); ?></p>
		</aside>
		<?php
	}

	/**
	 * One labelled field.
	 *
	 * @param string $name  Option name.
	 * @param string $label Visible label (empty for switches, which carry their own label).
	 * @param string $type  Field type.
	 * @param array  $args  Extra args.
	 */
	private function field( $name, $label, $type, $args = array() ) {
		$args['name'] = $name;
		$args['type'] = $type;
		$classes      = 'giocookies-field giocookies-field--' . $type . ( ! empty( $args['compact'] ) ? ' is-compact' : '' );

		echo '<div class="' . esc_attr( $classes ) . '">';
		if ( '' !== $label ) {
			if ( 'page_or_url' === $type ) {
				printf( '<span class="giocookies-field__label">%s</span>', esc_html( $label ) );
			} else {
				printf( '<label class="giocookies-field__label" for="%1$s">%2$s</label>', esc_attr( $name ), esc_html( $label ) );
			}
		}
		echo '<div class="giocookies-field__control">';
		$this->render_field( $args );
		echo '</div></div>';
	}

	/**
	 * Field control renderer.
	 *
	 * @param array $args Field args.
	 */
	public function render_field( $args ) {
		$name    = $args['name'];
		$value   = self::get( $name );
		$is_text = in_array( $args['type'], array( 'text', 'textarea' ), true ) && ! empty( $args['placeholder'] );

		switch ( $args['type'] ) {
			case 'checkbox':
				printf(
					'<label class="giocookies-toggle" for="%1$s"><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s%4$s /><span class="giocookies-toggle__track" aria-hidden="true"></span><span class="giocookies-toggle__label">%3$s</span></label>',
					esc_attr( $name ),
					checked( (string) $value, '1', false ),
					esc_html( isset( $args['label'] ) ? $args['label'] : '' ),
					! empty( $args['category'] ) ? ' data-gc-category="' . esc_attr( $args['category'] ) . '"' : ''
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%1$s" class="large-text" rows="%2$d" placeholder="%3$s" data-gc-in="%1$s">%4$s</textarea>',
					esc_attr( $name ),
					isset( $args['rows'] ) ? (int) $args['rows'] : 3,
					esc_attr( isset( $args['placeholder'] ) ? $args['placeholder'] : '' ),
					esc_textarea( (string) $value )
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%1$s" name="%1$s" class="small-text" min="%2$d" max="%3$d" step="1" value="%4$d" />',
					esc_attr( $name ),
					(int) $args['min'],
					(int) $args['max'],
					(int) $value
				);
				break;

			case 'color':
				printf(
					'<input type="text" id="%1$s" name="%1$s" class="giocookies-color" value="%2$s" maxlength="7" placeholder="#RRGGBB" data-gc-color="%1$s" />',
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'page_or_url':
				$url_option = $args['url_option'];
				echo '<div class="giocookies-page-or-url">';
				printf( '<label class="screen-reader-text" for="%1$s">%2$s</label>', esc_attr( $name ), esc_html__( 'Page', 'giocookies' ) );
				wp_dropdown_pages(
					array(
						'name'              => esc_attr( $name ),
						'id'                => esc_attr( $name ),
						'selected'          => absint( $value ),
						'show_option_none'  => esc_html__( '— Custom URL —', 'giocookies' ),
						'option_none_value' => '0',
						'post_status'       => array( 'publish' ),
					)
				);
				printf(
					'<label class="screen-reader-text" for="%1$s">%2$s</label><input type="url" id="%1$s" name="%1$s" class="regular-text" value="%3$s" placeholder="https://" />',
					esc_attr( $url_option ),
					esc_html__( 'Custom URL', 'giocookies' ),
					esc_attr( (string) self::get( $url_option ) )
				);
				echo '</div>';
				break;

			default:
				printf(
					'<input type="text" id="%1$s" name="%1$s" class="%2$s" value="%3$s" placeholder="%4$s" data-gc-in="%1$s" />',
					esc_attr( $name ),
					esc_attr( isset( $args['class'] ) ? $args['class'] : 'regular-text' ),
					esc_attr( (string) $value ),
					esc_attr( isset( $args['placeholder'] ) ? $args['placeholder'] : '' )
				);
		}

		if ( $is_text ) {
			printf(
				'<button type="button" class="button-link giocookies-reset" data-gc-reset="%1$s"%2$s>%3$s</button>',
				esc_attr( $name ),
				'' === trim( (string) $value ) ? ' hidden' : '',
				esc_html__( 'Use the default text', 'giocookies' )
			);
		}

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Sanitize a checkbox to '1' or '0'.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_checkbox( $value ) {
		return ( '1' === (string) $value || true === $value ) ? '1' : '0';
	}

	/**
	 * Sanitize the banner message (limited HTML).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_message( $value ) {
		return trim( wp_kses( (string) $value, self::message_allowed_html() ) );
	}

	/**
	 * Sanitize the position.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_position( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, self::POSITIONS, true ) ? $value : 'bottom-left';
	}

	/**
	 * Sanitize a hex color, empty allowed.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_color( $value ) {
		$color = sanitize_hex_color( trim( (string) $value ) );
		return $color ? $color : '';
	}

	/**
	 * Sanitize retention days (1-3650).
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_retention( $value ) {
		$days = absint( $value );
		if ( $days < 1 ) {
			$days = 365;
		}
		return min( $days, 3650 );
	}

	/**
	 * Sanitize the consent version string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_version( $value ) {
		$value = preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $value );
		return substr( (string) $value, 0, 32 );
	}
}
