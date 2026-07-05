<?php
/**
 * Template des réglages du module Image Optimizer.
 *
 * Variables disponibles : $instance, $tab, $module_id, $module
 */

$module_settings = $instance->get_settings();
?>

<form id="skmt-module-form" class="skmt-form skmt-module-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'skmt_save_settings', 'skmt_nonce' ); ?>
	<input type="hidden" name="action" value="skmt_save_settings">
	<input type="hidden" name="skmt_tab" value="<?php echo esc_attr( $tab ); ?>">

	<div class="skmt-module-form__scroll">

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

	<div class="skmt-divider"></div>

	<!-- Téléchargements SVG -->
	<?php $svg_enabled = ! empty( $module_settings['svg_upload'] ); ?>
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php echo esc_html__( 'Téléchargements SVG', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php echo esc_html__( 'Les fichiers SVG sont du XML et peuvent contenir du code malveillant. Une fois activés, chaque SVG téléversé est automatiquement assaini (suppression du JavaScript, des gestionnaires d\'événements et des références externes).', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_svg_upload" class="skmt-option__label"><?php echo esc_html__( 'Autoriser l\'upload de SVG', 'studio-kyne-mini-tools' ); ?></label>
					<p class="skmt-option__desc"><?php echo esc_html__( 'Active l\'upload de fichiers .svg assainis dans la médiathèque.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox"
							   id="skmt_svg_upload"
							   name="skmt_module_settings[svg_upload]"
							   value="1"
							   data-svg-master
							   <?php checked( $svg_enabled, true ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<?php $svg_roles = (array) ( $module_settings['svg_roles'] ?? [] ); ?>
			<div class="skmt-svg-roles<?php echo $svg_enabled ? '' : ' is-disabled'; ?>" id="skmt-svg-roles">
				<div class="skmt-svg-roles__head">
					<span class="skmt-svg-roles__title"><?php echo esc_html__( 'Rôles autorisés', 'studio-kyne-mini-tools' ); ?></span>
					<span class="skmt-svg-roles__hint"><?php echo esc_html__( 'Seuls ces rôles pourront téléverser des SVG.', 'studio-kyne-mini-tools' ); ?></span>
				</div>
				<div class="skmt-svg-roles__grid">
					<?php foreach ( wp_roles()->get_names() as $role_slug => $role_name ) : ?>
						<label class="skmt-svg-role">
							<span class="skmt-toggle">
								<input type="checkbox"
									   name="skmt_module_settings[svg_roles][]"
									   value="<?php echo esc_attr( $role_slug ); ?>"
									   <?php checked( in_array( $role_slug, $svg_roles, true ), true ); ?>>
								<span class="skmt-toggle__slider"></span>
							</span>
							<span class="skmt-svg-role__name"><?php echo esc_html( translate_user_role( $role_name ) ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>

	<div class="skmt-divider"></div>

	<!-- Optimisation en masse -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php echo esc_html__( 'Optimisation en masse', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php echo esc_html__( 'Optimise les images déjà présentes dans la médiathèque.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-bulk" id="skmt-bulk">

				<!-- État initial : lancer un scan avant d'afficher des chiffres -->
				<div class="skmt-bulk__scan" id="skmt-bulk-scan-intro">
					<p class="skmt-bulk__scan-hint">
						<?php echo esc_html__( 'Analysez la médiathèque pour connaître le nombre d\'images restant à optimiser.', 'studio-kyne-mini-tools' ); ?>
					</p>
					<button type="button" id="skmt-bulk-scan" class="skmt-btn skmt-btn--secondary">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
						<?php echo esc_html__( 'Scanner la médiathèque', 'studio-kyne-mini-tools' ); ?>
					</button>
				</div>

				<!-- Résultat du scan (révélé par le JS) -->
				<div class="skmt-bulk__result" id="skmt-bulk-result" style="display: none;">
					<div class="skmt-bulk__stats">
						<div class="skmt-bulk__stat">
							<span class="skmt-bulk__stat-value" id="skmt-bulk-remaining">0</span>
							<span class="skmt-bulk__stat-label"><?php echo esc_html__( 'images à optimiser', 'studio-kyne-mini-tools' ); ?></span>
						</div>
						<div class="skmt-bulk__stat" id="skmt-bulk-potential-tile" style="display: none;">
							<span class="skmt-bulk__stat-value" id="skmt-bulk-potential">—</span>
							<span class="skmt-bulk__stat-label"><?php echo esc_html__( 'gains potentiels estimés', 'studio-kyne-mini-tools' ); ?></span>
						</div>
					</div>
					<div class="skmt-bulk__action">
						<button type="button" id="skmt-bulk-start" class="skmt-btn skmt-btn--primary" disabled>
							<?php echo esc_html__( 'Lancer l\'optimisation', 'studio-kyne-mini-tools' ); ?>
						</button>
					</div>
				</div>

				<!-- Progression -->
				<div class="skmt-bulk__progress skmt-bulk-status__progress" style="display: none;">
					<div class="skmt-progress">
						<div class="skmt-progress__bar" style="width: 0%"></div>
					</div>
					<span class="skmt-bulk-status__message"></span>
				</div>
			</div>
		</div>
	</div>

	</div><!-- .skmt-module-form__scroll -->
</form>
