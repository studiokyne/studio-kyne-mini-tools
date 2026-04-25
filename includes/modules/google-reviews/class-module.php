<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Module_Google_Reviews implements SKMT_Module_Interface {
	protected $plugin;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function get_id() { return 'google-reviews'; }
	public function get_name() { return __( 'Google Reviews', 'studio-kyne-mini-tools' ); }
	public function get_description() { return __( 'Recuperation et affichage des avis Google My Business.', 'studio-kyne-mini-tools' ); }
	public function get_icon() { return 'star'; }
	public function is_default_active() { return false; }
	public function is_configurable() { return true; }
	public function activate() {}
	public function deactivate() {}

	public function register() {}

	public function register_admin_pages( $parent_slug ) {
		add_submenu_page(
			$parent_slug,
			__( 'Google Reviews', 'studio-kyne-mini-tools' ),
			__( 'Google Reviews', 'studio-kyne-mini-tools' ),
			SKMT_Capabilities::admin_capability(),
			'skmt-google-reviews',
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
						<h1><?php echo esc_html__( 'Module Google Reviews', 'studio-kyne-mini-tools' ); ?></h1>
						<p><?php echo esc_html__( 'Module en construction: integration avis Google My Business.', 'studio-kyne-mini-tools' ); ?></p>
					</div>
					<span class="skmt-badge skmt-badge--warning"><?php echo esc_html__( 'Roadmap', 'studio-kyne-mini-tools' ); ?></span>
				</header>

				<div class="skmt-card">
					<h2><?php echo esc_html__( 'Fonctionnalites cibles', 'studio-kyne-mini-tools' ); ?></h2>
					<ul class="skmt-status-list">
						<li><span><?php echo esc_html__( 'Configuration source (place id / business profile)', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted">TODO</span></li>
						<li><span><?php echo esc_html__( 'Recuperation des avis via API', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted">TODO</span></li>
						<li><span><?php echo esc_html__( 'Cache local pour limiter les appels API', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted">TODO</span></li>
						<li><span><?php echo esc_html__( 'Affichage configurable (widget/bloc/shortcode)', 'studio-kyne-mini-tools' ); ?></span><span class="skmt-badge skmt-badge--muted">TODO</span></li>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}
}
