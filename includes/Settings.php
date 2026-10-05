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

		$sections = array(
			'giocookies_general'    => array( __( 'General', 'giocookies' ), array( $this, 'section_general' ) ),
			'giocookies_categories' => array( __( 'Categories', 'giocookies' ), array( $this, 'section_categories' ) ),
			'giocookies_texts'      => array( __( 'Banner texts', 'giocookies' ), array( $this, 'section_texts' ) ),
			'giocookies_links'      => array( __( 'Policy links', 'giocookies' ), null ),
			'giocookies_appearance' => array( __( 'Appearance', 'giocookies' ), array( $this, 'section_appearance' ) ),
			'giocookies_log'        => array( __( 'Consent log', 'giocookies' ), array( $this, 'section_log' ) ),
			'giocookies_advanced'   => array( __( 'Uninstall', 'giocookies' ), null ),
		);
		foreach ( $sections as $id => $section ) {
			add_settings_section( $id, $section[0], $section[1], self::PAGE );
		}

		$texts = self::text_defaults();

		$this->field( 'giocookies_enable', __( 'Enable banner', 'giocookies' ), 'giocookies_general', 'checkbox', array( 'label' => __( 'Show the cookie banner and apply Consent Mode defaults on the frontend.', 'giocookies' ) ) );
		$this->field(
			'giocookies_consent_version',
			__( 'Consent version', 'giocookies' ),
			'giocookies_general',
			'text',
			array(
				'class'       => 'small-text giocookies-version',
				'description' => __( 'Optional. Change this value (for example from 1 to 2) when your cookie policy changes: visitors whose stored choice has a different version will see the banner again. Letters, numbers, dots, dashes and underscores only.', 'giocookies' ),
			)
		);

		$this->field( 'giocookies_cat_analytics', __( 'Analytics', 'giocookies' ), 'giocookies_categories', 'checkbox', array( 'label' => __( 'Ask consent for analytics cookies (Consent Mode: analytics_storage).', 'giocookies' ) ) );
		$this->field( 'giocookies_cat_marketing', __( 'Marketing', 'giocookies' ), 'giocookies_categories', 'checkbox', array( 'label' => __( 'Ask consent for marketing cookies (Consent Mode: ad_storage, ad_user_data, ad_personalization).', 'giocookies' ) ) );

		$this->field( 'giocookies_title', __( 'Title', 'giocookies' ), 'giocookies_texts', 'text', array( 'placeholder' => $texts['giocookies_title'] ) );
		$this->field(
			'giocookies_message',
			__( 'Message', 'giocookies' ),
			'giocookies_texts',
			'textarea',
			array(
				'placeholder' => $texts['giocookies_message'],
				'description' => __( 'Allowed HTML: a, strong, em, br.', 'giocookies' ),
			)
		);
		$this->field( 'giocookies_label_reject', __( '"Reject all" button', 'giocookies' ), 'giocookies_texts', 'text', array( 'placeholder' => $texts['giocookies_label_reject'] ) );
		$this->field( 'giocookies_label_accept', __( '"Accept all" button', 'giocookies' ), 'giocookies_texts', 'text', array( 'placeholder' => $texts['giocookies_label_accept'] ) );
		$this->field( 'giocookies_label_save', __( '"Save choices" button', 'giocookies' ), 'giocookies_texts', 'text', array( 'placeholder' => $texts['giocookies_label_save'] ) );
		$this->field( 'giocookies_label_customize', __( '"Customize" button', 'giocookies' ), 'giocookies_texts', 'text', array( 'placeholder' => $texts['giocookies_label_customize'] ) );
		$this->field( 'giocookies_label_necessary', __( 'Necessary: name', 'giocookies' ), 'giocookies_texts', 'text', array( 'placeholder' => $texts['giocookies_label_necessary'] ) );
		$this->field( 'giocookies_label_analytics', __( 'Analytics: name', 'giocookies' ), 'giocookies_texts', 'text', array( 'placeholder' => $texts['giocookies_label_analytics'] ) );
		$this->field( 'giocookies_label_marketing', __( 'Marketing: name', 'giocookies' ), 'giocookies_texts', 'text', array( 'placeholder' => $texts['giocookies_label_marketing'] ) );
		$this->field( 'giocookies_desc_necessary', __( 'Necessary: description', 'giocookies' ), 'giocookies_texts', 'textarea', array( 'placeholder' => $texts['giocookies_desc_necessary'], 'rows' => 2 ) );
		$this->field( 'giocookies_desc_analytics', __( 'Analytics: description', 'giocookies' ), 'giocookies_texts', 'textarea', array( 'placeholder' => $texts['giocookies_desc_analytics'], 'rows' => 2 ) );
		$this->field( 'giocookies_desc_marketing', __( 'Marketing: description', 'giocookies' ), 'giocookies_texts', 'textarea', array( 'placeholder' => $texts['giocookies_desc_marketing'], 'rows' => 2 ) );

		$this->field( 'giocookies_privacy_page', __( 'Privacy policy', 'giocookies' ), 'giocookies_links', 'page_or_url', array( 'url_option' => 'giocookies_privacy_url' ) );
		$this->field( 'giocookies_cookie_page', __( 'Cookie policy', 'giocookies' ), 'giocookies_links', 'page_or_url', array( 'url_option' => 'giocookies_cookie_url' ) );

		$this->field(
			'giocookies_position',
			__( 'Position', 'giocookies' ),
			'giocookies_appearance',
			'select',
			array(
				'choices' => array(
					'bottom-left'  => __( 'Bottom left card', 'giocookies' ),
					'bottom-right' => __( 'Bottom right card', 'giocookies' ),
					'bottom-bar'   => __( 'Bottom bar (full width)', 'giocookies' ),
				),
			)
		);
		$this->field(
			'giocookies_show_bubble',
			__( 'Floating button', 'giocookies' ),
			'giocookies_appearance',
			'checkbox',
			array(
				'label'       => __( 'Show the floating cookie button that reopens the preferences.', 'giocookies' ),
				'description' => __( 'If you hide it, give visitors another way to change their choice: the [giocookies_preferences] shortcode or any element with the data-giocookies-open attribute.', 'giocookies' ),
			)
		);
		$this->field( 'giocookies_accent_color', __( 'Accent color', 'giocookies' ), 'giocookies_appearance', 'color', array( 'description' => __( 'Buttons, switches and focus ring. Leave empty to use the stylesheet or theme value.', 'giocookies' ) ) );
		$this->field( 'giocookies_accent_text', __( 'Button text color', 'giocookies' ), 'giocookies_appearance', 'color', array( 'description' => __( 'Text color on accent buttons. Leave empty to use the stylesheet or theme value.', 'giocookies' ) ) );

		$this->field( 'giocookies_log_enabled', __( 'Log consent', 'giocookies' ), 'giocookies_log', 'checkbox', array( 'label' => __( 'Store each choice in the consent log (anonymized IP, consent ID, choices, version, date).', 'giocookies' ) ) );
		$this->field(
			'giocookies_log_retention',
			__( 'Retention (days)', 'giocookies' ),
			'giocookies_log',
			'number',
			array(
				'min'         => 1,
				'max'         => 3650,
				'description' => __( 'Log entries older than this are deleted automatically once a day.', 'giocookies' ),
			)
		);

		$this->field( 'giocookies_remove_data', __( 'Remove data', 'giocookies' ), 'giocookies_advanced', 'checkbox', array( 'label' => __( 'Delete all GioCookies settings and the consent log when the plugin is deleted.', 'giocookies' ) ) );
	}

	/**
	 * Shorthand for add_settings_field().
	 *
	 * @param string $name    Option name.
	 * @param string $title   Field title.
	 * @param string $section Section id.
	 * @param string $type    Field type.
	 * @param array  $args    Extra args.
	 */
	private function field( $name, $title, $section, $type, $args = array() ) {
		$args['name'] = $name;
		$args['type'] = $type;
		if ( ! in_array( $type, array( 'checkbox', 'page_or_url' ), true ) ) {
			$args['label_for'] = $name;
		}
		add_settings_field( $name, $title, array( $this, 'render_field' ), self::PAGE, $section, $args );
	}

	/**
	 * Generic field renderer.
	 *
	 * @param array $args Field args.
	 */
	public function render_field( $args ) {
		$name  = $args['name'];
		$value = self::get( $name );

		switch ( $args['type'] ) {
			case 'checkbox':
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s /> %3$s</label>',
					esc_attr( $name ),
					checked( (string) $value, '1', false ),
					esc_html( isset( $args['label'] ) ? $args['label'] : '' )
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%1$s" class="large-text" rows="%2$d" placeholder="%3$s">%4$s</textarea>',
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

			case 'select':
				printf( '<select id="%1$s" name="%1$s">', esc_attr( $name ) );
				foreach ( $args['choices'] as $key => $label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( (string) $value, $key, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'color':
				printf(
					'<input type="text" id="%1$s" name="%1$s" class="giocookies-color" value="%2$s" maxlength="7" placeholder="#RRGGBB" />',
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
				$args['description'] = __( 'Choose a page, or leave "Custom URL" selected and enter an address. A selected page takes precedence.', 'giocookies' );
				break;

			default:
				printf(
					'<input type="text" id="%1$s" name="%1$s" class="%2$s" value="%3$s" placeholder="%4$s" />',
					esc_attr( $name ),
					esc_attr( isset( $args['class'] ) ? $args['class'] : 'regular-text' ),
					esc_attr( (string) $value ),
					esc_attr( isset( $args['placeholder'] ) ? $args['placeholder'] : '' )
				);
		}

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Section intro: general.
	 */
	public function section_general() {
		echo '<p>' . esc_html__( 'GioCookies prints Google Consent Mode v2 defaults in the page head, shows the banner and sends a cookie_consent_update event to the dataLayer when visitors decide.', 'giocookies' ) . '</p>';

		$consent_api = function_exists( 'wp_has_consent' ) || class_exists( 'WP_CONSENT_API' );
		echo '<p>';
		if ( $consent_api ) {
			esc_html_e( 'WP Consent API: detected. Choices are shared with plugins that support it.', 'giocookies' );
		} else {
			esc_html_e( 'WP Consent API: not installed (optional). When it is active, choices are shared with plugins that support it.', 'giocookies' );
		}
		echo '</p>';
	}

	/**
	 * Section intro: texts.
	 */
	public function section_categories() {
		echo '<p>' . esc_html__( 'Turn off the categories your site does not use: they disappear from the banner and stay denied in Consent Mode. "Accept all" only grants the categories that are on.', 'giocookies' ) . '</p>';
	}

	/**
	 * Texts section intro.
	 */
	public function section_texts() {
		echo '<p>' . esc_html__( 'Leave a field empty to use the default text shown as placeholder (it follows the site language).', 'giocookies' ) . '</p>';
	}

	/**
	 * Section intro: appearance.
	 */
	public function section_appearance() {
		echo '<p>' . esc_html__( 'Themes can also style the banner with the --giocookies-* CSS custom properties.', 'giocookies' ) . '</p>';
	}

	/**
	 * Section intro: log.
	 */
	public function section_log() {
		printf(
			'<p>%1$s <a href="%2$s">%3$s</a></p>',
			esc_html__( 'The full IP address is never stored.', 'giocookies' ),
			esc_url( admin_url( 'admin.php?page=' . Admin::LOG_PAGE ) ),
			esc_html__( 'View the consent log', 'giocookies' )
		);
	}

	/**
	 * Settings page markup.
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap giocookies-admin">
			<h1><?php esc_html_e( 'GioCookies Settings', 'giocookies' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
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
