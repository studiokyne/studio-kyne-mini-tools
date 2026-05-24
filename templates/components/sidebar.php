<?php
/**
 * Composant sidebar réutilisable.
 */

$tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
$modules = $this->modules->get_all();

$core_items = [
	[
		'id'    => 'dashboard',
		'label' => __( 'Vue d\'ensemble', 'studio-kyne-mini-tools' ),
		'desc'  => __( 'Tableau de bord', 'studio-kyne-mini-tools' ),
		'icon'  => 'layout-dashboard',
	],
	[
		'id'    => 'modules',
		'label' => __( 'Modules', 'studio-kyne-mini-tools' ),
		'desc'  => __( 'Gérer les modules', 'studio-kyne-mini-tools' ),
		'icon'  => 'package',
	],
	[
		'id'    => 'settings',
		'label' => __( 'Réglages', 'studio-kyne-mini-tools' ),
		'desc'  => __( 'Configuration globale', 'studio-kyne-mini-tools' ),
		'icon'  => 'settings',
	],
];
?>
<aside class="skmt-sidebar">
	<div class="skmt-sidebar__header">
		<h2 class="skmt-sidebar__title"><?php echo esc_html__( 'Navigation', 'studio-kyne-mini-tools' ); ?></h2>
		<div class="skmt-sidebar__actions">
			<!-- 
         2 buttons
         - 1 pour importer la configuration depuis un fichier JSON
         - 1 pour exporter la configuration actuelle dans un fichier JSON
         -->
		</div>
	</div>

	<nav class="skmt-sidebar__nav">
		<ul class="skmt-sidebar__menu">
			<?php foreach ( $core_items as $item ) : ?>
				<li class="skmt-sidebar__item">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->get_slug() . '&tab=' . $item['id'] ) ); ?>"
					   class="skmt-sidebar__link <?php echo $item['id'] === $tab ? 'is-active' : ''; ?>">
						<div class="skmt-sidebar__icon-wrapper">
							<?php echo $this->render_icon( $item['icon'], 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="skmt-sidebar__link-text">
							<span class="skmt-sidebar__link-label"><?php echo esc_html( $item['label'] ); ?></span>
							<span class="skmt-sidebar__link-desc"><?php echo esc_html( $item['desc'] ); ?></span>
						</div>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( ! empty( $modules ) ) : ?>
			<div class="skmt-sidebar__divider"></div>
			<ul class="skmt-sidebar__menu">
				<?php foreach ( $modules as $module_id => $module ) : ?>
					<?php if ( $this->modules->is_active( $module_id ) ) : ?>
						<?php
							$label = ! empty( $module['menu_label'] ) ? $module['menu_label'] : $module['name'];
							$desc  = ! empty( $module['menu_desc'] ) ? $module['menu_desc'] : $module['description'];
							$icon  = ! empty( $module['icon'] ) ? $module['icon'] : 'package';
						?>
						<li class="skmt-sidebar__item">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->get_slug() . '&tab=module_' . $module_id ) ); ?>"
							   class="skmt-sidebar__link <?php echo 'module_' . $module_id === $tab ? 'is-active' : ''; ?>">
								<div class="skmt-sidebar__icon-wrapper">
									<?php echo $this->render_icon( $icon, 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
								<div class="skmt-sidebar__link-text">
									<span class="skmt-sidebar__link-label"><?php echo esc_html( $label ); ?></span>
									<span class="skmt-sidebar__link-desc"><?php echo esc_html( $desc ); ?></span>
								</div>
							</a>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</nav>
</aside>
