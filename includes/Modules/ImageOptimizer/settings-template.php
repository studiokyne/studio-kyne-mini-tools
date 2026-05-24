<?php
/**
 * Template des réglages du module Image Optimizer.
 *
 * Variables disponibles : $instance, $tab, $module_id, $module
 */

$module_settings = $instance->get_settings();
$preview = $instance->get_bulk_preview();
$estimated_saved = (int) ( $preview['estimated_bytes_saved'] ?? 0 );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'skmt_save_settings', 'skmt_nonce' ); ?>
	<input type="hidden" name="action" value="skmt_save_settings">
	<input type="hidden" name="skmt_tab" value="<?php echo esc_attr( $tab ); ?>">

	<!-- Comportement -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php echo esc_html__( 'Comportement', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php echo esc_html__( 'Configurez comment les images sont traitées lors de l\'upload.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_optimize_on_upload" class="skmt-option__label"><?php echo esc_html__( 'Optimiser à l\'upload', 'studio-kyne-mini-tools' ); ?></label>
					<p class="skmt-option__desc"><?php echo esc_html__( 'Redimensionne, compresse et convertit automatiquement les images.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox"
							   id="skmt_optimize_on_upload"
							   name="skmt_module_settings[optimize_on_upload]"
							   value="1"
							   <?php checked( $module_settings['optimize_on_upload'], true ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_format_mode" class="skmt-option__label"><?php echo esc_html__( 'Format de sortie', 'studio-kyne-mini-tools' ); ?></label>
					<p class="skmt-option__desc"><?php echo esc_html__( 'Format de conversion automatique des images.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<select id="skmt_format_mode"
							name="skmt_module_settings[format_mode]"
							class="skmt-select skmt-select--sm">
						<option value="auto" <?php selected( $module_settings['format_mode'], 'auto' ); ?>>
							<?php echo esc_html__( 'Auto', 'studio-kyne-mini-tools' ); ?>
						</option>
						<option value="avif" <?php selected( $module_settings['format_mode'], 'avif' ); ?>>
							<?php echo esc_html__( 'AVIF', 'studio-kyne-mini-tools' ); ?>
						</option>
						<option value="webp" <?php selected( $module_settings['format_mode'], 'webp' ); ?>>
							<?php echo esc_html__( 'WebP', 'studio-kyne-mini-tools' ); ?>
						</option>
					</select>
				</div>
			</div>
		</div>
	</div>

	<div class="skmt-divider"></div>

	<!-- Qualité et dimensions -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php echo esc_html__( 'Qualité et dimensions', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php echo esc_html__( 'Ajustez la qualité de compression et les dimensions maximales.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-form__group">
				<label for="skmt_quality" class="skmt-form__label"><?php echo esc_html__( 'Qualité de compression (1-100)', 'studio-kyne-mini-tools' ); ?></label>
				<input type="number"
					   id="skmt_quality"
					   name="skmt_module_settings[quality]"
					   class="skmt-input skmt-input--sm"
					   value="<?php echo esc_attr( $module_settings['quality'] ); ?>"
					   min="1"
					   max="100">
			</div>

			<div class="skmt-form__row">
				<div class="skmt-form__group">
					<label for="skmt_max_width" class="skmt-form__label"><?php echo esc_html__( 'Largeur max (px)', 'studio-kyne-mini-tools' ); ?></label>
					<input type="number"
						   id="skmt_max_width"
						   name="skmt_module_settings[max_width]"
						   class="skmt-input"
						   value="<?php echo esc_attr( $module_settings['max_width'] ); ?>"
						   min="100">
				</div>

				<div class="skmt-form__group">
					<label for="skmt_max_height" class="skmt-form__label"><?php echo esc_html__( 'Hauteur max (px)', 'studio-kyne-mini-tools' ); ?></label>
					<input type="number"
						   id="skmt_max_height"
						   name="skmt_module_settings[max_height]"
						   class="skmt-input"
						   value="<?php echo esc_attr( $module_settings['max_height'] ); ?>"
						   min="100">
				</div>
			</div>
		</div>
	</div>

	<div class="skmt-divider"></div>

	<!-- Options avancées -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php echo esc_html__( 'Options avancées', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php echo esc_html__( 'Options supplémentaires pour le traitement des images.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_strip_exif" class="skmt-option__label"><?php echo esc_html__( 'Supprimer les métadonnées EXIF', 'studio-kyne-mini-tools' ); ?></label>
					<p class="skmt-option__desc"><?php echo esc_html__( 'Retire les données GPS, appareil photo, etc.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox"
							   id="skmt_strip_exif"
							   name="skmt_module_settings[strip_exif]"
							   value="1"
							   <?php checked( $module_settings['strip_exif'], true ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_generate_alt" class="skmt-option__label"><?php echo esc_html__( 'Générer le texte alternatif', 'studio-kyne-mini-tools' ); ?></label>
					<p class="skmt-option__desc"><?php echo esc_html__( 'Crée automatiquement le alt text depuis le nom du fichier.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox"
							   id="skmt_generate_alt"
							   name="skmt_module_settings[generate_alt]"
							   value="1"
							   <?php checked( $module_settings['generate_alt'], true ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_keep_original" class="skmt-option__label"><?php echo esc_html__( 'Conserver l\'original', 'studio-kyne-mini-tools' ); ?></label>
					<p class="skmt-option__desc"><?php echo esc_html__( 'Garde une copie du fichier source en plus du format converti.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox"
							   id="skmt_keep_original"
							   name="skmt_module_settings[keep_original]"
							   value="1"
							   <?php checked( $module_settings['keep_original'], true ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>
		</div>
	</div>

	<!-- Optimisation en masse -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php echo esc_html__( 'Optimisation en masse', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php echo esc_html__( 'Optimise les images déjà présentes dans la médiathèque.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-bulk-status">
				<div class="skmt-bulk-status__info">
					<span class="skmt-bulk-status__count">
						<?php $remaining = (int) ( $preview['remaining'] ?? 0 ); ?>
						<span id="skmt-bulk-remaining"><?php echo esc_html( $remaining ); ?></span>
						<?php echo esc_html__( 'images à optimiser', 'studio-kyne-mini-tools' ); ?>
					</span>
					<span class="skmt-form__help">
						<?php echo esc_html__( 'Gains potentiels :', 'studio-kyne-mini-tools' ); ?>
						<strong id="skmt-bulk-potential"><?php echo esc_html( size_format( $estimated_saved, 2 ) ); ?></strong>
					</span>
				</div>
				<div class="skmt-bulk-status__progress" style="display: none;">
					<div class="skmt-progress">
						<div class="skmt-progress__bar" style="width: 0%"></div>
					</div>
					<span class="skmt-bulk-status__message"></span>
				</div>
				<button type="button" id="skmt-bulk-start" class="skmt-btn skmt-btn--primary"
						<?php disabled( 0 === $remaining ); ?>>
					<?php echo esc_html__( 'Lancer l\'optimisation', 'studio-kyne-mini-tools' ); ?>
				</button>
			</div>
		</div>
	</div>

	<div class="skmt-page__footer">
		<button type="submit" class="skmt-btn skmt-btn--primary">
			<?php echo esc_html__( 'Enregistrer les réglages', 'studio-kyne-mini-tools' ); ?>
		</button>
	</div>
</form>
