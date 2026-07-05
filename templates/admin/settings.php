<?php
/**
 * Template de la page Réglages globaux.
 */

global $wpdb;

$global         = $this->settings->get( 'global', [] );
$update_channel = $global['update_channel'] ?? 'stable';
$auto_updates   = ! empty( $global['auto_updates'] );

// Image processing capabilities (résultat mis en cache 24h pour éviter un appel Imagick coûteux)
$has_imagick = extension_loaded( 'imagick' );
$has_gd      = extension_loaded( 'gd' );

$image_caps = get_transient( 'skmt_image_caps' );
if ( false === $image_caps ) {
	$can_avif = false;
	$can_webp = false;

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
	$image_caps = [ 'avif' => $can_avif, 'webp' => $can_webp ];
	set_transient( 'skmt_image_caps', $image_caps, DAY_IN_SECONDS );
}

$can_avif = $image_caps['avif'];
$can_webp = $image_caps['webp'];
$editor   = $has_imagick ? 'Imagick' : ( $has_gd ? 'GD' : __( 'Aucun', 'studio-kyne-mini-tools' ) );

// Server info
$php_version     = PHP_VERSION;
$wp_version      = get_bloginfo( 'version' );
$mysql_version   = $wpdb->db_version();
$memory_limit    = ini_get( 'memory_limit' );
$upload_max      = ini_get( 'upload_max_filesize' );
$post_max        = ini_get( 'post_max_size' );
$max_exec_time   = ini_get( 'max_execution_time' );
$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : __( 'N/A', 'studio-kyne-mini-tools' );
$ssl_version     = defined( 'OPENSSL_VERSION_TEXT' ) ? OPENSSL_VERSION_TEXT : null;
$curl_version    = function_exists( 'curl_version' ) ? curl_version()['version'] : null;
$has_zip         = extension_loaded( 'zip' );
$has_mbstring    = extension_loaded( 'mbstring' );
$wp_debug        = defined( 'WP_DEBUG' ) && WP_DEBUG;
$wp_memory_limit = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : __( 'N/A', 'studio-kyne-mini-tools' );
?>
<div class="skmt-page">
	<div class="skmt-page__header">
		<div class="skmt-page__header-content">
			<h1 class="skmt-page__title"><?php echo esc_html__( 'Réglages', 'studio-kyne-mini-tools' ); ?></h1>
			<p class="skmt-page__subtitle"><?php echo esc_html__( 'Configuration globale du plugin.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-page__header-actions">
			<button type="submit" form="skmt-save-settings-form" class="skmt-btn skmt-btn--primary skmt-btn--sm">
				<?php echo esc_html__( 'Enregistrer', 'studio-kyne-mini-tools' ); ?>
			</button>
		</div>
	</div>

	<div class="skmt-page__scroll">

	<!-- ================================================================
	     MISES À JOUR
	     ================================================================ -->
	<form id="skmt-save-settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'skmt_save_settings', 'skmt_nonce' ); ?>
		<input type="hidden" name="action" value="skmt_save_settings">
		<input type="hidden" name="skmt_tab" value="settings">

		<div class="skmt-section">
			<div class="skmt-section__header">
				<h2 class="skmt-section__title"><?php echo esc_html__( 'Mises à jour', 'studio-kyne-mini-tools' ); ?></h2>
				<p class="skmt-section__desc"><?php echo esc_html__( 'Configurez le canal de mise à jour et les mises à jour automatiques.', 'studio-kyne-mini-tools' ); ?></p>
			</div>
			<div class="skmt-section__content">

				<div class="skmt-option">
					<div class="skmt-option__content">
						<span class="skmt-option__label"><?php echo esc_html__( 'Version actuelle', 'studio-kyne-mini-tools' ); ?></span>
						<p class="skmt-option__desc"><?php echo esc_html__( 'Version du plugin installée sur ce site.', 'studio-kyne-mini-tools' ); ?></p>
					</div>
					<div class="skmt-option__control skmt-inline">
						<span class="skmt-badge skmt-badge--info">v<?php echo esc_html( SKMT_VERSION ); ?></span>
						<button type="submit" class="skmt-btn skmt-btn--secondary skmt-btn--sm" form="skmt-check-updates-form">
							<?php echo esc_html__( 'Vérifier les mises à jour', 'studio-kyne-mini-tools' ); ?>
						</button>
					</div>
				</div>

				<div class="skmt-option">
					<div class="skmt-option__content">
						<label for="skmt_update_channel" class="skmt-option__label"><?php echo esc_html__( 'Canal de mise à jour', 'studio-kyne-mini-tools' ); ?></label>
						<p class="skmt-option__desc"><?php echo esc_html__( 'Choisissez entre les versions stables et les pré-versions de développement.', 'studio-kyne-mini-tools' ); ?></p>
					</div>
					<div class="skmt-option__control">
						<select id="skmt_update_channel" name="skmt_global[update_channel]" class="skmt-select skmt-select--sm">
							<option value="stable" <?php selected( $update_channel, 'stable' ); ?>><?php echo esc_html__( 'Stable (main)', 'studio-kyne-mini-tools' ); ?></option>
							<option value="dev" <?php selected( $update_channel, 'dev' ); ?>><?php echo esc_html__( 'Dev (pre-release)', 'studio-kyne-mini-tools' ); ?></option>
						</select>
					</div>
				</div>

				<div class="skmt-option">
					<div class="skmt-option__content">
						<label for="skmt_auto_updates" class="skmt-option__label"><?php echo esc_html__( 'Mises à jour automatiques', 'studio-kyne-mini-tools' ); ?></label>
						<p class="skmt-option__desc"><?php echo esc_html__( 'Autoriser WordPress à mettre à jour automatiquement ce plugin.', 'studio-kyne-mini-tools' ); ?></p>
					</div>
					<div class="skmt-option__control">
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
		</div>

	</form>

	<div class="skmt-divider"></div>

	<!-- ================================================================
	     CONFIGURATION — export / import / reset
	     ================================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php echo esc_html__( 'Configuration', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php echo esc_html__( 'Exportez, importez ou réinitialisez la configuration du plugin.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-config-grid">

				<!-- Export -->
				<div class="skmt-config-card">
					<div class="skmt-config-card__body">
						<p class="skmt-config-card__title"><?php echo esc_html__( 'Exporter', 'studio-kyne-mini-tools' ); ?></p>
						<p class="skmt-config-card__desc"><?php echo esc_html__( 'Télécharge un fichier JSON avec tous les réglages globaux et la configuration de chaque module.', 'studio-kyne-mini-tools' ); ?></p>
					</div>
					<div class="skmt-config-card__footer">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'skmt_export_settings', 'skmt_export_nonce' ); ?>
							<input type="hidden" name="action" value="skmt_export_settings">
							<button type="submit" class="skmt-btn skmt-btn--secondary skmt-btn--sm">
								<?php echo esc_html__( 'Exporter', 'studio-kyne-mini-tools' ); ?>
							</button>
						</form>
					</div>
				</div>

				<!-- Import -->
				<div class="skmt-config-card">
					<div class="skmt-config-card__body">
						<p class="skmt-config-card__title"><?php echo esc_html__( 'Importer', 'studio-kyne-mini-tools' ); ?></p>
						<p class="skmt-config-card__desc"><?php echo esc_html__( 'Restaure les réglages depuis un fichier JSON exporté précédemment.', 'studio-kyne-mini-tools' ); ?></p>
					</div>
					<div class="skmt-config-card__footer">
						<form id="skmt-import-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
							<?php wp_nonce_field( 'skmt_import_settings', 'skmt_import_nonce' ); ?>
							<input type="hidden" name="action" value="skmt_import_settings">
							<input type="file" name="skmt_import_file" id="skmt_import_file" accept=".json"
								   style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;clip:rect(0,0,0,0)"
								   onchange="document.getElementById('skmt-import-form').submit()">
							<label for="skmt_import_file" class="skmt-btn skmt-btn--secondary skmt-btn--sm" style="width:100%;cursor:pointer;">
								<?php echo esc_html__( 'Importer', 'studio-kyne-mini-tools' ); ?>
							</label>
						</form>
					</div>
				</div>

				<!-- Réinitialisation -->
				<div class="skmt-config-card">
					<div class="skmt-config-card__body">
						<p class="skmt-config-card__title skmt-config-card__title--danger"><?php echo esc_html__( 'Réinitialiser', 'studio-kyne-mini-tools' ); ?></p>
						<p class="skmt-config-card__desc"><?php echo esc_html__( 'Restaure tous les réglages aux valeurs par défaut. Les contenus générés ne sont pas supprimés.', 'studio-kyne-mini-tools' ); ?></p>
					</div>
					<div class="skmt-config-card__footer">
						<form id="skmt-reset-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'skmt_reset_settings', 'skmt_reset_nonce' ); ?>
							<input type="hidden" name="action" value="skmt_reset_settings">
							<button type="button" class="skmt-btn skmt-btn--danger skmt-btn--sm"
								data-modal-confirm
								data-modal-title="<?php esc_attr_e( 'Réinitialiser les réglages', 'studio-kyne-mini-tools' ); ?>"
								data-modal-message="<?php esc_attr_e( 'Réinitialiser tous les réglages du plugin aux valeurs par défaut ?', 'studio-kyne-mini-tools' ); ?>"
								data-modal-confirm-label="<?php esc_attr_e( 'Réinitialiser', 'studio-kyne-mini-tools' ); ?>"
								data-modal-form="skmt-reset-form">
								<?php echo esc_html__( 'Réinitialiser', 'studio-kyne-mini-tools' ); ?>
							</button>
						</form>
					</div>
				</div>

			</div>
		</div>
	</div>

	<div class="skmt-divider"></div>

	<!-- ================================================================
	     INFORMATIONS SERVEUR — tableau collapsible
	     ================================================================ -->
	<details class="skmt-section skmt-section--collapsible">
		<summary class="skmt-section__header skmt-section__header--summary">
			<div>
				<h2 class="skmt-section__title"><?php echo esc_html__( 'Informations serveur', 'studio-kyne-mini-tools' ); ?></h2>
				<p class="skmt-section__desc"><?php echo esc_html__( 'État et capacités de l\'environnement d\'hébergement.', 'studio-kyne-mini-tools' ); ?></p>
			</div>
			<span class="skmt-section__toggle-icon" aria-hidden="true">
				<?php echo $this->render_icon( 'chevron-down', '16', 'skmt-section__chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
		</summary>
		<div class="skmt-section__content">
			<table class="skmt-server-table">
				<tbody>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'WordPress', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge skmt-badge--info">v<?php echo esc_html( $wp_version ); ?></span></td>
					</tr>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'PHP', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge <?php echo version_compare( $php_version, '8.0', '>=' ) ? 'skmt-badge--success' : 'skmt-badge--warning'; ?>">v<?php echo esc_html( $php_version ); ?></span></td>
					</tr>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'Base de données', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge skmt-badge--info">v<?php echo esc_html( $mysql_version ); ?></span></td>
					</tr>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'Serveur', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge skmt-badge--inactive"><?php echo esc_html( $server_software ); ?></span></td>
					</tr>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'Mémoire PHP', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge skmt-badge--inactive"><?php echo esc_html( $memory_limit ); ?></span></td>
					</tr>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'Mémoire WordPress', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge skmt-badge--inactive"><?php echo esc_html( $wp_memory_limit ); ?></span></td>
					</tr>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'Upload max.', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge skmt-badge--inactive"><?php echo esc_html( $upload_max ); ?></span></td>
					</tr>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'Post max.', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge skmt-badge--inactive"><?php echo esc_html( $post_max ); ?></span></td>
					</tr>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'Exécution max.', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge skmt-badge--inactive"><?php echo esc_html( $max_exec_time ); ?>s</span></td>
					</tr>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'Éditeur image', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge <?php echo 'Aucun' !== $editor ? 'skmt-badge--success' : 'skmt-badge--danger'; ?>"><?php echo esc_html( $editor ); ?></span></td>
					</tr>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'AVIF', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge <?php echo $can_avif ? 'skmt-badge--success' : 'skmt-badge--inactive'; ?>"><?php echo $can_avif ? esc_html__( 'Supporté', 'studio-kyne-mini-tools' ) : esc_html__( 'Non supporté', 'studio-kyne-mini-tools' ); ?></span></td>
					</tr>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'WebP', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge <?php echo $can_webp ? 'skmt-badge--success' : 'skmt-badge--inactive'; ?>"><?php echo $can_webp ? esc_html__( 'Supporté', 'studio-kyne-mini-tools' ) : esc_html__( 'Non supporté', 'studio-kyne-mini-tools' ); ?></span></td>
					</tr>
					<?php if ( $ssl_version ) : ?>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'OpenSSL', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge skmt-badge--success"><?php echo esc_html( $ssl_version ); ?></span></td>
					</tr>
					<?php endif; ?>
					<?php if ( $curl_version ) : ?>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'cURL', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge skmt-badge--success">v<?php echo esc_html( $curl_version ); ?></span></td>
					</tr>
					<?php endif; ?>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'ZIP', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge <?php echo $has_zip ? 'skmt-badge--success' : 'skmt-badge--inactive'; ?>"><?php echo $has_zip ? esc_html__( 'Disponible', 'studio-kyne-mini-tools' ) : esc_html__( 'Non disponible', 'studio-kyne-mini-tools' ); ?></span></td>
					</tr>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'mbstring', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge <?php echo $has_mbstring ? 'skmt-badge--success' : 'skmt-badge--inactive'; ?>"><?php echo $has_mbstring ? esc_html__( 'Disponible', 'studio-kyne-mini-tools' ) : esc_html__( 'Non disponible', 'studio-kyne-mini-tools' ); ?></span></td>
					</tr>
					<tr>
						<td class="skmt-server-table__label"><?php echo esc_html__( 'WP_DEBUG', 'studio-kyne-mini-tools' ); ?></td>
						<td><span class="skmt-badge <?php echo $wp_debug ? 'skmt-badge--warning' : 'skmt-badge--success'; ?>"><?php echo $wp_debug ? esc_html__( 'Activé', 'studio-kyne-mini-tools' ) : esc_html__( 'Désactivé', 'studio-kyne-mini-tools' ); ?></span></td>
					</tr>
				</tbody>
			</table>
		</div>
	</details>

	</div><!-- .skmt-page__scroll -->

	<form id="skmt-check-updates-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'skmt_check_updates', 'skmt_check_nonce' ); ?>
		<input type="hidden" name="action" value="skmt_check_updates">
	</form>
</div>
