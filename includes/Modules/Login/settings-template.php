<?php
/**
 * Template des réglages du module Connexion.
 *
 * Variables disponibles (via module-settings.php) :
 * @var string          $module_id       ID du module (login)
 * @var array           $module          Infos du module
 * @var ModuleInterface $instance        Instance du module
 * @var array           $module_settings Settings actuels
 * @var string          $tab             Onglet actif
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$layout   = $module_settings['layout']   ?? [];
$branding = $module_settings['branding'] ?? [];
$form     = $module_settings['form']     ?? [];

// Images courantes
$panel_img_id  = absint( $layout['panel_image_id'] ?? 0 );
$panel_img_url = $panel_img_id ? wp_get_attachment_image_url( $panel_img_id, 'medium' ) : '';

$logo_id  = absint( $branding['logo_id'] ?? 0 );
$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
?>

<form id="skmt-module-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="skmt-form">
	<?php wp_nonce_field( 'skmt_save_settings', 'skmt_nonce' ); ?>
	<input type="hidden" name="action" value="skmt_save_settings">
	<input type="hidden" name="skmt_tab" value="<?php echo esc_attr( $tab ); ?>">

	<!-- ============================================================
		 LAYOUT — PANNEAU IMAGE
		 ============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Layout', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Panneau visuel affiché à droite du formulaire de connexion.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">

			<!-- Image du panneau -->
			<div class="skmt-option skmt-option--column">
				<div class="skmt-option__content">
					<span class="skmt-option__label"><?php esc_html_e( 'Image du panneau', 'studio-kyne-mini-tools' ); ?></span>
					<p class="skmt-option__desc"><?php esc_html_e( 'Image de fond du panneau droit. Si vide, la couleur de fond est utilisée.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control skmt-option__control--full">
					<div class="skmt-media-picker" data-picker="panel_image">
						<input type="hidden" name="skmt_module_settings[layout][panel_image_id]" id="skmt_panel_image_id" value="<?php echo esc_attr( $panel_img_id ); ?>">
						<div class="skmt-media-preview <?php echo $panel_img_url ? 'has-image' : ''; ?>">
							<?php if ( $panel_img_url ) : ?>
								<img src="<?php echo esc_url( $panel_img_url ); ?>" alt="">
							<?php endif; ?>
						</div>
						<div class="skmt-media-actions">
							<button type="button" class="skmt-btn skmt-btn--secondary skmt-btn--sm skmt-media-select" data-title="<?php esc_attr_e( 'Choisir une image', 'studio-kyne-mini-tools' ); ?>" data-button="<?php esc_attr_e( 'Utiliser cette image', 'studio-kyne-mini-tools' ); ?>">
								<?php esc_html_e( 'Choisir une image', 'studio-kyne-mini-tools' ); ?>
							</button>
							<button type="button" class="skmt-btn skmt-btn--secondary skmt-btn--sm skmt-media-remove <?php echo ! $panel_img_url ? 'is-hidden' : ''; ?>">
								<?php esc_html_e( 'Supprimer', 'studio-kyne-mini-tools' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Couleur de fond panneau -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_panel_bg_color" class="skmt-option__label">
						<?php esc_html_e( 'Couleur de fond du panneau', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc"><?php esc_html_e( 'Utilisée comme fallback si aucune image n\'est définie.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<div class="skmt-color-field">
						<input
							type="color"
							id="skmt_panel_bg_color"
							name="skmt_module_settings[layout][panel_bg_color]"
							value="<?php echo esc_attr( $layout['panel_bg_color'] ?? '#eaeaea' ); ?>"
						>
						<span class="skmt-color-field__value"><?php echo esc_html( $layout['panel_bg_color'] ?? '#eaeaea' ); ?></span>
						<button type="button" class="skmt-color-reset" data-default="#eaeaea" title="<?php esc_attr_e( 'Réinitialiser', 'studio-kyne-mini-tools' ); ?>" aria-label="<?php esc_attr_e( 'Réinitialiser la couleur', 'studio-kyne-mini-tools' ); ?>">↩</button>
					</div>
				</div>
			</div>

		</div>
	</div>

	<div class="skmt-divider"></div>

	<!-- ============================================================
		 BRANDING — LOGO
		 ============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Branding', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Remplacez le logo WordPress par le vôtre.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">

			<!-- Logo custom -->
			<div class="skmt-option skmt-option--column">
				<div class="skmt-option__content">
					<span class="skmt-option__label"><?php esc_html_e( 'Logo personnalisé', 'studio-kyne-mini-tools' ); ?></span>
					<p class="skmt-option__desc"><?php esc_html_e( 'Remplace le logo WordPress par défaut. Format recommandé : PNG transparent ou SVG.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control skmt-option__control--full">
					<div class="skmt-media-picker" data-picker="logo">
						<input type="hidden" name="skmt_module_settings[branding][logo_id]" id="skmt_logo_id" value="<?php echo esc_attr( $logo_id ); ?>">
						<div class="skmt-media-preview skmt-media-preview--logo <?php echo $logo_url ? 'has-image' : ''; ?>">
							<?php if ( $logo_url ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="">
							<?php endif; ?>
						</div>
						<div class="skmt-media-actions">
							<button type="button" class="skmt-btn skmt-btn--secondary skmt-btn--sm skmt-media-select" data-title="<?php esc_attr_e( 'Choisir un logo', 'studio-kyne-mini-tools' ); ?>" data-button="<?php esc_attr_e( 'Utiliser ce logo', 'studio-kyne-mini-tools' ); ?>">
								<?php esc_html_e( 'Choisir un logo', 'studio-kyne-mini-tools' ); ?>
							</button>
							<button type="button" class="skmt-btn skmt-btn--secondary skmt-btn--sm skmt-media-remove <?php echo ! $logo_url ? 'is-hidden' : ''; ?>">
								<?php esc_html_e( 'Supprimer', 'studio-kyne-mini-tools' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Largeur du logo -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_logo_width" class="skmt-option__label">
						<?php esc_html_e( 'Largeur du logo', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc"><?php esc_html_e( 'Largeur maximale du logo en pixels (entre 40 et 600).', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<div class="skmt-input-unit">
						<input
							type="number"
							id="skmt_logo_width"
							name="skmt_module_settings[branding][logo_width]"
							value="<?php echo esc_attr( $branding['logo_width'] ?? 150 ); ?>"
							min="40"
							max="600"
							step="1"
							class="skmt-input skmt-input--sm"
						>
						<span class="skmt-input-unit__label">px</span>
					</div>
				</div>
			</div>

		</div>
	</div>

	<div class="skmt-divider"></div>

	<!-- ============================================================
		 COULEURS
		 ============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Couleurs', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Personnalisez les couleurs du formulaire de connexion.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">

			<!-- Couleur de fond -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_bg_color" class="skmt-option__label"><?php esc_html_e( 'Fond de la page', 'studio-kyne-mini-tools' ); ?></label>
					<p class="skmt-option__desc"><?php esc_html_e( 'Couleur de fond de la zone formulaire.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<div class="skmt-color-field">
						<input type="color" id="skmt_bg_color" name="skmt_module_settings[form][bg_color]" value="<?php echo esc_attr( $form['bg_color'] ?? '#f7f7f7' ); ?>">
						<span class="skmt-color-field__value"><?php echo esc_html( $form['bg_color'] ?? '#f7f7f7' ); ?></span>
						<button type="button" class="skmt-color-reset" data-default="#f7f7f7" title="<?php esc_attr_e( 'Réinitialiser', 'studio-kyne-mini-tools' ); ?>" aria-label="<?php esc_attr_e( 'Réinitialiser la couleur', 'studio-kyne-mini-tools' ); ?>">↩</button>
					</div>
				</div>
			</div>

			<!-- Couleur bouton -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_btn_bg_color" class="skmt-option__label"><?php esc_html_e( 'Fond du bouton', 'studio-kyne-mini-tools' ); ?></label>
				</div>
				<div class="skmt-option__control">
					<div class="skmt-color-field">
						<input type="color" id="skmt_btn_bg_color" name="skmt_module_settings[form][btn_bg_color]" value="<?php echo esc_attr( $form['btn_bg_color'] ?? '#615FFF' ); ?>">
						<span class="skmt-color-field__value"><?php echo esc_html( $form['btn_bg_color'] ?? '#615FFF' ); ?></span>
						<button type="button" class="skmt-color-reset" data-default="#615FFF" title="<?php esc_attr_e( 'Réinitialiser', 'studio-kyne-mini-tools' ); ?>" aria-label="<?php esc_attr_e( 'Réinitialiser la couleur', 'studio-kyne-mini-tools' ); ?>">↩</button>
					</div>
				</div>
			</div>

			<!-- Couleur texte bouton -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_btn_text_color" class="skmt-option__label"><?php esc_html_e( 'Texte du bouton', 'studio-kyne-mini-tools' ); ?></label>
				</div>
				<div class="skmt-option__control">
					<div class="skmt-color-field">
						<input type="color" id="skmt_btn_text_color" name="skmt_module_settings[form][btn_text_color]" value="<?php echo esc_attr( $form['btn_text_color'] ?? '#ffffff' ); ?>">
						<span class="skmt-color-field__value"><?php echo esc_html( $form['btn_text_color'] ?? '#ffffff' ); ?></span>
						<button type="button" class="skmt-color-reset" data-default="#ffffff" title="<?php esc_attr_e( 'Réinitialiser', 'studio-kyne-mini-tools' ); ?>" aria-label="<?php esc_attr_e( 'Réinitialiser la couleur', 'studio-kyne-mini-tools' ); ?>">↩</button>
					</div>
				</div>
			</div>

			<!-- Couleur liens -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_link_color" class="skmt-option__label"><?php esc_html_e( 'Couleur des liens', 'studio-kyne-mini-tools' ); ?></label>
				</div>
				<div class="skmt-option__control">
					<div class="skmt-color-field">
						<input type="color" id="skmt_link_color" name="skmt_module_settings[form][link_color]" value="<?php echo esc_attr( $form['link_color'] ?? '#615FFF' ); ?>">
						<span class="skmt-color-field__value"><?php echo esc_html( $form['link_color'] ?? '#615FFF' ); ?></span>
						<button type="button" class="skmt-color-reset" data-default="#615FFF" title="<?php esc_attr_e( 'Réinitialiser', 'studio-kyne-mini-tools' ); ?>" aria-label="<?php esc_attr_e( 'Réinitialiser la couleur', 'studio-kyne-mini-tools' ); ?>">↩</button>
					</div>
				</div>
			</div>

		</div>
	</div>

	<div class="skmt-divider"></div>

	<!-- ============================================================
		 OPTIONS DIVERSES
		 ============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Options', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Éléments à masquer sur la page de connexion.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">

			<!-- Masquer sélecteur de langue -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_hide_language" class="skmt-option__label">
						<?php esc_html_e( 'Masquer le sélecteur de langue', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc">
						<?php esc_html_e( 'Cache le menu déroulant de sélection de langue en bas du formulaire.', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input
							type="checkbox"
							id="skmt_hide_language"
							name="skmt_module_settings[form][hide_language_switcher]"
							value="1"
							<?php checked( ! empty( $form['hide_language_switcher'] ) ); ?>
						>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Masquer mot de passe oublié -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_hide_lost_password" class="skmt-option__label">
						<?php esc_html_e( 'Masquer le lien « Mot de passe oublié »', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc">
						<?php esc_html_e( 'Cache le lien de récupération de mot de passe sous le formulaire.', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input
							type="checkbox"
							id="skmt_hide_lost_password"
							name="skmt_module_settings[form][hide_lost_password]"
							value="1"
							<?php checked( ! empty( $form['hide_lost_password'] ) ); ?>
						>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Masquer lien retour au site -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_hide_back_to_blog" class="skmt-option__label">
						<?php esc_html_e( 'Masquer le lien « Aller sur le site »', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc">
						<?php esc_html_e( 'Cache le lien de retour vers l\'accueil du site en bas du formulaire.', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input
							type="checkbox"
							id="skmt_hide_back_to_blog"
							name="skmt_module_settings[form][hide_back_to_blog]"
							value="1"
							<?php checked( ! empty( $form['hide_back_to_blog'] ) ); ?>
						>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

		</div>
	</div>

	<div class="skmt-page__footer">
		<button type="submit" class="skmt-btn skmt-btn--primary">
			<?php esc_html_e( 'Enregistrer les réglages', 'studio-kyne-mini-tools' ); ?>
		</button>
	</div>

</form>
