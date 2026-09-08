<?php
/**
 * Yönetim ekranı: Ayarlar → no404.
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class No404_Admin {

	const PAGE_SLUG   = 'no404';
	const GROUP       = 'no404_settings_group';
	const AJAX_ACTION = 'no404_test_connection';

	/** Kancaları bağlar. */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_test' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( NO404_PLUGIN_FILE ),
			array( $this, 'action_links' )
		);
	}

	/**
	 * Eklenti listesine "Settings" bağlantısı ekler.
	 *
	 * @param string[] $links Mevcut bağlantılar.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'no404-auto-404-redirect' ) . '</a>'
		);

		return $links;
	}

	/** Menü kaydı. */
	public function add_menu() {
		add_options_page(
			__( 'no404 – Auto 404 Redirect', 'no404-auto-404-redirect' ),
			__( 'no404', 'no404-auto-404-redirect' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/** Settings API kayıtları. */
	public function register_settings() {
		register_setting(
			self::GROUP,
			No404_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'No404_Options', 'sanitize' ),
				'default'           => No404_Options::defaults(),
			)
		);

		add_settings_section(
			'no404_connection',
			__( 'Connection', 'no404-auto-404-redirect' ),
			array( $this, 'section_connection' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'no404_behaviour',
			__( 'Behaviour', 'no404-auto-404-redirect' ),
			array( $this, 'section_behaviour' ),
			self::PAGE_SLUG
		);

		$fields = array(
			array( 'api_base', __( 'no404 address', 'no404-auto-404-redirect' ), 'no404_connection' ),
			array( 'api_key', __( 'API key', 'no404-auto-404-redirect' ), 'no404_connection' ),
			array( 'enabled', __( 'Redirecting', 'no404-auto-404-redirect' ), 'no404_behaviour' ),
			array( 'force_301', __( 'Permanent redirects', 'no404-auto-404-redirect' ), 'no404_behaviour' ),
			array( 'cache_ttl', __( 'Cache lifetime', 'no404-auto-404-redirect' ), 'no404_behaviour' ),
			array( 'timeout_ms', __( 'Timeout', 'no404-auto-404-redirect' ), 'no404_behaviour' ),
			array( 'exclude_paths', __( 'Excluded paths', 'no404-auto-404-redirect' ), 'no404_behaviour' ),
		);

		foreach ( $fields as $field ) {
			add_settings_field(
				'no404_' . $field[0],
				$field[1],
				array( $this, 'render_field' ),
				self::PAGE_SLUG,
				$field[2],
				array( 'key' => $field[0], 'label_for' => 'no404_' . $field[0] )
			);
		}
	}

	/** Bağlantı bölümü açıklaması. */
	public function section_connection() {
		echo '<p>' . esc_html__(
			'Get your API key from the site settings page in your no404 dashboard. The key is used server-side only and never appears in your site\'s source code.',
			'no404-auto-404-redirect'
		) . '</p>';
	}

	/** Davranış bölümü açıklaması. */
	public function section_behaviour() {
		echo '<p>' . esc_html__(
			'Caching stops bots that hit the same dead URL over and over from burning through your monthly event quota. A shorter lifetime means higher quota usage.',
			'no404-auto-404-redirect'
		) . '</p>';
	}

	/**
	 * Tek bir ayar alanını basar.
	 *
	 * @param array $args label_for ve key içerir.
	 * @return void
	 */
	public function render_field( $args ) {
		$key      = isset( $args['key'] ) ? $args['key'] : '';
		$settings = No404_Options::all();
		$name     = No404_Options::OPTION . '[' . $key . ']';
		$id       = 'no404_' . $key;

		switch ( $key ) {
			case 'api_base':
				printf(
					'<input type="url" id="%1$s" name="%2$s" value="%3$s" class="regular-text" placeholder="%4$s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $settings['api_base'] ),
					esc_attr( No404_Options::DEFAULT_API_BASE )
				);
				echo '<p class="description">' . esc_html(
					sprintf(
						/* translators: %s: default no404 service address. */
						__( 'You should not need to touch this — the default, %s, is the right address. Change it only if you host no404 on your own server. Leave the field empty to restore the default.', 'no404-auto-404-redirect' ),
						No404_Options::DEFAULT_API_BASE
					)
				) . '</p>';
				break;

			case 'api_key':
				$masked = No404_Options::masked_key();
				printf(
					'<input type="password" id="%1$s" name="%2$s" value="" class="regular-text" autocomplete="off" placeholder="%3$s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( '' !== $masked ? $masked : __( 'Paste your key', 'no404-auto-404-redirect' ) )
				);
				if ( '' !== $masked ) {
					echo '<p class="description">' . esc_html(
						sprintf(
							/* translators: %s: masked API key. */
							__( 'Saved key: %s — leave this field empty to keep it.', 'no404-auto-404-redirect' ),
							$masked
						)
					) . '</p>';
				}
				break;

			case 'enabled':
			case 'force_301':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( 1, (int) $settings[ $key ], false ),
					esc_html(
						'enabled' === $key
							? __( 'Apply no404 redirects on pages that are not found', 'no404-auto-404-redirect' )
							: __( 'Send every match as a 301 (permanent)', 'no404-auto-404-redirect' )
					)
				);
				if ( 'force_301' === $key ) {
					echo '<p class="description">' . esc_html__(
						'By default only manually defined redirects and high-scoring matches are sent as 301; speculative matches are sent as 302. A 301 is cached permanently by browsers and cannot be taken back — tick this box only if you are confident your catalogue is complete.',
						'no404-auto-404-redirect'
					) . '</p>';
				}
				break;

			case 'cache_ttl':
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$d" min="60" max="604800" step="60" class="small-text" /> %4$s',
					esc_attr( $id ),
					esc_attr( $name ),
					(int) $settings['cache_ttl'],
					esc_html__( 'seconds', 'no404-auto-404-redirect' )
				);
				echo '<p class="description">' . esc_html__( 'Default 3600 (1 hour). Minimum 60, maximum 604800 (7 days).', 'no404-auto-404-redirect' ) . '</p>';
				break;

			case 'timeout_ms':
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$d" min="200" max="10000" step="100" class="small-text" /> %4$s',
					esc_attr( $id ),
					esc_attr( $name ),
					(int) $settings['timeout_ms'],
					esc_html__( 'milliseconds', 'no404-auto-404-redirect' )
				);
				echo '<p class="description">' . esc_html__(
					'If no404 does not answer within this time the request is dropped and your site shows its own 404 page. Visitors are never left waiting.',
					'no404-auto-404-redirect'
				) . '</p>';
				break;

			case 'exclude_paths':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="5" class="large-text code" placeholder="/campaigns&#10;/old-blog">%3$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_textarea( $settings['exclude_paths'] )
				);
				echo '<p class="description">' . esc_html__(
					'One path prefix per line. URLs starting with these prefixes are never sent to no404. Static files (.css, .js, .png …) and paths such as /wp-admin and /wp-json are excluded automatically.',
					'no404-auto-404-redirect'
				) . '</p>';
				break;
		}
	}

	/**
	 * Yönetim betiklerini yükler.
	 *
	 * @param string $hook Geçerli ekran.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'no404-admin',
			plugins_url( 'assets/admin.js', NO404_PLUGIN_FILE ),
			array(),
			NO404_VERSION,
			true
		);

		wp_localize_script(
			'no404-admin',
			'no404Admin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::AJAX_ACTION ),
				'testing' => __( 'Testing…', 'no404-auto-404-redirect' ),
				'failed'  => __( 'The test could not be completed. Reload the page and try again.', 'no404-auto-404-redirect' ),
			)
		);
	}

	/** Ayar sayfasını basar. */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = No404_Options::all();
		$configured = '' !== $settings['api_key'] && '' !== $settings['api_base'];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'no404 – Auto 404 Redirect', 'no404-auto-404-redirect' ); ?></h1>

			<?php if ( ! $configured ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'The plugin is not running yet: no API key has been entered.', 'no404-auto-404-redirect' ); ?></p>
				</div>
			<?php elseif ( empty( $settings['enabled'] ) ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Redirecting is switched off. 404 pages are shown as they are.', 'no404-auto-404-redirect' ); ?></p>
				</div>
			<?php else : ?>
				<div class="notice notice-success">
					<p><?php esc_html_e( 'Active. Pages that are not found are redirected server-side.', 'no404-auto-404-redirect' ); ?></p>
				</div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Connection test', 'no404-auto-404-redirect' ); ?></h2>
			<p>
				<?php esc_html_e(
					'Sends a real request to no404 using your saved settings. Save your changes first.',
					'no404-auto-404-redirect'
				); ?>
			</p>
			<p>
				<label for="no404-test-path"><?php esc_html_e( 'Path to test', 'no404-auto-404-redirect' ); ?></label><br />
				<input type="text" id="no404-test-path" class="regular-text code" value="/no404-baglanti-testi" />
			</p>
			<p>
				<button type="button" class="button button-secondary" id="no404-test-button">
					<?php esc_html_e( 'Test the connection', 'no404-auto-404-redirect' ); ?>
				</button>
			</p>
			<div id="no404-test-result" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * AJAX: bağlantı testi.
	 *
	 * @return void
	 */
	public function ajax_test() {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'no404-auto-404-redirect' ) ), 403 );
		}

		$path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		if ( '' === $path ) {
			$path = '/no404-baglanti-testi';
		}

		$client = no404()->client();
		if ( ! $client instanceof No404_Client ) {
			wp_send_json_error( array( 'message' => __( 'The client could not be initialised.', 'no404-auto-404-redirect' ) ) );
		}

		$result = $client->ping( $path );
		$body   = self::describe( $result );

		wp_send_json_success(
			array(
				'ok'      => 'ok' === $result['code'],
				'message' => $body,
			)
		);
	}

	/**
	 * Ping sonucunu kullanıcıya okunur mesaja çevirir.
	 *
	 * Kullanıcı neyin yanlış olduğunu ayar ekranında anlamalı; 404/403/429
	 * ayrı ayrı açıklanır.
	 *
	 * @param array $result No404_Client::ping çıktısı.
	 * @return string
	 */
	protected static function describe( array $result ) {
		$detail = isset( $result['detail'] ) ? trim( (string) $result['detail'] ) : '';

		switch ( $result['code'] ) {
			case 'ok':
				if ( ! empty( $result['redirect'] ) ) {
					return sprintf(
						/* translators: 1: target URL, 2: match source, 3: score. */
						__( 'Connection succeeded. Suggested target for this path: %1$s (source: %2$s, score: %3$s).', 'no404-auto-404-redirect' ),
						$result['redirect'],
						$result['source'],
						number_format_i18n( (float) $result['score'], 2 )
					);
				}

				return __( 'Connection succeeded. Your API key is valid; no match was found for this test path, which is what we expect.', 'no404-auto-404-redirect' );

			case 'no_api_key':
				return __( 'No API key has been entered. Save your key and try again.', 'no404-auto-404-redirect' );

			case 'no_api_base':
				return __( 'The no404 address is empty.', 'no404-auto-404-redirect' );

			case 'invalid_key':
				return __( 'Invalid API key (404). Copy the key from the site settings page in your no404 dashboard; if you rotated the key recently, enter the new one here as well.', 'no404-auto-404-redirect' );

			case 'forbidden':
				return $detail
					? sprintf(
						/* translators: %s: server message. */
						__( 'Access denied (403): %s. Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.', 'no404-auto-404-redirect' ),
						$detail
					)
					: __( 'Access denied (403). Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.', 'no404-auto-404-redirect' );

			case 'rate_limited':
				return __( 'Rate limit exceeded, or your monthly event quota is used up (429). Check your quota in the no404 dashboard; if you sent many requests in a short time, try again in a minute.', 'no404-auto-404-redirect' );

			case 'invalid_path':
				return __( 'The test path is invalid (422). Enter a path that starts with "/".', 'no404-auto-404-redirect' );

			case 'unreachable':
				return $detail
					? sprintf(
						/* translators: %s: transport error. */
						__( 'Could not reach the no404 server: %s. Make sure your server is allowed to make outbound HTTPS requests.', 'no404-auto-404-redirect' ),
						$detail
					)
					: __( 'Could not reach the no404 server. Make sure your server is allowed to make outbound HTTPS requests.', 'no404-auto-404-redirect' );

			case 'server_error':
				return __( 'no404 hit a temporary error (5xx). Your site is unaffected; try again shortly.', 'no404-auto-404-redirect' );

			default:
				return sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Unexpected response (HTTP %d).', 'no404-auto-404-redirect' ),
					(int) $result['status']
				);
		}
	}
}
