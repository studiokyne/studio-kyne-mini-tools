<?php
/**
 * Template de la page Modules.
 */

$modules = $this->modules->get_all();
?>
<div class="skmt-page">
	<div class="skmt-page__header">
		<div class="skmt-page__header-content">
			<h1 class="skmt-page__title"><?php echo esc_html__( 'Modules', 'studio-kyne-mini-tools' ); ?></h1>
			<p class="skmt-page__subtitle"><?php echo esc_html__( 'Activez ou désactivez les modules selon vos besoins.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
	</div>

	<div class="skmt-page__body">
		<div class="skmt-module-grid">
			<?php foreach ( $modules as $module_id => $module ) :
				$is_active = $this->modules->is_active( $module_id );
				$icon      = ! empty( $module['icon'] ) ? $module['icon'] : 'package';
			?>
				<div class="skmt-module-card <?php echo $is_active ? 'skmt-module-card--active' : ''; ?>">
					<div class="skmt-module-card__header">
						<i data-lucide="<?php echo esc_attr( $icon ); ?>" class="skmt-icon skmt-icon--md"></i>
						<h3 class="skmt-module-card__title"><?php echo esc_html( $module['name'] ); ?></h3>
						<?php if ( $is_active ) : ?>
							<span class="skmt-badge skmt-badge--success"><?php echo esc_html__( 'Actif', 'studio-kyne-mini-tools' ); ?></span>
						<?php else : ?>
							<span class="skmt-badge skmt-badge--inactive"><?php echo esc_html__( 'Inactif', 'studio-kyne-mini-tools' ); ?></span>
						<?php endif; ?>
					</div>
					<p class="skmt-module-card__desc"><?php echo esc_html( $module['description'] ); ?></p>
					<div class="skmt-module-card__actions">
						<?php if ( $is_active ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->get_slug() . '&tab=module_' . $module_id ) ); ?>" class="skmt-btn skmt-btn--sm skmt-btn--secondary">
								<?php echo esc_html__( 'Configurer', 'studio-kyne-mini-tools' ); ?>
							</a>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=skmt_toggle_module&module=' . $module_id . '&skmt_action=deactivate' ), 'skmt_toggle_module', 'skmt_nonce' ) ); ?>"
							   class="skmt-btn skmt-btn--sm skmt-btn--danger">
								<?php echo esc_html__( 'Désactiver', 'studio-kyne-mini-tools' ); ?>
							</a>
						<?php else : ?>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=skmt_toggle_module&module=' . $module_id . '&skmt_action=activate' ), 'skmt_toggle_module', 'skmt_nonce' ) ); ?>"
							   class="skmt-btn skmt-btn--sm skmt-btn--primary">
								<?php echo esc_html__( 'Activer', 'studio-kyne-mini-tools' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>