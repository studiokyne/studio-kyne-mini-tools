<?php
/**
 * Template des réglages d'un module.
 */

$module_id = substr( $tab, 7 );
$module    = $this->modules->get( $module_id );
$instance  = $this->modules->get_active_instances()[ $module_id ] ?? null;

if ( ! $instance ) {
	return;
}

$module_settings = $instance->get_settings();
?>
<div class="skmt-page">
	<div class="skmt-page__header">
		<div class="skmt-page__header-content">
			<h1 class="skmt-page__title"><?php echo esc_html( $module['name'] ); ?></h1>
			<p class="skmt-page__subtitle"><?php echo esc_html( $module['description'] ); ?></p>
		</div>
		<div class="skmt-page__header-actions">
			<button type="submit" form="skmt-module-form" id="skmt-module-save-btn" class="skmt-btn skmt-btn--primary skmt-btn--sm">
				<?php echo esc_html__( 'Enregistrer', 'studio-kyne-mini-tools' ); ?>
			</button>
		</div>
	</div>

	<?php
	// Le module peut fournir son propre template de réglages
	$module_class_name = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $module_id ) ) );
	$module_template   = SKMT_PLUGIN_DIR . 'includes/Modules/' . $module_class_name . '/settings-template.php';

	if ( file_exists( $module_template ) ) {
		include $module_template;
	} else {
		// Template générique
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="skmt-form">
			<?php wp_nonce_field( 'skmt_save_settings', 'skmt_nonce' ); ?>
			<input type="hidden" name="action" value="skmt_save_settings">
			<input type="hidden" name="skmt_tab" value="<?php echo esc_attr( $tab ); ?>">

			<div class="skmt-card">
				<div class="skmt-card__header">
					<h2 class="skmt-card__title"><?php echo esc_html__( 'Réglages du module', 'studio-kyne-mini-tools' ); ?></h2>
				</div>
				<div class="skmt-card__body">
					<?php foreach ( $module_settings as $key => $value ) : ?>
						<div class="skmt-form__group skmt-form__group--toggle">
							<div class="skmt-form__toggle-label">
								<label for="skmt_<?php echo esc_attr( $key ); ?>" class="skmt-form__label"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ); ?></label>
							</div>
							<label class="skmt-toggle">
								<input type="checkbox"
									   id="skmt_<?php echo esc_attr( $key ); ?>"
									   name="skmt_module_settings[<?php echo esc_attr( $key ); ?>]"
									   value="1"
									   <?php checked( $value, true ); ?>>
								<span class="skmt-toggle__slider"></span>
							</label>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="skmt-form__actions">
				<button type="submit" class="skmt-btn skmt-btn--primary">
					<?php echo esc_html__( 'Enregistrer les réglages', 'studio-kyne-mini-tools' ); ?>
				</button>
			</div>
		</form>
	<?php } ?>
</div>