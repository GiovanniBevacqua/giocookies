<?php
/**
 * Frontend: Consent Mode defaults, banner, assets and the consent AJAX endpoint.
 *
 * @package GioCookies
 */

namespace GioCookies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend consent manager.
 */
class ConsentManager {

	const CONSENT_COOKIE     = 'giocookies_consent';
	const PREFERENCES_COOKIE = 'giocookies_preferences';
	const DECISIONS          = array( 'accepted', 'declined', 'custom' );

	/**
	 * Whether the banner markup was already printed.
	 *
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'print_consent_defaults' ), -999999 );
		add_action( 'wp_body_open', array( $this, 'render_banner' ) );
		add_action( 'wp_footer', array( $this, 'render_banner_fallback' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		add_action( 'wp_ajax_giocookies_save_consent', array( $this, 'save_consent' ) );
		add_action( 'wp_ajax_nopriv_giocookies_save_consent', array( $this, 'save_consent' ) );

		add_shortcode( 'giocookies_preferences', array( $this, 'shortcode_preferences' ) );

		// WP Consent API: this banner asks for opt-in consent.
		add_filter( 'wp_get_consent_type', array( $this, 'consent_type' ) );
	}

	/**
	 * Stored consent from the request cookies.
	 *
	 * A stored choice is ignored when its version differs from the current
	 * consent version, so the visitor is asked again.
	 *
	 * @return array{decided:bool,analytics:bool,marketing:bool}
	 */
	public static function stored_consent() {
		$state = array(
			'decided'   => false,
			'analytics' => false,
			'marketing' => false,
		);

		if ( ! isset( $_COOKIE[ self::PREFERENCES_COOKIE ] ) ) {
			return $state;
		}

		$stored = json_decode( sanitize_text_field( wp_unslash( $_COOKIE[ self::PREFERENCES_COOKIE ] ) ), true );
		if ( ! is_array( $stored ) ) {
			return $state;
		}

		$stored_version = isset( $stored['version'] ) ? Settings::sanitize_version( $stored['version'] ) : '';
		if ( Settings::consent_version() !== $stored_version ) {
			return $state;
		}

		$state['decided']   = isset( $_COOKIE[ self::CONSENT_COOKIE ] );
		$state['analytics'] = ! empty( $stored['analytics'] );
		$state['marketing'] = ! empty( $stored['marketing'] );

		return $state;
	}

	/**
	 * Print gtag Consent Mode v2 defaults as early as possible in <head>, before GTM.
	 */
	public function print_consent_defaults() {
		if ( ! Settings::is_enabled() ) {
			return;
		}

		$consent   = self::stored_consent();
		$analytics = $consent['analytics'] ? 'granted' : 'denied';
		$ads       = $consent['marketing'] ? 'granted' : 'denied';

		/**
		 * Filter the Consent Mode default command parameters.
		 *
		 * @param array $defaults Parameters passed to gtag( 'consent', 'default', ... ).
		 * @param array $consent  Stored consent state.
		 */
		$defaults = apply_filters(
			'giocookies_consent_defaults',
			array(
				'analytics_storage'  => $analytics,
				'ad_storage'         => $ads,
				'ad_user_data'       => $ads,
				'ad_personalization' => $ads,
			),
			$consent
		);

		$js = "window.dataLayer = window.dataLayer || [];\n"
			. "function gtag(){dataLayer.push(arguments);}\n"
			. "gtag('consent', 'default', " . wp_json_encode( $defaults ) . ");\n";

		wp_print_inline_script_tag( $js, array( 'id' => 'giocookies-consent-defaults' ) );
	}

	/**
	 * Enqueue frontend CSS/JS and the runtime configuration.
	 */
	public function enqueue_frontend_assets() {
		if ( ! Settings::is_enabled() ) {
			return;
		}

		wp_enqueue_style( 'giocookies-style', GIOCOOKIES_URL . 'assets/css/style.css', array(), Plugin::asset_version( 'assets/css/style.css' ) );

		$css    = '';
		$accent = Settings::get( 'giocookies_accent_color' );
		$text   = Settings::get( 'giocookies_accent_text' );
		$accent = $accent ? sanitize_hex_color( $accent ) : '';
		$text   = $text ? sanitize_hex_color( $text ) : '';
		if ( $accent ) {
			$css .= "--giocookies-accent:{$accent};--giocookies-accent-hover:{$accent};--giocookies-accent-hover:color-mix(in srgb,{$accent} 85%,#000);";
		}
		if ( $text ) {
			$css .= "--giocookies-accent-text:{$text};";
		}
		if ( $css ) {
			wp_add_inline_style( 'giocookies-style', ':root .giocookies-banner,:root .giocookies-bubble{' . $css . '}' );
		}

		wp_enqueue_script( 'giocookies-js', GIOCOOKIES_URL . 'assets/js/consent-manager.js', array(), Plugin::asset_version( 'assets/js/consent-manager.js' ), true );

		/**
		 * Filter the lifetime of the consent cookies, in days.
		 *
		 * @param int $days Default 365.
		 */
		$days = (int) apply_filters( 'giocookies_cookie_days', 365 );

		$config = array(
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'giocookies_nonce' ),
			'version'     => Settings::consent_version(),
			'log'         => '1' === (string) Settings::get( 'giocookies_log_enabled' ),
			'cookie_days' => max( 1, $days ),
			'categories'  => Settings::categories(),
		);

		wp_add_inline_script( 'giocookies-js', 'var giocookies_vars = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Print the banner (wp_body_open).
	 */
	public function render_banner() {
		if ( $this->rendered || ! Settings::is_enabled() ) {
			return;
		}
		$this->rendered = true;

		$position    = Settings::get( 'giocookies_position' );
		$position    = in_array( $position, Settings::POSITIONS, true ) ? $position : 'bottom-left';
		$show_bubble = '1' === (string) Settings::get( 'giocookies_show_bubble' );
		$privacy_url = Settings::policy_url( 'privacy' );
		$cookie_url  = Settings::policy_url( 'cookie' );

		$categories = Settings::categories();
		$options    = array(
			'necessary' => array(
				'label'       => Settings::text( 'giocookies_label_necessary' ),
				'description' => Settings::text( 'giocookies_desc_necessary' ),
			),
		);
		foreach ( array( 'analytics', 'marketing' ) as $category ) {
			if ( $categories[ $category ] ) {
				$options[ $category ] = array(
					'label'       => Settings::text( 'giocookies_label_' . $category ),
					'description' => Settings::text( 'giocookies_desc_' . $category ),
				);
			}
		}

		$pos_class = 'giocookies-pos-' . $position;
		?>

		<?php if ( $show_bubble ) : ?>
		<button type="button" id="giocookies-bubble" class="giocookies-bubble <?php echo esc_attr( $pos_class ); ?>" aria-controls="giocookies-banner" aria-expanded="false">
			<svg xmlns="http://www.w3.org/2000/svg" height="22" viewBox="0 0 24 24" width="22" fill="currentColor" aria-hidden="true" focusable="false">
				<path d="M21.5 12c0-.82-.68-1.5-1.5-1.5h-1c-.55 0-1-.45-1-1V8c0-.55-.45-1-1-1h-1c-.55 0-1-.45-1-1V4.5c0-.82-.68-1.5-1.5-1.5C9.36 3 5 7.36 5 12.5c0 4.14 2.76 7.63 6.5 8.73-.39-.46-.63-1.06-.63-1.73 0-1.38 1.12-2.5 2.5-2.5.67 0 1.27.24 1.73.63 1.1-3.74 4.59-6.5 8.73-6.5h.76c.82 0 1.5-.68 1.5-1.5zM10 13c-.83 0-1.5-.67-1.5-1.5S9.17 10 10 10s1.5.67 1.5 1.5S10.83 13 10 13zm3-4c-.83 0-1.5-.67-1.5-1.5S12.17 6 13 6s1.5.67 1.5 1.5S13.83 9 13 9zm4 6c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
			</svg>
			<span class="giocookies-sr"><?php esc_html_e( 'Cookie preferences', 'giocookies' ); ?></span>
		</button>
		<?php endif; ?>

		<section id="giocookies-banner" class="giocookies-banner <?php echo esc_attr( $pos_class ); ?>" role="dialog" aria-modal="false" aria-labelledby="giocookies-title" aria-describedby="giocookies-message">
			<h2 id="giocookies-title" class="giocookies-title" tabindex="-1"><?php echo esc_html( Settings::text( 'giocookies_title' ) ); ?></h2>
			<p id="giocookies-message" class="giocookies-message"><?php echo wp_kses( Settings::text( 'giocookies_message' ), Settings::message_allowed_html() ); ?></p>

			<div id="giocookies-preferences" class="giocookies-preferences" hidden>
				<?php foreach ( $options as $key => $option ) : ?>
					<div class="giocookies-option">
						<label class="giocookies-option__text" for="giocookies-<?php echo esc_attr( $key ); ?>">
							<strong><?php echo esc_html( $option['label'] ); ?></strong>
							<span><?php echo esc_html( $option['description'] ); ?></span>
						</label>
						<input type="checkbox" role="switch" id="giocookies-<?php echo esc_attr( $key ); ?>" class="giocookies-switch"<?php echo 'necessary' === $key ? ' checked disabled' : ''; ?>>
					</div>
				<?php endforeach; ?>
				<?php
				/**
				 * Fires at the end of the preferences panel, after the category switches.
				 *
				 * @since 1.0.0
				 */
				do_action( 'giocookies_preferences_end' );
				?>
			</div>

			<div class="giocookies-actions">
				<button type="button" id="giocookies-decline" class="giocookies-btn"><?php echo esc_html( Settings::text( 'giocookies_label_reject' ) ); ?></button>
				<button type="button" id="giocookies-accept" class="giocookies-btn"><?php echo esc_html( Settings::text( 'giocookies_label_accept' ) ); ?></button>
				<button type="button" id="giocookies-accept-selected" class="giocookies-btn giocookies-btn--outline" hidden><?php echo esc_html( Settings::text( 'giocookies_label_save' ) ); ?></button>
				<button type="button" id="giocookies-customize" class="giocookies-btn giocookies-btn--link" aria-expanded="false" aria-controls="giocookies-preferences"><?php echo esc_html( Settings::text( 'giocookies_label_customize' ) ); ?></button>
			</div>

			<?php if ( $privacy_url || $cookie_url ) : ?>
				<p class="giocookies-links">
					<?php if ( $privacy_url ) : ?>
						<a href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'Privacy Policy', 'giocookies' ); ?></a>
					<?php endif; ?>
					<?php if ( $cookie_url ) : ?>
						<a href="<?php echo esc_url( $cookie_url ); ?>"><?php esc_html_e( 'Cookie Policy', 'giocookies' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Print the banner in the footer when the theme does not call wp_body_open().
	 */
	public function render_banner_fallback() {
		$this->render_banner();
	}

	/**
	 * [giocookies_preferences label="..."] — a button that reopens the banner.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_preferences( $atts ) {
		$atts = shortcode_atts(
			array(
				'label' => __( 'Cookie preferences', 'giocookies' ),
				'class' => '',
			),
			$atts,
			'giocookies_preferences'
		);

		return sprintf(
			'<button type="button" class="giocookies-open %1$s" data-giocookies-open aria-controls="giocookies-banner">%2$s</button>',
			esc_attr( $atts['class'] ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * WP Consent API consent type.
	 *
	 * @param string $type Current type.
	 * @return string
	 */
	public function consent_type( $type ) {
		return Settings::is_enabled() ? 'optin' : $type;
	}

	/**
	 * Visitor IP (filterable for sites behind a trusted proxy).
	 *
	 * @return string
	 */
	private function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filter the visitor IP used for rate limiting and the (anonymized) log.
		 * Use it when the site is behind a trusted reverse proxy.
		 *
		 * @param string $ip REMOTE_ADDR.
		 */
		$ip = (string) apply_filters( 'giocookies_client_ip', $ip );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Simple per-IP rate limit for the consent endpoint.
	 *
	 * @param string $ip Visitor IP.
	 * @return bool True when the request is allowed.
	 */
	private function rate_limit_ok( $ip ) {
		/**
		 * Filter the maximum consent saves per IP in a 10 minute window. 0 disables the limit.
		 *
		 * @param int $limit Default 20.
		 */
		$limit = (int) apply_filters( 'giocookies_rate_limit', 20 );
		if ( $limit <= 0 || '' === $ip ) {
			return true;
		}

		$key   = 'giocookies_rl_' . substr( wp_hash( $ip ), 0, 20 );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * AJAX: log a consent decision.
	 */
	public function save_consent() {
		if ( ! check_ajax_referer( 'giocookies_nonce', 'giocookies_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'giocookies' ) ), 403 );
		}

		if ( ! Settings::is_enabled() || '1' !== (string) Settings::get( 'giocookies_log_enabled' ) ) {
			wp_send_json_success( array( 'logged' => false ) );
		}

		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		if ( ! in_array( $decision, self::DECISIONS, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid decision.', 'giocookies' ) ), 400 );
		}

		$ip = $this->client_ip();
		if ( ! $this->rate_limit_ok( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests.', 'giocookies' ) ), 429 );
		}

		$is_true = static function ( $key ) {
			if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked above.
				return false;
			}
			$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked above.
			return in_array( $value, array( 'true', '1' ), true );
		};

		if ( 'accepted' === $decision ) {
			$analytics = true;
			$marketing = true;
		} elseif ( 'declined' === $decision ) {
			$analytics = false;
			$marketing = false;
		} else {
			$analytics = $is_true( 'analytics' );
			$marketing = $is_true( 'marketing' );
		}

		$consent_id = isset( $_POST['consent_id'] ) ? sanitize_text_field( wp_unslash( $_POST['consent_id'] ) ) : '';
		$consent_id = wp_is_uuid( $consent_id ) ? strtolower( $consent_id ) : '';

		$version = isset( $_POST['version'] ) ? Settings::sanitize_version( sanitize_text_field( wp_unslash( $_POST['version'] ) ) ) : Settings::consent_version();

		$result = Plugin::instance()->db->log_consent(
			array(
				'consent_id'         => $consent_id,
				'user_ip'            => DB::anonymize_ip( $ip ),
				'consent_given'      => ( $analytics || $marketing ) ? 1 : 0,
				'decision'           => $decision,
				'consent_version'    => $version,
				'cookie_preferences' => array(
					'necessary' => 1,
					'analytics' => $analytics ? 1 : 0,
					'marketing' => $marketing ? 1 : 0,
				),
			)
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Unable to save consent. Please try again.', 'giocookies' ) ), 500 );
		}

		wp_send_json_success( array( 'logged' => true ) );
	}
}
