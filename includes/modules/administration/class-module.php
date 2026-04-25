<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Module_Administration implements SKMT_Module_Interface {
	protected $plugin;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function get_id() { return 'administration'; }
	public function get_name() { return __( 'Administration', 'studio-kyne-mini-tools' ); }
	public function get_description() { return __( 'Confort, branding et ergonomie du back-office WordPress.', 'studio-kyne-mini-tools' ); }
	public function get_icon() { return 'layout-dashboard'; }
	public function is_default_active() { return false; }
	public function is_configurable() { return true; }
	public function activate() {}
	public function deactivate() {}

	public function register() {}

	public function register_admin_pages( $parent_slug ) {
		add_submenu_page(
			$parent_slug,
			__( 'Administration', 'studio-kyne-mini-tools' ),
			__( 'Administration', 'studio-kyne-mini-tools' ),
			SKMT_Capabilities::admin_capability(),
			'skmt-administration',
			array( $this, 'render_admin_page' )
		);
	}

	public function render_admin_page() {
		if ( ! SKMT_Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Acces refuse.', 'studio-kyne-mini-tools' ) );
		}

		?>
		<div class="wrap skmt-wrap">
			<div class="skmt-shell">
				<header class="skmt-page-head">
					<div>
						<h1><?php echo esc_html__( 'Module Administration', 'studio-kyne-mini-tools' ); ?></h1>
						<p><?php echo esc_html__( 'Module en construction: confort, branding et UX admin.', 'studio-kyne-mini-tools' ); ?></p>
					</div>
					<span class="skmt-badge skmt-badge--warning"><?php echo esc_html__( 'Roadmap', 'studio-kyne-mini-tools' ); ?></span>
				</header>

				<div class="skmt-card">
					<h2><?php echo esc_html__( 'Interface', 'studio-kyne-mini-tools' ); ?></h2>
					<ul class="skmt-status-list">
						<li><span><?php echo esc_html__( 'Refonte de l administration', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted">TODO</span></li>
						<li><span><?php echo esc_html__( 'Amelioration du design de la page de connexion', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted">TODO</span></li>
						<li><span><?php echo esc_html__( 'Masquer la barre d administration', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted">TODO</span></li>
					</ul>
				</div>

				<div class="skmt-card">
					<h2><?php echo esc_html__( 'Experience utilisateur', 'studio-kyne-mini-tools' ); ?></h2>
					<ul class="skmt-status-list">
						<li><span><?php echo esc_html__( 'Nettoyer les profils', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted">TODO</span></li>
						<li><span><?php echo esc_html__( 'Ajustements UI/UX du back-office', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted">TODO</span></li>
						<li><span><?php echo esc_html__( 'Personnalisation de certains ecrans admin', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted">TODO</span></li>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}
}
