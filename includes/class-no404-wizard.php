<?php
/**
 * First-run setup wizard: Welcome → Connect → Redirects → Done.
 *
 * Why it exists: the plugin does nothing until it has an API key, and before
 * 1.1.0 the only hint was a notice on a settings page the user had to find on
 * their own. Activating led nowhere, and people dropped off there.
 *
 * Rules this class keeps (each is a WordPress.org review point or a lesson):
 *   - The activation redirect happens ONCE: a transient set on activation and
 *     deleted by the first admin request that reads it. Never on bulk or network
 *     activation, never for another user, never when the site is already set up.
 *     A redirect that keeps firing is what reviewers call an "admin hijack".
 *   - Settings are written through No404_Options::save(), so the wizard and the
 *     settings screen share one sanitiser.
 *   - Wizard progress lives in its own option (`no404_wizard`), not in
 *     `no404_settings`: saving the settings screen must not reset it.
 *   - A pasted key is checked BEFORE it is saved, including which site it
 *     belongs to (No404_Client::site_info).
 *   - Steps are plain forms posted to admin-post.php (post → redirect → get).
 *     No new AJAX surface; the only script is the existing connection test.
 *
 * @package no404
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class No404_Wizard {

	const PAGE_SLUG = 'no404-setup';

	/** Wizard progress: array( completed => 0|1, version => string ). */
	const OPTION = 'no404_wizard';

	/** One-shot activation redirect; holds the ID of the admin who activated. */
	const REDIRECT_TRANSIENT = 'no404_activation_redirect';

	/** Per-user message carried from a form post to the next step. */
	const NOTICE_TRANSIENT = 'no404_wizard_notice_';

	const ACTION_CONNECT   = 'no404_wizard_connect';
	const ACTION_BEHAVIOUR = 'no404_wizard_behaviour';
	const ACTION_SKIP      = 'no404_wizard_skip';

	/** Step slugs, in order. Do not go past five — long wizards get abandoned. */
	const STEPS = array( 'welcome', 'connect', 'redirects', 'done' );

	/** The screen ID add_dashboard_page() gives the page. */
	const SCREEN_ID = 'dashboard_page_no404-setup';

	// ------------------------------------------------------------ state

	/** @return array The stored wizard progress over its defaults. */
	public static function state() {
		$state = get_option( self::OPTION, array() );

		return array_merge(
			array(
				'completed' => 0,
				'version'   => '',
			),
			is_array( $state ) ? $state : array()
		);
	}

	/**
	 * Has this site been set up?
	 *
	 * A saved API key counts as set up. Sites updating from 1.0.x never ran the
	 * wizard, and a working installation must not be sent through it — this needs
	 * no migration step.
	 *
	 * @return bool
	 */
	public static function is_completed() {
		$state = self::state();

		return ! empty( $state['completed'] ) || '' !== (string) No404_Options::get( 'api_key', '' );
	}

	/** Records that the wizard was finished or skipped. */
	public static function mark_completed() {
		update_option(
			self::OPTION,
			array(
				'completed' => 1,
				'version'   => NO404_VERSION,
			),
			false
		);
	}

	// ------------------------------------------------------------ activation

	/**
	 * Activation hook: asks the next admin page load to open the wizard.
	 *
	 * @param bool $network_wide Multisite network activation.
	 * @return void
	 */
	public static function on_activate( $network_wide = false ) {
		// Network activation has no single site to connect; each site admin finds
		// the notice on their own Plugins screen instead.
		if ( $network_wide ) {
			return;
		}
		if ( self::is_completed() ) {
			return;
		}

		set_transient( self::REDIRECT_TRANSIENT, (int) get_current_user_id(), 60 );
	}

	/**
	 * `admin_init`: performs the one-shot redirect after activation.
	 *
	 * @return void
	 */
	public function maybe_redirect() {
		$user = get_transient( self::REDIRECT_TRANSIENT );
		if ( false === $user ) {
			return;
		}

		// Only the admin who activated the plugin is taken to the wizard.
		if ( (int) $user !== (int) get_current_user_id() ) {
			return;
		}

		// One shot: this request consumes it, whatever happens below.
		delete_transient( self::REDIRECT_TRANSIENT );

		if ( wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) || is_network_admin() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Several plugins activated at once: taking over the screen would be rude,
		// and other plugins' own redirects would race ours.
		if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reads a core flag, changes nothing.
			return;
		}
		if ( self::is_completed() ) {
			return;
		}

		if ( wp_safe_redirect( self::url() ) ) {
			exit;
		}
	}

	// ------------------------------------------------------------ addresses

	/**
	 * @param string $step Step slug; empty for the first one.
	 * @return string The wizard address.
	 */
	public static function url( $step = '' ) {
		$url = admin_url( 'index.php?page=' . self::PAGE_SLUG );

		return in_array( $step, self::STEPS, true ) ? $url . '&step=' . $step : $url;
	}

	/**
	 * no404 sign-up, with this site's host handed over (`?site=`): after sign-up
	 * no404's Search Console wizard opens with the matching property selected.
	 * The host is only a convenience there — ownership is still proven through
	 * Search Console.
	 *
	 * @return string
	 */
	public static function signup_url() {
		$locale = function_exists( 'get_user_locale' ) ? (string) get_user_locale() : '';
		$lang   = ( 0 === strpos( $locale, 'tr' ) ) ? 'tr' : 'en';
		$host   = self::site_host();

		return self::service_base() . '/' . $lang . '/register' . ( '' !== $host ? '?site=' . rawurlencode( $host ) : '' );
	}

	/** @return string The no404 dashboard. */
	public static function dashboard_url() {
		return self::service_base() . '/panel';
	}

	/** @return string This site's host, lower case. */
	public static function site_host() {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/** @return string The no404 address from the settings (self-hosted installs included). */
	protected static function service_base() {
		$base = (string) No404_Options::get( 'api_base', No404_Options::DEFAULT_API_BASE );

		return '' !== $base ? $base : No404_Options::DEFAULT_API_BASE;
	}

	/**
	 * @param string $from 'notice' when dismissed from the Plugins screen.
	 * @return string Nonce-protected "skip setup" address.
	 */
	public static function skip_url( $from = '' ) {
		$url = admin_url( 'admin-post.php?action=' . self::ACTION_SKIP . ( 'notice' === $from ? '&from=notice' : '' ) );

		return wp_nonce_url( $url, self::ACTION_SKIP );
	}

	// ------------------------------------------------------------ actions (testable)

	/**
	 * Checks a pasted key with no404 and stores it when it belongs to this site.
	 *
	 * An empty or masked value re-checks the saved key.
	 *
	 * @param string $raw_key What the user pasted.
	 * @return array ok (bool), type (notice type), message (string, may be empty)
	 */
	public function connect( $raw_key ) {
		$raw_key = trim( (string) $raw_key );
		$key     = ( '' === $raw_key || false !== strpos( $raw_key, No404_Options::MASK ) )
			? (string) No404_Options::get( 'api_key', '' )
			: No404_Options::clean_key( $raw_key );

		if ( '' === $key ) {
			return self::result( false, __( 'Paste your API key first.', 'no404-auto-404-redirect' ) );
		}

		$client = no404()->build_client( array( 'api_key' => $key ) );
		$info   = $client->site_info();

		if ( 'unsupported' === $info['code'] ) {
			// A no404 server older than the site endpoint: the connection test is
			// the best check it offers (it cannot tell which site the key is for).
			$ping = $client->ping();
			if ( 'ok' !== $ping['code'] ) {
				return self::result( false, No404_Admin::describe( $ping ) );
			}

			$this->save_key( $key );
			return self::result( true, '' );
		}

		if ( 'ok' !== $info['code'] ) {
			return self::result( false, No404_Admin::describe( $info ) );
		}

		if ( ! $client->site_host_matches( $info['host'] ) ) {
			return self::result(
				false,
				sprintf(
					/* translators: 1: the host the key belongs to, 2: this WordPress site's host. */
					__( 'This key belongs to %1$s, but this WordPress site is %2$s. Copy this site\'s key from its Integration tab in the no404 dashboard. If you are sure, you can still save the key on the settings page.', 'no404-auto-404-redirect' ),
					$info['host'],
					self::site_host()
				)
			);
		}

		$this->save_key( $key );

		return self::result(
			true,
			sprintf(
				/* translators: %s: the connected site's host. */
				__( 'Connected to %s.', 'no404-auto-404-redirect' ),
				$info['host']
			)
		);
	}

	/**
	 * Stores the redirect behaviour chosen on step 3.
	 *
	 * @param bool $force_301 Send every redirect as a 301.
	 * @return void
	 */
	public function set_behaviour( $force_301 ) {
		No404_Options::save(
			array(
				'force_301' => $force_301 ? 1 : 0,
				'enabled'   => 1,
			)
		);
		no404()->reset_client();
	}

	/**
	 * @param string $key A checked key.
	 * @return void
	 */
	protected function save_key( $key ) {
		No404_Options::save(
			array(
				'api_key' => $key,
				'enabled' => 1,
			)
		);
		no404()->reset_client();
	}

	/**
	 * @param bool   $ok      Did it work?
	 * @param string $message What to tell the user.
	 * @return array
	 */
	protected static function result( $ok, $message ) {
		return array(
			'ok'      => (bool) $ok,
			'type'    => $ok ? 'success' : 'error',
			'message' => (string) $message,
		);
	}

	// ------------------------------------------------------------ hooks

	/** Registers the hooks (admin only). */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_head', array( $this, 'hide_menu_entry' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
		add_action( 'current_screen', array( $this, 'quiet_screen' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'setup_notice' ) );
		add_action( 'admin_post_' . self::ACTION_CONNECT, array( $this, 'handle_connect' ) );
		add_action( 'admin_post_' . self::ACTION_BEHAVIOUR, array( $this, 'handle_behaviour' ) );
		add_action( 'admin_post_' . self::ACTION_SKIP, array( $this, 'handle_skip' ) );
	}

	/**
	 * The page is registered under Dashboard and its menu entry removed again in
	 * admin_head: it has an address but no place in the menu. (A null parent
	 * slug raises deprecation notices on PHP 8.1+.)
	 */
	public function add_page() {
		add_dashboard_page(
			__( 'no404 setup', 'no404-auto-404-redirect' ),
			__( 'no404 setup', 'no404-auto-404-redirect' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/** Removes the menu entry (after the page title has been worked out). */
	public function hide_menu_entry() {
		remove_submenu_page( 'index.php', self::PAGE_SLUG );
	}

	/**
	 * On the wizard screen ONLY, other plugins' notices are removed — they would
	 * break the layout. Doing this globally would break those plugins.
	 *
	 * @param WP_Screen $screen The current screen.
	 * @return void
	 */
	public function quiet_screen( $screen ) {
		if ( ! is_object( $screen ) || ! isset( $screen->id ) || self::SCREEN_ID !== $screen->id ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	}

	/**
	 * @param string $classes Admin body classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		return self::is_wizard_screen() ? $classes . ' no404-wizard' : $classes;
	}

	/**
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( self::SCREEN_ID !== $hook ) {
			return;
		}

		wp_enqueue_style( 'no404-wizard', plugins_url( 'assets/wizard.css', NO404_PLUGIN_FILE ), array(), NO404_VERSION );

		if ( 'done' === $this->current_step() ) {
			No404_Admin::enqueue_test_script();
		}
	}

	/**
	 * Plugins screen: until the site is connected, point at the wizard.
	 *
	 * Only on the Plugins screen, so it does not follow the user around the
	 * admin. "Dismiss" records the wizard as skipped.
	 *
	 * @return void
	 */
	public function setup_notice() {
		global $pagenow;

		if ( 'plugins.php' !== $pagenow || ! current_user_can( 'manage_options' ) || self::is_completed() ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%1$s <a href="%2$s" class="button button-primary">%3$s</a> <a href="%4$s">%5$s</a></p></div>',
			esc_html__( 'no404 is installed but not connected yet.', 'no404-auto-404-redirect' ),
			esc_url( self::url() ),
			esc_html__( 'Run the setup wizard', 'no404-auto-404-redirect' ),
			esc_url( self::skip_url( 'notice' ) ),
			esc_html__( 'Dismiss', 'no404-auto-404-redirect' )
		);
	}

	/** admin-post: step 2. */
	public function handle_connect() {
		check_admin_referer( self::ACTION_CONNECT );
		self::require_capability();

		$raw    = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$result = $this->connect( $raw );

		self::flash( $result['type'], $result['message'] );
		self::go( $result['ok'] ? self::url( 'redirects' ) : self::url( 'connect' ) );
	}

	/** admin-post: step 3. */
	public function handle_behaviour() {
		check_admin_referer( self::ACTION_BEHAVIOUR );
		self::require_capability();

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$this->set_behaviour( 'force_301' === $mode );

		self::go( self::url( 'done' ) );
	}

	/** admin-post: "Skip setup" / "Dismiss". */
	public function handle_skip() {
		check_admin_referer( self::ACTION_SKIP );
		self::require_capability();

		self::mark_completed();

		$from = isset( $_GET['from'] ) ? sanitize_key( wp_unslash( $_GET['from'] ) ) : '';
		self::go( 'notice' === $from ? admin_url( 'plugins.php' ) : admin_url( 'options-general.php?page=' . No404_Admin::PAGE_SLUG ) );
	}

	/** Stops a request from a user who may not change settings. */
	protected static function require_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'no404-auto-404-redirect' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * @param string $url Where to go.
	 * @return void
	 */
	protected static function go( $url ) {
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Carries a message to the next page load (per user, one minute).
	 *
	 * @param string $type    success | error | warning.
	 * @param string $message Already translated text; empty = nothing to show.
	 * @return void
	 */
	protected static function flash( $type, $message ) {
		if ( '' === $message ) {
			return;
		}

		set_transient(
			self::NOTICE_TRANSIENT . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}

	/** @return bool Is the wizard page being rendered? */
	protected static function is_wizard_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return is_object( $screen ) && isset( $screen->id ) && self::SCREEN_ID === $screen->id;
	}

	/**
	 * The requested step. Steps after "Connect" need a saved key; without one the
	 * user lands on "Connect".
	 *
	 * @return string
	 */
	protected function current_step() {
		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'welcome'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.

		if ( ! in_array( $step, self::STEPS, true ) ) {
			$step = 'welcome';
		}
		if ( in_array( $step, array( 'redirects', 'done' ), true ) && '' === (string) No404_Options::get( 'api_key', '' ) ) {
			$step = 'connect';
		}

		return $step;
	}

	// ------------------------------------------------------------ rendering

	/** Renders the wizard. */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$step   = $this->current_step();
		$labels = array(
			'welcome'   => __( 'Welcome', 'no404-auto-404-redirect' ),
			'connect'   => __( 'Connect', 'no404-auto-404-redirect' ),
			'redirects' => __( 'Redirects', 'no404-auto-404-redirect' ),
			'done'      => __( 'Done', 'no404-auto-404-redirect' ),
		);
		$index  = (int) array_search( $step, self::STEPS, true );

		echo '<div class="no404-wizard-wrap">';
		echo '<p class="no404-brand">no404</p>';

		echo '<ol class="no404-steps">';
		foreach ( self::STEPS as $i => $slug ) {
			$class = $i < $index ? 'is-done' : ( $i === $index ? 'is-current' : '' );
			printf(
				'<li class="%1$s"%2$s>%3$s</li>',
				esc_attr( $class ),
				$i === $index ? ' aria-current="step"' : '',
				esc_html( $labels[ $slug ] )
			);
		}
		echo '</ol>';

		echo '<div class="no404-card">';
		$this->render_flash();

		switch ( $step ) {
			case 'connect':
				$this->render_step_connect();
				break;
			case 'redirects':
				$this->render_step_redirects();
				break;
			case 'done':
				$this->render_step_done();
				break;
			default:
				$this->render_step_welcome();
		}
		echo '</div>';

		if ( 'done' !== $step ) {
			printf(
				'<p class="no404-footer"><a href="%1$s">%2$s</a></p>',
				esc_url( self::skip_url() ),
				esc_html__( 'Skip setup', 'no404-auto-404-redirect' )
			);
		}

		echo '</div>';
	}

	/** Shows (and forgets) the message the previous form post left. */
	protected function render_flash() {
		$key   = self::NOTICE_TRANSIENT . get_current_user_id();
		$flash = get_transient( $key );
		if ( ! is_array( $flash ) || empty( $flash['message'] ) ) {
			return;
		}
		delete_transient( $key );

		$type = in_array( $flash['type'], array( 'success', 'error', 'warning' ), true ) ? $flash['type'] : 'info';
		printf(
			'<div class="notice notice-%1$s inline" role="status"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $flash['message'] )
		);
	}

	/** Step 1. */
	protected function render_step_welcome() {
		?>
		<h1><?php esc_html_e( 'Welcome to no404', 'no404-auto-404-redirect' ); ?></h1>
		<p class="no404-lead"><?php esc_html_e( 'When a visitor lands on a page that no longer exists, no404 finds the closest live page on your site and sends them there with a real server-side 301. There are no redirect rules to write.', 'no404-auto-404-redirect' ); ?></p>
		<p><?php esc_html_e( 'no404 is a hosted service, so the plugin needs a free no404 account and an API key. Setup takes about two minutes.', 'no404-auto-404-redirect' ); ?></p>
		<p class="no404-actions">
			<a class="button button-primary button-hero" href="<?php echo esc_url( self::signup_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Create a free account', 'no404-auto-404-redirect' ); ?></a>
			<a class="button button-hero" href="<?php echo esc_url( self::url( 'connect' ) ); ?>"><?php esc_html_e( 'I already have an account', 'no404-auto-404-redirect' ); ?></a>
		</p>
		<p class="description"><?php esc_html_e( 'The sign-up page opens in a new tab with this site\'s address filled in. Add the site there, then come back to this tab.', 'no404-auto-404-redirect' ); ?></p>
		<?php
	}

	/** Step 2. */
	protected function render_step_connect() {
		$masked = No404_Options::masked_key();
		?>
		<h1><?php esc_html_e( 'Connect your site', 'no404-auto-404-redirect' ); ?></h1>
		<p><?php esc_html_e( 'In your no404 dashboard, open this site and go to the Integration tab. Copy the API key from the WordPress card and paste it below.', 'no404-auto-404-redirect' ); ?></p>
		<p><a href="<?php echo esc_url( self::dashboard_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open the no404 dashboard', 'no404-auto-404-redirect' ); ?></a></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_CONNECT ); ?>" />
			<?php wp_nonce_field( self::ACTION_CONNECT ); ?>
			<p>
				<label for="no404-wizard-key" class="no404-label"><?php esc_html_e( 'API key', 'no404-auto-404-redirect' ); ?></label>
				<input type="text" id="no404-wizard-key" name="api_key" value="" class="large-text code" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr( '' !== $masked ? $masked : __( 'Paste your key', 'no404-auto-404-redirect' ) ); ?>" />
			</p>
			<?php if ( '' !== $masked ) : ?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: masked API key. */
							__( 'Saved key: %s — leave this field empty to keep it.', 'no404-auto-404-redirect' ),
							$masked
						)
					);
					?>
				</p>
			<?php endif; ?>
			<p class="no404-actions">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Connect', 'no404-auto-404-redirect' ); ?></button>
				<a class="button button-link" href="<?php echo esc_url( self::url( 'welcome' ) ); ?>"><?php esc_html_e( 'Back', 'no404-auto-404-redirect' ); ?></a>
			</p>
		</form>
		<?php
	}

	/** Step 3. */
	protected function render_step_redirects() {
		$force = ! empty( No404_Options::get( 'force_301' ) );
		?>
		<h1><?php esc_html_e( 'How should redirects be sent?', 'no404-auto-404-redirect' ); ?></h1>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_BEHAVIOUR ); ?>" />
			<?php wp_nonce_field( self::ACTION_BEHAVIOUR ); ?>
			<fieldset class="no404-choices">
				<label class="no404-choice">
					<input type="radio" name="mode" value="dashboard" <?php checked( ! $force ); ?> />
					<span class="no404-choice-title"><?php esc_html_e( 'Follow my no404 dashboard (recommended)', 'no404-auto-404-redirect' ); ?></span>
					<span class="no404-choice-desc"><?php esc_html_e( 'Confident matches and your own redirects get a permanent 301, uncertain guesses a temporary 302. You can change the threshold later in your no404 dashboard without touching WordPress.', 'no404-auto-404-redirect' ); ?></span>
				</label>
				<label class="no404-choice">
					<input type="radio" name="mode" value="force_301" <?php checked( $force ); ?> />
					<span class="no404-choice-title"><?php esc_html_e( 'Send every match as a 301 (permanent)', 'no404-auto-404-redirect' ); ?></span>
					<span class="no404-choice-desc"><?php esc_html_e( 'Only if your catalogue is complete: browsers cache a 301 permanently, so a wrong match cannot be taken back.', 'no404-auto-404-redirect' ); ?></span>
				</label>
			</fieldset>
			<p class="no404-actions">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Continue', 'no404-auto-404-redirect' ); ?></button>
				<a class="button button-link" href="<?php echo esc_url( self::url( 'connect' ) ); ?>"><?php esc_html_e( 'Back', 'no404-auto-404-redirect' ); ?></a>
			</p>
		</form>
		<?php
	}

	/** Step 4. Arriving here finishes the wizard. */
	protected function render_step_done() {
		self::mark_completed();

		$info = no404()->client()->site_info();
		?>
		<h1><?php esc_html_e( 'You are all set', 'no404-auto-404-redirect' ); ?></h1>
		<?php
		if ( 'ok' === $info['code'] ) {
			echo '<p class="no404-lead">' . esc_html(
				sprintf(
					/* translators: %s: the site's host, e.g. shop.example.com. */
					__( 'no404 is now redirecting pages that are not found on %s.', 'no404-auto-404-redirect' ),
					$info['host']
				)
			) . '</p>';

			$warning = self::serving_warning( $info );
			if ( '' !== $warning ) {
				echo '<div class="notice notice-warning inline"><p>' . esc_html( $warning ) . '</p></div>';
			}
		} elseif ( 'unsupported' === $info['code'] ) {
			echo '<p class="no404-lead">' . esc_html__( 'Active. Pages that are not found are redirected server-side.', 'no404-auto-404-redirect' ) . '</p>';
		} else {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( No404_Admin::describe( $info ) ) . '</p></div>';
		}
		?>
		<h2><?php esc_html_e( 'Try it with an old address', 'no404-auto-404-redirect' ); ?></h2>
		<p><?php esc_html_e( 'Enter an address that no longer exists on your site. The test counts as one lookup and shows up in your no404 dashboard.', 'no404-auto-404-redirect' ); ?></p>
		<p>
			<label for="no404-test-path" class="no404-label"><?php esc_html_e( 'Path to test', 'no404-auto-404-redirect' ); ?></label>
			<input type="text" id="no404-test-path" class="large-text code" value="" placeholder="/old-product" />
		</p>
		<p>
			<button type="button" class="button button-secondary" id="no404-test-button"><?php esc_html_e( 'Test the connection', 'no404-auto-404-redirect' ); ?></button>
		</p>
		<div id="no404-test-result" aria-live="polite"></div>

		<p class="no404-actions">
			<a class="button button-primary button-hero" href="<?php echo esc_url( self::dashboard_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open the no404 dashboard', 'no404-auto-404-redirect' ); ?></a>
			<a class="button button-hero" href="<?php echo esc_url( admin_url( 'options-general.php?page=' . No404_Admin::PAGE_SLUG ) ); ?>"><?php esc_html_e( 'Go to settings', 'no404-auto-404-redirect' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Why no404 would not redirect for this site right now ('' when it would).
	 *
	 * @param array $info Output of No404_Client::site_info.
	 * @return string
	 */
	public static function serving_warning( array $info ) {
		if ( ! empty( $info['serving'] ) ) {
			return '';
		}

		switch ( isset( $info['reason'] ) ? $info['reason'] : '' ) {
			case 'paused':
				return __( 'Monitoring for this site is paused in the no404 dashboard. Resume it there to start redirecting.', 'no404-auto-404-redirect' );
			case 'inactive':
				return __( 'Your no404 subscription is not active, so nothing is redirected yet. Check your plan in the no404 dashboard.', 'no404-auto-404-redirect' );
			case 'quota':
				return __( 'Your monthly lookup quota is used up. Redirects start again when it resets, or you can upgrade your plan in the no404 dashboard.', 'no404-auto-404-redirect' );
			case 'suspended':
				return __( 'Your no404 account is suspended. Contact no404 support.', 'no404-auto-404-redirect' );
			default:
				return '';
		}
	}
}
