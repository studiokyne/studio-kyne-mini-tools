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

	<div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="skmt-form">
			<?php wp_nonce_field( 'skmt_update_modules', 'skmt_modules_nonce' ); ?>
			<input type="hidden" name="action" value="skmt_update_modules">

			<div class="skmt-module-grid skmt-page__body">
				<?php foreach ( $modules as $module_id => $module ) :
					$is_active = $this->modules->is_active( $module_id );
					$icon      = ! empty( $module['icon'] ) ? $module['icon'] : 'package';
				?>
					<div class="skmt-module-card <?php echo $is_active ? 'skmt-module-card--active' : ''; ?>">
						<div class="skmt-module-card__header">
							<i data-lucide="<?php echo esc_attr( $icon ); ?>" class="skmt-icon skmt-icon--md"></i>
							<h3 class="skmt-module-card__title"><?php echo esc_html( $module['name'] ); ?></h3>
							<label class="skmt-toggle">
								<input type="checkbox"
									   name="skmt_modules[]"
									   value="<?php echo esc_attr( $module_id ); ?>"
									   <?php checked( $is_active, true ); ?>>
								<span class="skmt-toggle__slider"></span>
							</label>
						</div>
						<p class="skmt-module-card__desc"><?php echo esc_html( $module['description'] ); ?></p>
						<div class="skmt-module-card__actions">

							<?php if ( $is_active ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->get_slug() . '&tab=module_' . $module_id ) ); ?>" class="skmt-btn skmt-btn--sm skmt-btn--secondary">
									<?php echo esc_html__( 'Configurer', 'studio-kyne-mini-tools' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="skmt-page__footer">
				<button type="submit" class="skmt-btn skmt-btn--primary">
					<?php echo esc_html__( 'Enregistrer les modules', 'studio-kyne-mini-tools' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>