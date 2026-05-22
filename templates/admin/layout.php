<?php
/**
 * Template principal de l'interface admin.
 */

$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
?>
<div class="skmt-admin-wrap">
	<div class="skmt-admin-container">
		<!-- Sidebar -->
		<?php include SKMT_TEMPLATES_DIR . 'components/sidebar.php'; ?>

		<!-- Contenu principal -->
		<main class="skmt-admin-main">
			<?php
			switch ( $tab ) {
				case 'dashboard':
					include SKMT_TEMPLATES_DIR . 'admin/dashboard.php';
					break;
				case 'modules':
					include SKMT_TEMPLATES_DIR . 'admin/modules.php';
					break;
				case 'settings':
					include SKMT_TEMPLATES_DIR . 'admin/settings.php';
					break;
				default:
					// Vérifier si c'est un module actif
					if ( strpos( $tab, 'module_' ) === 0 ) {
						$module_id = substr( $tab, 7 );
						$module    = $this->modules->get( $module_id );

						if ( $module && $this->modules->is_active( $module_id ) ) {
							include SKMT_TEMPLATES_DIR . 'admin/module-settings.php';
						} else {
							include SKMT_TEMPLATES_DIR . 'admin/dashboard.php';
						}
					} else {
						include SKMT_TEMPLATES_DIR . 'admin/dashboard.php';
					}
					break;
			}
			?>
		</main>
	</div>
</div>