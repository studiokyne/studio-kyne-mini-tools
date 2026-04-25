<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Module_Security implements SKMT_Module_Interface {
	protected $plugin;
	protected $option_name = 'skmt_security_settings';
	protected $rewrite_flush_flag = 'skmt_security_flush_rewrite';
	protected $defaults = array(
		'enabled'                      => 1,
		'custom_login_enabled'         => 0,
		'custom_login_slug'            => 'connexion',
		'block_default_login'          => 0,
		'limit_login_attempts'         => 1,
		'max_login_attempts'           => 5,
		'lockout_minutes'              => 20,
		'force_strong_password'        => 1,
		'disable_public_registration'  => 1,
		'disable_xmlrpc'               => 1,
		'prevent_user_enumeration'     => 1,
		'hide_wp_version'              => 1,
	);

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function get_id() { return 'security'; }
	public function get_name() { return __( 'Securite', 'studio-kyne-mini-tools' ); }
	public function get_description() { return __( 'Renforce l authentification et le hardening WordPress.', 'studio-kyne-mini-tools' ); }
	public function get_icon() { return 'shield'; }
	public function is_default_active() { return false; }
	public function is_configurable() { return true; }

	public function activate() {
		$this->maybe_seed_defaults();
		$settings = $this->get_settings();
		if ( ! empty( $settings['custom_login_enabled'] ) ) {
			update_option( $this->rewrite_flush_flag, 1, false );
		}
	}

	public function deactivate() {
		delete_option( $this->rewrite_flush_flag );
	}

	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'init', array( $this, 'register_custom_login_rewrite' ), 20 );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 30 );
		add_filter( 'query_vars', array( $this, 'filter_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_custom_login' ), 1 );
		add_action( 'login_init', array( $this, 'maybe_block_default_login_url' ), 1 );
		add_filter( 'site_url', array( $this, 'filter_site_login_url' ), 10, 4 );
		add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 3 );
		add_filter( 'lostpassword_url', array( $this, 'filter_lostpassword_url' ), 10, 2 );
		add_filter( 'register_url', array( $this, 'filter_register_url' ) );

		add_filter( 'authenticate', array( $this, 'filter_authenticate_attempts' ), 30, 3 );
		add_action( 'wp_login_failed', array( $this, 'handle_login_failed' ) );
		add_action( 'wp_login', array( $this, 'handle_login_success' ), 10, 2 );
		add_action( 'user_profile_update_errors', array( $this, 'validate_profile_password' ), 10, 3 );
		add_action( 'validate_password_reset', array( $this, 'validate_reset_password' ), 10, 2 );

		add_filter( 'pre_option_users_can_register', array( $this, 'filter_users_can_register' ) );
		add_filter( 'xmlrpc_enabled', array( $this, 'filter_xmlrpc_enabled' ) );
		add_action( 'template_redirect', array( $this, 'maybe_block_user_enumeration' ), 2 );
		add_filter( 'rest_request_before_callbacks', array( $this, 'filter_rest_user_endpoints' ), 10, 3 );
		add_filter( 'the_generator', array( $this, 'filter_wp_generator' ) );
		add_filter( 'script_loader_src', array( $this, 'filter_asset_src' ), 10, 2 );
		add_filter( 'style_loader_src', array( $this, 'filter_asset_src' ), 10, 2 );
	}

	public function register_admin_pages( $parent_slug ) {
		add_submenu_page(
			$parent_slug,
			__( 'Securite', 'studio-kyne-mini-tools' ),
			__( 'Securite', 'studio-kyne-mini-tools' ),
			SKMT_Capabilities::admin_capability(),
			'skmt-security',
			array( $this, 'render_admin_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'skmt_security_group',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->defaults,
			)
		);
	}

	public function get_settings() {
		$stored = get_option( $this->option_name, array() );
		$data   = wp_parse_args( is_array( $stored ) ? $stored : array(), $this->defaults );

		$bool_keys = array(
			'enabled',
			'custom_login_enabled',
			'block_default_login',
			'limit_login_attempts',
			'force_strong_password',
			'disable_public_registration',
			'disable_xmlrpc',
			'prevent_user_enumeration',
			'hide_wp_version',
		);

		foreach ( $bool_keys as $key ) {
			$data[ $key ] = empty( $data[ $key ] ) ? 0 : 1;
		}

		$data['enabled']            = 1;
		$data['max_login_attempts'] = max( 3, min( 20, absint( $data['max_login_attempts'] ) ) );
		$data['lockout_minutes']    = max( 1, min( 1440, absint( $data['lockout_minutes'] ) ) );
		$data['custom_login_slug']  = $this->sanitize_login_slug( (string) $data['custom_login_slug'] );

		if ( '' === $data['custom_login_slug'] ) {
			$data['custom_login_slug'] = $this->defaults['custom_login_slug'];
		}

		return $data;
	}

	public function sanitize_settings( $input ) {
		$current = $this->get_settings();
		$input   = is_array( $input ) ? $input : array();

		$updated = $current;
		$updated['enabled']                      = 1;
		$updated['custom_login_enabled']         = empty( $input['custom_login_enabled'] ) ? 0 : 1;
		$updated['block_default_login']          = empty( $input['block_default_login'] ) ? 0 : 1;
		$updated['limit_login_attempts']         = empty( $input['limit_login_attempts'] ) ? 0 : 1;
		$updated['force_strong_password']        = empty( $input['force_strong_password'] ) ? 0 : 1;
		$updated['disable_public_registration']  = empty( $input['disable_public_registration'] ) ? 0 : 1;
		$updated['disable_xmlrpc']               = empty( $input['disable_xmlrpc'] ) ? 0 : 1;
		$updated['prevent_user_enumeration']     = empty( $input['prevent_user_enumeration'] ) ? 0 : 1;
		$updated['hide_wp_version']              = empty( $input['hide_wp_version'] ) ? 0 : 1;
		$updated['max_login_attempts']           = max( 3, min( 20, absint( $input['max_login_attempts'] ?? $current['max_login_attempts'] ) ) );
		$updated['lockout_minutes']              = max( 1, min( 1440, absint( $input['lockout_minutes'] ?? $current['lockout_minutes'] ) ) );
		$updated['custom_login_slug']            = $this->sanitize_login_slug( (string) ( $input['custom_login_slug'] ?? $current['custom_login_slug'] ) );

		if ( '' === $updated['custom_login_slug'] ) {
			$updated['custom_login_slug'] = $this->defaults['custom_login_slug'];
		}

		if (
			$current['custom_login_enabled'] !== $updated['custom_login_enabled'] ||
			$current['custom_login_slug'] !== $updated['custom_login_slug']
		) {
			update_option( $this->rewrite_flush_flag, 1, false );
		}

		return $updated;
	}

	public function render_admin_page() {
		if ( ! SKMT_Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Acces refuse.', 'studio-kyne-mini-tools' ) );
		}

		$settings = $this->get_settings();
		$login_url = $this->build_custom_login_url( $settings );

		?>
		<div class="wrap skmt-wrap">
			<div class="skmt-shell">
				<header class="skmt-page-head">
					<div>
						<h1><?php echo esc_html__( 'Module Securite', 'studio-kyne-mini-tools' ); ?></h1>
						<p><?php echo esc_html__( 'Controlez les protections auth et hardening en gardant une granularite par fonctionnalite.', 'studio-kyne-mini-tools' ); ?></p>
					</div>
					<span class="skmt-badge skmt-badge--success"><?php echo esc_html__( 'Actif', 'studio-kyne-mini-tools' ); ?></span>
				</header>

				<div class="skmt-card">
					<form action="options.php" method="post">
						<?php settings_fields( 'skmt_security_group' ); ?>

						<h2><?php echo esc_html__( 'Authentification', 'studio-kyne-mini-tools' ); ?></h2>

						<div class="skmt-field">
							<label class="skmt-toggle">
								<input type="checkbox" name="skmt_security_settings[custom_login_enabled]" value="1" <?php checked( ! empty( $settings['custom_login_enabled'] ) ); ?> />
								<span></span>
								<strong><?php echo esc_html__( 'Activer une URL de connexion personnalisee', 'studio-kyne-mini-tools' ); ?></strong>
							</label>
						</div>

						<div class="skmt-field">
							<label for="skmt-security-login-slug"><strong><?php echo esc_html__( 'Slug de connexion', 'studio-kyne-mini-tools' ); ?></strong></label>
							<input id="skmt-security-login-slug" type="text" name="skmt_security_settings[custom_login_slug]" value="<?php echo esc_attr( (string) $settings['custom_login_slug'] ); ?>" />
							<p class="description"><?php echo esc_html__( 'URL generee:', 'studio-kyne-mini-tools' ); ?> <a href="<?php echo esc_url( $login_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $login_url ); ?></a></p>
						</div>

						<div class="skmt-field">
							<label class="skmt-toggle">
								<input type="checkbox" name="skmt_security_settings[block_default_login]" value="1" <?php checked( ! empty( $settings['block_default_login'] ) ); ?> />
								<span></span>
								<strong><?php echo esc_html__( 'Bloquer la page wp-login.php par defaut', 'studio-kyne-mini-tools' ); ?></strong>
							</label>
							<p class="description"><?php echo esc_html__( 'A activer seulement apres validation de l URL personnalisee.', 'studio-kyne-mini-tools' ); ?></p>
						</div>

						<div class="skmt-field">
							<label class="skmt-toggle">
								<input type="checkbox" name="skmt_security_settings[limit_login_attempts]" value="1" <?php checked( ! empty( $settings['limit_login_attempts'] ) ); ?> />
								<span></span>
								<strong><?php echo esc_html__( 'Limiter les tentatives de connexion', 'studio-kyne-mini-tools' ); ?></strong>
							</label>
						</div>

						<div class="skmt-grid skmt-grid--2">
							<div class="skmt-field">
								<label for="skmt-security-max-attempts"><strong><?php echo esc_html__( 'Tentatives max', 'studio-kyne-mini-tools' ); ?></strong></label>
								<input id="skmt-security-max-attempts" type="number" min="3" max="20" name="skmt_security_settings[max_login_attempts]" value="<?php echo esc_attr( (string) $settings['max_login_attempts'] ); ?>" />
							</div>
							<div class="skmt-field">
								<label for="skmt-security-lockout-minutes"><strong><?php echo esc_html__( 'Duree de blocage (minutes)', 'studio-kyne-mini-tools' ); ?></strong></label>
								<input id="skmt-security-lockout-minutes" type="number" min="1" max="1440" name="skmt_security_settings[lockout_minutes]" value="<?php echo esc_attr( (string) $settings['lockout_minutes'] ); ?>" />
							</div>
						</div>

						<div class="skmt-field">
							<label class="skmt-toggle">
								<input type="checkbox" name="skmt_security_settings[force_strong_password]" value="1" <?php checked( ! empty( $settings['force_strong_password'] ) ); ?> />
								<span></span>
								<strong><?php echo esc_html__( 'Forcer un mot de passe fort', 'studio-kyne-mini-tools' ); ?></strong>
							</label>
							<p class="description"><?php echo esc_html__( 'Regle: 12 caracteres min, majuscule, minuscule, chiffre et caractere special.', 'studio-kyne-mini-tools' ); ?></p>
						</div>

						<div class="skmt-field">
							<label class="skmt-toggle">
								<input type="checkbox" name="skmt_security_settings[disable_public_registration]" value="1" <?php checked( ! empty( $settings['disable_public_registration'] ) ); ?> />
								<span></span>
								<strong><?php echo esc_html__( 'Desactiver l inscription publique', 'studio-kyne-mini-tools' ); ?></strong>
							</label>
						</div>

						<hr />

						<h2><?php echo esc_html__( 'Hardening', 'studio-kyne-mini-tools' ); ?></h2>

						<div class="skmt-field">
							<label class="skmt-toggle">
								<input type="checkbox" name="skmt_security_settings[disable_xmlrpc]" value="1" <?php checked( ! empty( $settings['disable_xmlrpc'] ) ); ?> />
								<span></span>
								<strong><?php echo esc_html__( 'Desactiver XML-RPC', 'studio-kyne-mini-tools' ); ?></strong>
							</label>
						</div>

						<div class="skmt-field">
							<label class="skmt-toggle">
								<input type="checkbox" name="skmt_security_settings[prevent_user_enumeration]" value="1" <?php checked( ! empty( $settings['prevent_user_enumeration'] ) ); ?> />
								<span></span>
								<strong><?php echo esc_html__( 'Empecher l enumeration des utilisateurs', 'studio-kyne-mini-tools' ); ?></strong>
							</label>
						</div>

						<div class="skmt-field">
							<label class="skmt-toggle">
								<input type="checkbox" name="skmt_security_settings[hide_wp_version]" value="1" <?php checked( ! empty( $settings['hide_wp_version'] ) ); ?> />
								<span></span>
								<strong><?php echo esc_html__( 'Masquer la version WordPress', 'studio-kyne-mini-tools' ); ?></strong>
							</label>
						</div>

						<?php submit_button( __( 'Enregistrer les reglages', 'studio-kyne-mini-tools' ) ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public function register_custom_login_rewrite() {
		$settings = $this->get_settings();
		if ( ! $this->is_custom_login_enabled( $settings ) ) {
			return;
		}

		$slug = trim( (string) $settings['custom_login_slug'], '/' );
		if ( '' === $slug ) {
			return;
		}

		add_rewrite_rule( '^' . preg_quote( $slug, '#' ) . '/?$', 'index.php?skmt_custom_login=1', 'top' );
	}

	public function maybe_flush_rewrite_rules() {
		if ( ! get_option( $this->rewrite_flush_flag, false ) ) {
			return;
		}

		flush_rewrite_rules( false );
		delete_option( $this->rewrite_flush_flag );
	}

	public function filter_query_vars( $vars ) {
		$vars[] = 'skmt_custom_login';
		return $vars;
	}

	public function maybe_render_custom_login() {
		$settings = $this->get_settings();
		if ( ! $this->is_custom_login_enabled( $settings ) ) {
			return;
		}

		if ( 1 !== absint( get_query_var( 'skmt_custom_login' ) ) ) {
			return;
		}

		$GLOBALS['skmt_security_custom_login_request'] = true;
		nocache_headers();
		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	public function maybe_block_default_login_url() {
		$settings = $this->get_settings();
		if ( ! $this->is_custom_login_enabled( $settings ) || empty( $settings['block_default_login'] ) ) {
			return;
		}

		if ( ! empty( $GLOBALS['skmt_security_custom_login_request'] ) ) {
			return;
		}

		$script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? wp_basename( sanitize_text_field( wp_unslash( (string) $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		if ( 'wp-login.php' !== $script_name ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : 'login';
		if ( in_array( $action, array( 'logout', 'lostpassword', 'retrievepassword', 'rp', 'resetpass', 'postpass' ), true ) ) {
			return;
		}

		wp_safe_redirect( home_url( '/' ), 302 );
		exit;
	}

	public function filter_site_login_url( $url, $path, $scheme, $blog_id ) {
		$settings = $this->get_settings();
		if ( ! $this->is_custom_login_enabled( $settings ) ) {
			return $url;
		}

		if ( ! in_array( $scheme, array( 'login', 'login_post' ), true ) ) {
			return $url;
		}

		if ( false === strpos( (string) $url, 'wp-login.php' ) ) {
			return $url;
		}

		$target = $this->build_custom_login_url( $settings );
		$query  = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! empty( $query ) ) {
			parse_str( (string) $query, $args );
			$target = add_query_arg( $args, $target );
		}

		return $target;
	}

	public function filter_login_url( $login_url, $redirect, $force_reauth ) {
		$settings = $this->get_settings();
		if ( ! $this->is_custom_login_enabled( $settings ) ) {
			return $login_url;
		}

		$args = array();
		if ( ! empty( $redirect ) ) {
			$args['redirect_to'] = $redirect;
		}
		if ( $force_reauth ) {
			$args['reauth'] = '1';
		}

		return $this->build_custom_login_url( $settings, $args );
	}

	public function filter_lostpassword_url( $lostpassword_url, $redirect ) {
		$settings = $this->get_settings();
		if ( ! $this->is_custom_login_enabled( $settings ) ) {
			return $lostpassword_url;
		}

		$args = array( 'action' => 'lostpassword' );
		if ( ! empty( $redirect ) ) {
			$args['redirect_to'] = $redirect;
		}

		return $this->build_custom_login_url( $settings, $args );
	}

	public function filter_register_url( $register_url ) {
		$settings = $this->get_settings();
		if ( ! $this->is_custom_login_enabled( $settings ) ) {
			return $register_url;
		}

		return $this->build_custom_login_url( $settings, array( 'action' => 'register' ) );
	}

	public function filter_authenticate_attempts( $user, $username, $password ) {
		$settings = $this->get_settings();
		if ( empty( $settings['limit_login_attempts'] ) ) {
			return $user;
		}

		$ip = $this->get_client_ip();
		if ( '' === $ip ) {
			return $user;
		}

		$state = $this->get_attempt_state( $ip );
		$lock_until = absint( $state['lock_until'] ?? 0 );
		if ( $lock_until > time() ) {
			$remaining = max( 1, (int) ceil( ( $lock_until - time() ) / MINUTE_IN_SECONDS ) );
			return new WP_Error(
				'skmt_security_locked',
				sprintf(
					/* translators: %d: number of minutes. */
					__( 'Trop de tentatives. Reessayez dans %d minute(s).', 'studio-kyne-mini-tools' ),
					$remaining
				)
			);
		}

		return $user;
	}

	public function handle_login_failed( $username ) {
		$settings = $this->get_settings();
		if ( empty( $settings['limit_login_attempts'] ) ) {
			return;
		}

		$ip = $this->get_client_ip();
		if ( '' === $ip ) {
			return;
		}

		$state = $this->get_attempt_state( $ip );
		$attempts = absint( $state['attempts'] ?? 0 ) + 1;
		$lock_until = 0;

		if ( $attempts >= absint( $settings['max_login_attempts'] ) ) {
			$attempts = 0;
			$lock_until = time() + ( absint( $settings['lockout_minutes'] ) * MINUTE_IN_SECONDS );
		}

		$this->set_attempt_state( $ip, $attempts, $lock_until, absint( $settings['lockout_minutes'] ) );
	}

	public function handle_login_success( $user_login, $user ) {
		$settings = $this->get_settings();
		if ( empty( $settings['limit_login_attempts'] ) ) {
			return;
		}

		$ip = $this->get_client_ip();
		if ( '' === $ip ) {
			return;
		}

		delete_transient( $this->get_attempt_key( $ip ) );
	}

	public function validate_profile_password( $errors, $update, $user ) {
		$settings = $this->get_settings();
		if ( empty( $settings['force_strong_password'] ) ) {
			return;
		}

		$password = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		if ( '' === $password || '********' === $password ) {
			return;
		}

		if ( ! $this->is_strong_password( $password ) ) {
			$errors->add( 'skmt_weak_password', __( 'Mot de passe trop faible. Utilisez 12 caracteres minimum avec majuscule, minuscule, chiffre et symbole.', 'studio-kyne-mini-tools' ) );
		}
	}

	public function validate_reset_password( $errors, $user ) {
		$settings = $this->get_settings();
		if ( empty( $settings['force_strong_password'] ) ) {
			return;
		}

		$password = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		if ( '' === $password ) {
			return;
		}

		if ( ! $this->is_strong_password( $password ) ) {
			$errors->add( 'skmt_weak_password', __( 'Mot de passe trop faible. Utilisez 12 caracteres minimum avec majuscule, minuscule, chiffre et symbole.', 'studio-kyne-mini-tools' ) );
		}
	}

	public function filter_users_can_register( $value ) {
		$settings = $this->get_settings();
		if ( empty( $settings['disable_public_registration'] ) ) {
			return $value;
		}

		return 0;
	}

	public function filter_xmlrpc_enabled( $enabled ) {
		$settings = $this->get_settings();
		if ( empty( $settings['disable_xmlrpc'] ) ) {
			return $enabled;
		}

		return false;
	}

	public function maybe_block_user_enumeration() {
		$settings = $this->get_settings();
		if ( empty( $settings['prevent_user_enumeration'] ) ) {
			return;
		}

		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$has_author_query = isset( $_GET['author'] ) && '' !== (string) $_GET['author'];
		$has_author_path  = '' !== $request_uri && preg_match( '#/author/[^/?]+#i', $request_uri );

		if ( $has_author_query || $has_author_path ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}
	}

	public function filter_rest_user_endpoints( $response, $handler, $request ) {
		$settings = $this->get_settings();
		if ( empty( $settings['prevent_user_enumeration'] ) ) {
			return $response;
		}

		if ( SKMT_Capabilities::current_user_can_manage() ) {
			return $response;
		}

		if ( ! $request instanceof WP_REST_Request ) {
			return $response;
		}

		$route = (string) $request->get_route();
		if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
			return new WP_Error( 'skmt_rest_users_blocked', __( 'Acces refuse.', 'studio-kyne-mini-tools' ), array( 'status' => 403 ) );
		}

		return $response;
	}

	public function filter_wp_generator( $generator ) {
		$settings = $this->get_settings();
		if ( empty( $settings['hide_wp_version'] ) ) {
			return $generator;
		}

		return '';
	}

	public function filter_asset_src( $src, $handle ) {
		$settings = $this->get_settings();
		if ( empty( $settings['hide_wp_version'] ) || empty( $src ) ) {
			return $src;
		}

		$parsed = wp_parse_url( $src );
		if ( ! is_array( $parsed ) || empty( $parsed['query'] ) ) {
			return $src;
		}

		parse_str( (string) $parsed['query'], $query_args );
		if ( ! isset( $query_args['ver'] ) ) {
			return $src;
		}

		unset( $query_args['ver'] );
		$clean = strtok( $src, '?' );
		if ( empty( $query_args ) ) {
			return (string) $clean;
		}

		return add_query_arg( $query_args, (string) $clean );
	}

	protected function maybe_seed_defaults() {
		if ( false === get_option( $this->option_name, false ) ) {
			add_option( $this->option_name, $this->defaults );
		}
	}

	protected function is_custom_login_enabled( $settings ) {
		return ! empty( $settings['custom_login_enabled'] ) && ! empty( $settings['custom_login_slug'] );
	}

	protected function sanitize_login_slug( $slug ) {
		$slug = sanitize_title_with_dashes( (string) $slug );
		$slug = trim( $slug, '/' );
		if ( '' === $slug ) {
			return '';
		}

		$slug = preg_replace( '/[^a-z0-9\-]/', '', $slug );
		return trim( (string) $slug, '-' );
	}

	protected function build_custom_login_url( $settings, $args = array() ) {
		$slug = trim( (string) $settings['custom_login_slug'], '/' );
		$url  = home_url( '/' . $slug . '/' );
		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		return $url;
	}

	protected function is_strong_password( $password ) {
		$password = (string) $password;
		if ( strlen( $password ) < 12 ) {
			return false;
		}

		if ( ! preg_match( '/[a-z]/', $password ) ) {
			return false;
		}

		if ( ! preg_match( '/[A-Z]/', $password ) ) {
			return false;
		}

		if ( ! preg_match( '/\d/', $password ) ) {
			return false;
		}

		if ( ! preg_match( '/[^a-zA-Z0-9]/', $password ) ) {
			return false;
		}

		return true;
	}

	protected function get_attempt_key( $ip ) {
		return 'skmt_sec_login_' . md5( (string) $ip );
	}

	protected function get_attempt_state( $ip ) {
		$key = $this->get_attempt_key( $ip );
		$state = get_transient( $key );
		if ( ! is_array( $state ) ) {
			return array(
				'attempts'   => 0,
				'lock_until' => 0,
			);
		}

		return array(
			'attempts'   => absint( $state['attempts'] ?? 0 ),
			'lock_until' => absint( $state['lock_until'] ?? 0 ),
		);
	}

	protected function set_attempt_state( $ip, $attempts, $lock_until, $lockout_minutes ) {
		$key = $this->get_attempt_key( $ip );
		$ttl = max( HOUR_IN_SECONDS, ( absint( $lockout_minutes ) * MINUTE_IN_SECONDS ) + HOUR_IN_SECONDS );

		set_transient(
			$key,
			array(
				'attempts'   => absint( $attempts ),
				'lock_until' => absint( $lock_until ),
			),
			$ttl
		);
	}

	protected function get_client_ip() {
		$keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$raw = sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) );
			if ( 'HTTP_X_FORWARDED_FOR' === $key ) {
				$parts = explode( ',', $raw );
				$raw = trim( (string) ( $parts[0] ?? '' ) );
			}

			if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
				return $raw;
			}
		}

		return '';
	}
}
