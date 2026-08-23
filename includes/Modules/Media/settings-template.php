<?php
/**
 * Page de réglages du module Médias.
 *
 * Le module n'a pas de réglages propres : l'interface des dossiers virtuels est
 * intégrée directement dans la médiathèque WordPress (upload.php) via JS.
 *
 * @var string $module_id
 * @var array  $module
 * @var object $instance
 * @var array  $module_settings
 * @var string $tab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="skmt-section">
	<div class="skmt-section__header">
		<h2 class="skmt-section__title"><?php esc_html_e( 'Dossiers médias', 'studio-kyne-mini-tools' ); ?></h2>
		<p class="skmt-section__desc">
			<?php esc_html_e( 'Les dossiers virtuels sont accessibles directement depuis la médiathèque WordPress.', 'studio-kyne-mini-tools' ); ?>
		</p>
	</div>
	<div class="skmt-section__content">
		<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="skmt-btn skmt-btn--primary">
			<?php esc_html_e( 'Ouvrir la médiathèque', 'studio-kyne-mini-tools' ); ?>
		</a>
	</div>
</div>
