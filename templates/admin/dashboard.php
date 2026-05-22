<?php
/**
 * Template du tableau de bord.
 */

$modules      = $this->modules->get_all();
$active_count = count( array_filter( $modules, function( $id ) {
	return $this->modules->is_active( $id );
}, ARRAY_FILTER_USE_KEY ) );
?>
<div class="skmt-page">
	<div class="skmt-page__header">
		<div class="skmt-page__header-content">
			<h1 class="skmt-page__title"><?php echo esc_html__( 'Vue d\'ensemble', 'studio-kyne-mini-tools' ); ?></h1>
			<p class="skmt-page__subtitle"><?php echo esc_html__( 'Paramètres du plugin principal', 'studio-kyne-mini-tools' ); ?></p>
		</div>
	</div>

		<div class="skmt-cards">
			<div class="skmt-card skmt-card--stat">
				<div class="skmt-card__icon">
					<i data-lucide="package" class="skmt-icon skmt-icon--lg"></i>
				</div>
				<div class="skmt-card__content">
					<span class="skmt-card__value"><?php echo esc_html( count( $modules ) ); ?></span>
					<span class="skmt-card__label"><?php echo esc_html__( 'Modules disponibles', 'studio-kyne-mini-tools' ); ?></span>
				</div>
			</div>

			<div class="skmt-card skmt-card--stat">
				<div class="skmt-card__icon skmt-card__icon--success">
					<i data-lucide="check-circle" class="skmt-icon skmt-icon--lg"></i>
				</div>
				<div class="skmt-card__content">
					<span class="skmt-card__value"><?php echo esc_html( $active_count ); ?></span>
					<span class="skmt-card__label"><?php echo esc_html__( 'Modules actifs', 'studio-kyne-mini-tools' ); ?></span>
				</div>
			</div>

			<div class="skmt-card skmt-card--stat">
				<div class="skmt-card__icon skmt-card__icon--info">
					<i data-lucide="info" class="skmt-icon skmt-icon--lg"></i>
				</div>
				<div class="skmt-card__content">
					<span class="skmt-card__value"><?php echo esc_html( SKMT_VERSION ); ?></span>
					<span class="skmt-card__label"><?php echo esc_html__( 'Version', 'studio-kyne-mini-tools' ); ?></span>
				</div>
			</div>
		</div>

		<div class="skmt-section">
			<div class="skmt-section__header">
				<h2 class="skmt-section__title"><?php echo esc_html__( 'Modules actifs', 'studio-kyne-mini-tools' ); ?></h2>
				<p class="skmt-section__desc"><?php echo esc_html__( 'Voici les modules actuellement actifs sur votre site.', 'studio-kyne-mini-tools' ); ?></p>
			</div>
			<div class="skmt-section__content">
				<?php if ( $active_count > 0 ) : ?>
					<div class="skmt-module-list">
						<?php foreach ( $modules as $module_id => $module ) : ?>
							<?php if ( $this->modules->is_active( $module_id ) ) : ?>
								<?php $icon = ! empty( $module['icon'] ) ? $module['icon'] : 'package'; ?>
								<div class="skmt-module-card skmt-module-card--active">
									<div class="skmt-module-card__header">
										<i data-lucide="<?php echo esc_attr( $icon ); ?>" class="skmt-icon skmt-icon--md"></i>
										<h3 class="skmt-module-card__title"><?php echo esc_html( $module['name'] ); ?></h3>
										<span class="skmt-badge skmt-badge--success"><?php echo esc_html__( 'Actif', 'studio-kyne-mini-tools' ); ?></span>
									</div>
									<p class="skmt-module-card__desc"><?php echo esc_html( $module['description'] ); ?></p>
									<div class="skmt-module-card__actions">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->get_slug() . '&tab=module_' . $module_id ) ); ?>" class="skmt-btn skmt-btn--sm skmt-btn--secondary">
											<?php echo esc_html__( 'Configurer', 'studio-kyne-mini-tools' ); ?>
										</a>
									</div>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="skmt-empty">
						<i data-lucide="puzzle" class="skmt-icon skmt-icon--xl"></i>
						<p><?php echo esc_html__( 'Aucun module actif. Activez des modules depuis la page Modules.', 'studio-kyne-mini-tools' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->get_slug() . '&tab=modules' ) ); ?>" class="skmt-btn skmt-btn--primary">
							<?php echo esc_html__( 'Voir les modules', 'studio-kyne-mini-tools' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>
</div>