<?php
namespace StudioKyne\MiniTools\Modules\Login;

use StudioKyne\MiniTools\Core\AbstractModule;

/**
 * Module Connexion — personnalisation de la page de connexion WordPress.
 */
class Module extends AbstractModule {

	/**
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * Constructeur.
	 */
	public function __construct( string $id ) {
		parent::__construct( $id );
	}

	/**
	 * Initialise les hooks WordPress.
	 */
	public function init(): void {
		$this->settings = $this->get_settings();

		add_action( 'login_enqueue_scripts',        [ $this, 'enqueue_login_assets' ] );
		add_action( 'login_head',                   [ $this, 'inject_css_variables' ] );
		add_filter( 'login_headerurl',              [ $this, 'filter_logo_url' ] );
		add_filter( 'login_headertext',             [ $this, 'filter_logo_text' ] );
		add_filter( 'login_body_class',             [ $this, 'add_body_class' ] );
		add_action( 'login_footer',                 [ $this, 'render_side_panel' ] );
		add_action( 'login_footer',                 [ $this, 'render_login_dom_tweaks' ], 20 );

		if ( ! empty( $this->settings['form']['hide_language_switcher'] ) ) {
			add_filter( 'login_display_language_dropdown', '__return_false' );
		}

		if ( ! empty( $this->settings['form']['hide_lost_password'] ) ) {
			add_action( 'login_head', [ $this, 'hide_lost_password_css' ], 99 );
		}

		if ( ! empty( $this->settings['form']['hide_back_to_blog'] ) ) {
			add_action( 'login_head', [ $this, 'hide_back_to_blog_css' ], 99 );
		}

		// Charge le media uploader WP sur la page de réglages du module.
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_media' ] );
	}

	/**
	 * Enqueue le CSS de la page de connexion.
	 */
	public function enqueue_login_assets(): void {
		wp_enqueue_style(
			'skmt-login-css',
			SKMT_ASSETS_URL . 'login/css/login.css',
			[],
			SKMT_VERSION
		);
	}

	/**
	 * Injecte les variables CSS custom dans <head> de la page de connexion.
	 */
	public function inject_css_variables(): void {
		$s = $this->settings;

		$bg_color       = $this->sanitize_color( $s['form']['bg_color']       ?? '#f7f7f7' );
		$panel_bg       = $this->sanitize_color( $s['layout']['panel_bg_color'] ?? '#eaeaea' );
		$btn_bg         = $this->sanitize_color( $s['form']['btn_bg_color']    ?? '#615FFF' );
		$btn_color      = $this->sanitize_color( $s['form']['btn_text_color']  ?? '#ffffff' );
		$link_color     = $this->sanitize_color( $s['form']['link_color']      ?? '#615FFF' );
		$logo_width     = absint( $s['branding']['logo_width'] ?? 150 );

		// Image du panneau
		$panel_img_url = '';
		$panel_img_id  = absint( $s['layout']['panel_image_id'] ?? 0 );
		if ( $panel_img_id > 0 ) {
			$src = wp_get_attachment_image_url( $panel_img_id, 'full' );
			if ( $src ) {
				$panel_img_url = esc_url( $src );
			}
		}

		// Logo custom
		$logo_url = '';
		$logo_id  = absint( $s['branding']['logo_id'] ?? 0 );
		if ( $logo_id > 0 ) {
			$src = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $src ) {
				$logo_url = esc_url( $src );
			}
		}

		echo '<style id="skmt-login-vars">';
		echo ':root{';
		echo '--skmt-l-bg:' . esc_html( $bg_color ) . ';';
		echo '--skmt-l-panel-bg:' . esc_html( $panel_bg ) . ';';
		echo '--skmt-l-btn-bg:' . esc_html( $btn_bg ) . ';';
		echo '--skmt-l-btn-color:' . esc_html( $btn_color ) . ';';
		echo '--skmt-l-link:' . esc_html( $link_color ) . ';';
		echo '--skmt-l-logo-width:' . $logo_width . 'px;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( $panel_img_url ) {
			echo '--skmt-l-panel-img:url(' . $panel_img_url . ');'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '--skmt-l-panel-img:none;';
		}

		if ( $logo_url ) {
			echo '--skmt-l-logo-url:url(' . $logo_url . ');'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '--skmt-l-logo-display:block;';
		} else {
			echo '--skmt-l-logo-url:none;';
			echo '--skmt-l-logo-display:none;';
		}

		echo '}';
		echo '</style>';
	}

	/**
	 * Injecte un style pour masquer le lien "Mot de passe oublié".
	 */
	public function hide_lost_password_css(): void {
		echo '<style>#nav{display:none!important}</style>';
	}

	/**
	 * Injecte un style pour masquer le lien "Aller à NOM DU SITE".
	 */
	public function hide_back_to_blog_css(): void {
		echo '<style>#backtoblog{display:none!important}</style>';
	}

	/**
	 * Remplace l'URL du logo par l'accueil du site.
	 */
	public function filter_logo_url( string $url ): string {
		return home_url( '/' );
	}

	/**
	 * Remplace le texte alternatif du logo par le nom du site.
	 */
	public function filter_logo_text( string $text ): string {
		return get_bloginfo( 'name' );
	}

	/**
	 * Ajoute la classe CSS pour le layout split.
	 *
	 * @param string[] $classes
	 * @return string[]
	 */
	public function add_body_class( array $classes ): array {
		$classes[] = 'skmt-login-split';
		$logo_id = absint( $this->settings['branding']['logo_id'] ?? 0 );
		if ( $logo_id > 0 ) {
			$classes[] = 'skmt-has-logo';
		}
		return $classes;
	}

	/**
	 * Injecte le panneau image/couleur côté droit après le formulaire.
	 */
	public function render_side_panel(): void {
		echo '<div class="skmt-login-panel" aria-hidden="true"></div>';
	}

	/**
	 * Injecte les tweaks DOM JS de la page de connexion :
	 * - Titre "Se connecter" entre logo et formulaire
	 * - Password header (label + lien MDP oublié en space-between)
	 * - Réordonnancement bouton → "Se souvenir de moi"
	 */
	public function render_login_dom_tweaks(): void {
		?>
		<script>
		(function() {
			var loginform    = document.querySelector('#loginform');
			var userPassWrap = document.querySelector('.user-pass-wrap');
			var passwordLabel = userPassWrap ? userPassWrap.querySelector('label[for="user_pass"]') : null;
			var nav          = document.querySelector('#nav');
			var navLink      = nav ? nav.querySelector('a') : null;
			var forgetmenot  = document.querySelector('.forgetmenot');
			var submit       = document.querySelector('#loginform .submit');
			var h1           = document.querySelector('#login h1');

			if (h1) {
				var title = document.createElement('p');
				title.className = 'skmt-login-title';
				title.textContent = '<?php echo esc_js( __( 'Se connecter', 'studio-kyne-mini-tools' ) ); ?>';
				h1.insertAdjacentElement('afterend', title);
			}

			if (userPassWrap && passwordLabel) {
				var passHeader = document.createElement('div');
				passHeader.className = 'skmt-pass-header';
				passHeader.appendChild(passwordLabel.cloneNode(true));
				var navIsHidden = nav && window.getComputedStyle(nav).display === 'none';
				if (navLink && !navIsHidden) {
					passHeader.appendChild(navLink.cloneNode(true));
					if (nav) nav.classList.add('skmt-nav-hidden');
				}
				userPassWrap.parentElement.insertBefore(passHeader, userPassWrap);
				passwordLabel.style.display = 'none';
			}

			if (loginform && submit && forgetmenot) {
				loginform.insertBefore(submit, forgetmenot);
			}
		})();
		</script>
		<?php
	}

	/**
	 * Charge wp_enqueue_media() uniquement sur la page de réglages du module.
	 */
	public function maybe_enqueue_media( string $hook ): void {
		if ( strpos( $hook, 'studio-kyne-mini-tools' ) === false ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
		if ( 'module_login' === $tab ) {
			wp_enqueue_media();
		}
	}

	/* ================================================================
	 * SETTINGS
	 * ================================================================ */

	public function get_settings(): array {
		return $this->get_module_settings( static::get_defaults() );
	}

	/**
	 * Valide et sauvegarde les settings.
	 */
	public function save_settings( array $settings ): bool {
		$current = $this->get_module_settings( static::get_defaults() );

		// Layout
		if ( isset( $settings['layout'] ) && is_array( $settings['layout'] ) ) {
			$current['layout']['panel_image_id'] = absint( $settings['layout']['panel_image_id'] ?? 0 );
			$current['layout']['panel_bg_color']  = $this->sanitize_color( $settings['layout']['panel_bg_color'] ?? '', '#16213e' );
		}

		// Branding
		if ( isset( $settings['branding'] ) && is_array( $settings['branding'] ) ) {
			$current['branding']['logo_id']    = absint( $settings['branding']['logo_id'] ?? 0 );
			$current['branding']['logo_width'] = min( 600, max( 40, absint( $settings['branding']['logo_width'] ?? 150 ) ) );
		}

		// Formulaire
		if ( isset( $settings['form'] ) && is_array( $settings['form'] ) ) {
			$current['form']['hide_language_switcher'] = ! empty( $settings['form']['hide_language_switcher'] );
			$current['form']['hide_lost_password']     = ! empty( $settings['form']['hide_lost_password'] );
			$current['form']['hide_back_to_blog']      = ! empty( $settings['form']['hide_back_to_blog'] );
			$current['form']['bg_color']               = $this->sanitize_color( $settings['form']['bg_color']      ?? '', '#f7f7f7' );
			$current['form']['btn_bg_color']           = $this->sanitize_color( $settings['form']['btn_bg_color']  ?? '', '#615FFF' );
			$current['form']['btn_text_color']         = $this->sanitize_color( $settings['form']['btn_text_color'] ?? '', '#ffffff' );
			$current['form']['link_color']             = $this->sanitize_color( $settings['form']['link_color']    ?? '', '#615FFF' );
		}

		$this->settings = $current;
		return $this->save_module_settings( $current );
	}

	/**
	 * Valeurs par défaut des settings.
	 */
	public static function get_defaults(): array {
		return [
			'layout'  => [
				'panel_image_id' => 0,
				'panel_bg_color' => '#eaeaea',
			],
			'branding' => [
				'logo_id'    => 0,
				'logo_width' => 150,
			],
			'form' => [
				'hide_language_switcher' => true,
				'hide_lost_password'     => false,
				'hide_back_to_blog'      => true,
				'bg_color'               => '#f7f7f7',
				'btn_bg_color'           => '#615FFF',
				'btn_text_color'         => '#ffffff',
				'link_color'             => '#615FFF',
			],
		];
	}

	/**
	 * Clés à supprimer lors de la désinstallation.
	 */
	public static function get_uninstall_keys(): array {
		return [
			'options' => [ 'skmt_module_login' ],
			'meta'    => [],
		];
	}

	/**
	 * Assets CSS pour la page de réglages admin.
	 */
	public function get_admin_css(): array {
		return [ SKMT_ASSETS_URL . 'admin/css/modules/login.css' ];
	}

	/**
	 * Assets JS pour la page de réglages admin.
	 */
	public function get_admin_js(): array {
		return [ SKMT_ASSETS_URL . 'admin/js/modules/login.js' ];
	}

	/* ================================================================
	 * HELPERS PRIVÉS
	 * ================================================================ */

	/**
	 * Valide une couleur hex. Retourne la valeur par défaut si invalide.
	 */
	private function sanitize_color( string $color, string $default = '' ): string {
		$color = sanitize_hex_color( trim( $color ) );
		return $color ?: $default;
	}
}
