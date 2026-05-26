<?php
/**
 * Template de la page Réglages globaux.
 */

global $wpdb;

$global         = $this->settings->get( 'global', [] );
$update_channel = $global['update_channel'] ?? 'stable';
$auto_updates   = ! empty( $global['auto_updates'] );

// Image processing capabilities
$has_imagick = extension_loaded( 'imagick' );
$has_gd      = extension_loaded( 'gd' );
$can_avif    = false;
$can_webp    = false;

if ( $has_imagick ) {
	$formats  = \Imagick::queryFormats();
	$can_avif = in_array( 'AVIF', $formats, true );
	$can_webp = in_array( 'WEBP', $formats, true );
}

if ( $has_gd ) {
	$gd_info  = gd_info();
	$can_avif = $can_avif || ( $gd_info['AVIF Support'] ?? false );
	$can_webp = $can_webp || ( $gd_info['WebP Support'] ?? false );
}

$editor = $has_imagick ? 'Imagick' : ( $has_gd ? 'GD' : __( 'Aucun', 'studio-kyne-mini-tools' ) );

// Server info
$php_version      = PHP_VERSION;
$wp_version       = get_bloginfo( 'version' );
$mysql_version    = $wpdb->db_version();
$memory_limit     = ini_get( 'memory_limit' );
$upload_max       = ini_get( 'upload_max_filesize' );
$post_max         = ini_get( 'post_max_size' );
$max_exec_time    = ini_get( 'max_execution_time' );
$server_software  = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : __( 'N/A', 'studio-kyne-mini-tools' );
$ssl_version      = defined( 'OPENSSL_VERSION_TEXT' ) ? OPENSSL_VERSION_TEXT : null;
$curl_version     = function_exists( 'curl_version' ) ? curl_version()['version'] : null;
$has_zip          = extension_loaded( 'zip' );
$has_mbstring     = extension_loaded( 'mbstring' );
$wp_debug         = defined( 'WP_DEBUG' ) && WP_DEBUG;
$wp_memory_limit  = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : __( 'N/A', 'studio-kyne-mini-tools' );
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
				<p class="skmt-section__desc"><?php echo esc_html__( 'État et capacités de l\'environnement d\'hébergement.', 'studio-kyne-mini-tools' ); ?></p>
			</div>
			<div class="skmt-section__content">
				<div class="skmt-server-status">

					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'WordPress', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge skmt-badge--info">v<?php echo esc_html( $wp_version ); ?></span>
					</div>

					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'PHP', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge <?php echo version_compare( $php_version, '8.0', '>=' ) ? 'skmt-badge--success' : 'skmt-badge--warning'; ?>">
							v<?php echo esc_html( $php_version ); ?>
						</span>
					</div>

					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'Base de données', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge skmt-badge--info">v<?php echo esc_html( $mysql_version ); ?></span>
					</div>

					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'Serveur', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge skmt-badge--inactive"><?php echo esc_html( $server_software ); ?></span>
					</div>

					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'Mémoire PHP', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge skmt-badge--inactive"><?php echo esc_html( $memory_limit ); ?></span>
					</div>

					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'Mémoire WordPress', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge skmt-badge--inactive"><?php echo esc_html( $wp_memory_limit ); ?></span>
					</div>

					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'Upload max.', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge skmt-badge--inactive"><?php echo esc_html( $upload_max ); ?></span>
					</div>

					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'Post max.', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge skmt-badge--inactive"><?php echo esc_html( $post_max ); ?></span>
					</div>

					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'Temps d\'exécution max.', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge skmt-badge--inactive"><?php echo esc_html( $max_exec_time ); ?>s</span>
					</div>

					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'Éditeur image', 'studio-kyne-mini-tools' ); ?></span>
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

					<?php if ( $ssl_version ) : ?>
					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'OpenSSL', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge skmt-badge--success"><?php echo esc_html( $ssl_version ); ?></span>
					</div>
					<?php endif; ?>

					<?php if ( $curl_version ) : ?>
					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'cURL', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge skmt-badge--success">v<?php echo esc_html( $curl_version ); ?></span>
					</div>
					<?php endif; ?>

					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'ZIP', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge <?php echo $has_zip ? 'skmt-badge--success' : 'skmt-badge--inactive'; ?>">
							<?php echo $has_zip ? esc_html__( 'Disponible', 'studio-kyne-mini-tools' ) : esc_html__( 'Non disponible', 'studio-kyne-mini-tools' ); ?>
						</span>
					</div>

					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'mbstring', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge <?php echo $has_mbstring ? 'skmt-badge--success' : 'skmt-badge--inactive'; ?>">
							<?php echo $has_mbstring ? esc_html__( 'Disponible', 'studio-kyne-mini-tools' ) : esc_html__( 'Non disponible', 'studio-kyne-mini-tools' ); ?>
						</span>
					</div>

					<div class="skmt-server-status__item">
						<span class="skmt-server-status__label"><?php echo esc_html__( 'WP_DEBUG', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge <?php echo $wp_debug ? 'skmt-badge--warning' : 'skmt-badge--success'; ?>">
							<?php echo $wp_debug ? esc_html__( 'Activé', 'studio-kyne-mini-tools' ) : esc_html__( 'Désactivé', 'studio-kyne-mini-tools' ); ?>
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

	<div class="skmt-divider"></div>

	<!-- Réinitialisation -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php echo esc_html__( 'Réinitialisation', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php echo esc_html__( 'Restaurer tous les réglages du plugin aux valeurs par défaut.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">
			<p class="skmt-form__help">
				<?php echo esc_html__( 'Cette action réinitialisera les réglages globaux ainsi que ceux de tous les modules actifs. Les contenus générés (images optimisées, etc.) ne sont pas supprimés.', 'studio-kyne-mini-tools' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Réinitialiser tous les réglages du plugin aux valeurs par défaut ?', 'studio-kyne-mini-tools' ) ); ?>')">
				<?php wp_nonce_field( 'skmt_reset_settings', 'skmt_reset_nonce' ); ?>
				<input type="hidden" name="action" value="skmt_reset_settings">
				<button type="submit" class="skmt-btn skmt-btn--secondary">
					<?php echo esc_html__( 'Réinitialiser la configuration', 'studio-kyne-mini-tools' ); ?>
				</button>
			</form>
		</div>
	</div>

	<form id="skmt-check-updates-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'skmt_check_updates', 'skmt_check_nonce' ); ?>
		<input type="hidden" name="action" value="skmt_check_updates">
	</form>
</div>
