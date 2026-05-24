<?php
/**
 * Template de la page Réglages globaux.
 */

$global = $this->settings->get( 'global', [] );
$update_channel = $global['update_channel'] ?? 'stable';
$auto_updates   = ! empty( $global['auto_updates'] );

// Détecter les capacités serveur
$has_imagick = extension_loaded( 'imagick' );
$has_gd      = extension_loaded( 'gd' );
$can_avif    = false;
$can_webp    = false;

if ( $has_imagick ) {
	$formats = \Imagick::queryFormats();
	$can_avif = in_array( 'AVIF', $formats, true );
	$can_webp = in_array( 'WEBP', $formats, true );
}

if ( $has_gd ) {
	$gd_info  = gd_info();
	$can_avif = $can_avif || ( $gd_info['AVIF Support'] ?? false );
	$can_webp = $can_webp || ( $gd_info['WebP Support'] ?? false );
}

$editor = $has_imagick ? 'Imagick' : ( $has_gd ? 'GD' : 'Aucun' );
?>
<div class="skmt-page">
	<div class="skmt-page__header">
		<div class="skmt-page__header-content">
			<h1 class="skmt-page__title"><?php echo esc_html__( 'Réglages', 'studio-kyne-mini-tools' ); ?></h1>
			<p class="skmt-page__subtitle"><?php echo esc_html__( 'Configuration globale du plugin.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'skmt_save_settings', 'skmt_nonce' ); ?>
		<input type="hidden" name="action" value="skmt_save_settings">
		<input type="hidden" name="skmt_tab" value="settings">

		<!-- Informations serveur -->
		<div class="skmt-section">
			<div class="skmt-section__header">
				<h2 class="skmt-section__title"><?php echo esc_html__( 'Informations serveur', 'studio-kyne-mini-tools' ); ?></h2>
				<p class="skmt-section__desc"><?php echo esc_html__( 'Capacités de traitement d\'image détectées sur votre serveur.', 'studio-kyne-mini-tools' ); ?></p>
			</div>
			<div class="skmt-section__content">
				<div class="skmt-server-status">
					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'Éditeur', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge <?php echo 'Aucun' !== $editor ? 'skmt-badge--success' : 'skmt-badge--danger'; ?>">
							<?php echo esc_html( $editor ); ?>
						</span>
					</div>
					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'AVIF', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge <?php echo $can_avif ? 'skmt-badge--success' : 'skmt-badge--inactive'; ?>">
							<?php echo $can_avif ? esc_html__( 'Supporté', 'studio-kyne-mini-tools' ) : esc_html__( 'Non supporté', 'studio-kyne-mini-tools' ); ?>
						</span>
					</div>
					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'WebP', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge <?php echo $can_webp ? 'skmt-badge--success' : 'skmt-badge--inactive'; ?>">
							<?php echo $can_webp ? esc_html__( 'Supporté', 'studio-kyne-mini-tools' ) : esc_html__( 'Non supporté', 'studio-kyne-mini-tools' ); ?>
						</span>
					</div>
				</div>
			</div>
		</div>

		<div class="skmt-divider"></div>

		<!-- Mises à jour GitHub -->
		<div class="skmt-section">
			<div class="skmt-section__header">
				<h2 class="skmt-section__title"><?php echo esc_html__( 'Mises à jour', 'studio-kyne-mini-tools' ); ?></h2>
				<p class="skmt-section__desc"><?php echo esc_html__( 'Configurez le plugin pour qu\'il vérifie les mises à jour',
             'studio-kyne-mini-tools' ); ?></p>
			</div>
			<div class="skmt-section__content">
				<div class="skmt-form__group">
					<label class="skmt-form__label"><?php echo esc_html__( 'Version actuelle', 'studio-kyne-mini-tools' ); ?></label>
					<div class="skmt-inline">
						<p class="skmt-form__help">v<?php echo esc_html( SKMT_VERSION ); ?></p>
						<button type="submit" class="skmt-btn skmt-btn--secondary skmt-btn--sm" form="skmt-check-updates-form">
							<?php echo esc_html__( 'Vérifier les mises à jour', 'studio-kyne-mini-tools' ); ?>
						</button>
					</div>
				</div>
				<div class="skmt-form__group">
					<label for="skmt_update_channel" class="skmt-form__label"><?php echo esc_html__( 'Canal de mise à jour', 'studio-kyne-mini-tools' ); ?></label>
					<select id="skmt_update_channel" name="skmt_global[update_channel]" class="skmt-select">
						<option value="stable" <?php selected( $update_channel, 'stable' ); ?>><?php echo esc_html__( 'Stable (main)', 'studio-kyne-mini-tools' ); ?></option>
						<option value="dev" <?php selected( $update_channel, 'dev' ); ?>><?php echo esc_html__( 'Dev (pré-release)', 'studio-kyne-mini-tools' ); ?></option>
					</select>
				</div>
				<div class="skmt-form__group skmt-form__group--toggle">
					<div class="skmt-form__toggle-label">
						<label for="skmt_auto_updates" class="skmt-form__label"><?php echo esc_html__( 'Mises à jour automatiques', 'studio-kyne-mini-tools' ); ?></label>
						<p class="skmt-form__help"><?php echo esc_html__( 'Autoriser WordPress à mettre à jour automatiquement ce plugin.', 'studio-kyne-mini-tools' ); ?></p>
					</div>
					<label class="skmt-toggle">
						<input type="checkbox"
							   id="skmt_auto_updates"
							   name="skmt_global[auto_updates]"
							   value="1"
							<?php checked( $auto_updates, true ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>
		</div>

		<div class="skmt-page__footer">
			<button type="submit" class="skmt-btn skmt-btn--primary">
				<?php echo esc_html__( 'Enregistrer les réglages', 'studio-kyne-mini-tools' ); ?>
			</button>
		</div>
	</form>

	<form id="skmt-check-updates-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'skmt_check_updates', 'skmt_check_nonce' ); ?>
		<input type="hidden" name="action" value="skmt_check_updates">
	</form>
</div>