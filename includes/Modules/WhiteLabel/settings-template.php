<?php
/**
 * Template des réglages du module Marque Blanche.
 *
 * Variables disponibles (via module-settings.php) :
 * @var string          $module_id       ID du module (white_label)
 * @var array           $module          Infos du module
 * @var ModuleInterface $instance        Instance du module
 * @var array           $module_settings Settings actuels
 * @var string          $tab             Onglet actif
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ab     = $module_settings['admin_bar'] ?? [];
$footer = $module_settings['footer'] ?? [];
?>

<form id="skmt-module-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="skmt-form">
	<?php wp_nonce_field( 'skmt_save_settings', 'skmt_nonce' ); ?>
	<input type="hidden" name="action" value="skmt_save_settings">
	<input type="hidden" name="skmt_tab" value="<?php echo esc_attr( $tab ); ?>">

	<!-- ============================================================
		 BARRE D'ADMINISTRATION
		 ============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Barre d\'administration', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Masquez les éléments inutiles de la barre d\'administration WordPress.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">

			<!-- Logo WordPress -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<p class="skmt-option__label"><?php esc_html_e( 'Masquer le logo WordPress', 'studio-kyne-mini-tools' ); ?></p>
					<p class="skmt-option__desc"><?php esc_html_e( 'Supprime le logo WP et son menu déroulant en haut à gauche.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" name="skmt_module_settings[admin_bar][hide_wp_logo]" value="1"
							<?php checked( ! empty( $ab['hide_wp_logo'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Icône d'accueil / nom du site -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<p class="skmt-option__label"><?php esc_html_e( 'Masquer l\'icône d\'accueil et le nom du site', 'studio-kyne-mini-tools' ); ?></p>
					<p class="skmt-option__desc"><?php esc_html_e( 'Supprime l\'icône maison et le nom du site dans la barre admin.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" name="skmt_module_settings[admin_bar][hide_site_menu]" value="1"
							<?php checked( ! empty( $ab['hide_site_menu'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Palette de commandes -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<p class="skmt-option__label"><?php esc_html_e( 'Masquer la palette de commandes', 'studio-kyne-mini-tools' ); ?></p>
					<p class="skmt-option__desc"><?php esc_html_e( 'Disponible depuis WordPress 6.7+. Supprime le bouton de palette de commandes.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" name="skmt_module_settings[admin_bar][hide_command_palette]" value="1"
							<?php checked( ! empty( $ab['hide_command_palette'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Compteur de mises à jour -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<p class="skmt-option__label"><?php esc_html_e( 'Masquer le compteur de mises à jour', 'studio-kyne-mini-tools' ); ?></p>
					<p class="skmt-option__desc"><?php esc_html_e( 'Supprime l\'icône et le badge indiquant les mises à jour disponibles.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" name="skmt_module_settings[admin_bar][hide_updates_counter]" value="1"
							<?php checked( ! empty( $ab['hide_updates_counter'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Compteur de commentaires -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<p class="skmt-option__label"><?php esc_html_e( 'Masquer le compteur de commentaires', 'studio-kyne-mini-tools' ); ?></p>
					<p class="skmt-option__desc"><?php esc_html_e( 'Supprime l\'icône et le badge indiquant les commentaires en attente.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" name="skmt_module_settings[admin_bar][hide_comments_counter]" value="1"
							<?php checked( ! empty( $ab['hide_comments_counter'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Menu Ajouter -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<p class="skmt-option__label"><?php esc_html_e( 'Masquer le menu « Ajouter »', 'studio-kyne-mini-tools' ); ?></p>
					<p class="skmt-option__desc"><?php esc_html_e( 'Supprime le bouton « + Ajouter » permettant de créer rapidement du contenu.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" name="skmt_module_settings[admin_bar][hide_new_content_menu]" value="1"
							<?php checked( ! empty( $ab['hide_new_content_menu'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Bouton Aide -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<p class="skmt-option__label"><?php esc_html_e( 'Masquer le bouton Aide', 'studio-kyne-mini-tools' ); ?></p>
					<p class="skmt-option__desc"><?php esc_html_e( 'Masque l\'onglet « Aide » en haut à droite de chaque page admin.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" name="skmt_module_settings[admin_bar][hide_help_button]" value="1"
							<?php checked( ! empty( $ab['hide_help_button'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Options de l'écran -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<p class="skmt-option__label"><?php esc_html_e( 'Masquer le bouton Options de l\'écran', 'studio-kyne-mini-tools' ); ?></p>
					<p class="skmt-option__desc"><?php esc_html_e( 'Masque l\'onglet « Options de l\'écran » en haut à droite de chaque page admin.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" name="skmt_module_settings[admin_bar][hide_screen_options]" value="1"
							<?php checked( ! empty( $ab['hide_screen_options'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Supprimer Howdy -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<p class="skmt-option__label"><?php esc_html_e( 'Supprimer la salutation', 'studio-kyne-mini-tools' ); ?></p>
					<p class="skmt-option__desc"><?php esc_html_e( 'Supprime « Howdy, » / « Bonjour, » devant le nom de l\'utilisateur connecté, quelle que soit la langue.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" name="skmt_module_settings[admin_bar][remove_howdy]" value="1"
							<?php checked( ! empty( $ab['remove_howdy'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Masquer barre côté site -->
			<div class="skmt-option skmt-option--frontend-separator">
				<div class="skmt-option__content">
					<p class="skmt-option__label"><?php esc_html_e( 'Masquer la barre d\'administration sur le site', 'studio-kyne-mini-tools' ); ?></p>
					<p class="skmt-option__desc"><?php esc_html_e( 'Cache entièrement la barre d\'administration pour les visiteurs du site (front-end).', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" name="skmt_module_settings[admin_bar][hide_frontend]" value="1"
							<?php checked( ! empty( $ab['hide_frontend'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

		</div>
	</div>

	<!-- ============================================================
		 FOOTER ADMIN
		 ============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Footer Admin', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Personnalisez les textes affichés dans le pied de page de l\'administration WordPress.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">

			<!-- Texte gauche -->
			<div class="skmt-form__group">
				<label class="skmt-form__label" for="skmt-wl-left-text"><?php esc_html_e( 'Texte gauche du footer', 'studio-kyne-mini-tools' ); ?></label>
				<textarea class="skmt-input" id="skmt-wl-left-text" name="skmt_module_settings[footer][left_text]" rows="2"><?php echo esc_textarea( $footer['left_text'] ?? '' ); ?></textarea>
				<p class="skmt-form__help"><?php esc_html_e( 'Supporte le HTML basique (liens, balises em/strong). Laissez vide pour garder la valeur WordPress par défaut.', 'studio-kyne-mini-tools' ); ?></p>
			</div>

			<!-- Masquer version WordPress -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<p class="skmt-option__label"><?php esc_html_e( 'Masquer la version WordPress (texte droit)', 'studio-kyne-mini-tools' ); ?></p>
					<p class="skmt-option__desc"><?php esc_html_e( 'Supprime l\'indication « Version X.X.X » en bas à droite de chaque page admin.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" id="skmt-wl-hide-right" name="skmt_module_settings[footer][hide_right_text]" value="1"
							<?php checked( ! empty( $footer['hide_right_text'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Texte droit custom -->
			<div class="skmt-form__group">
				<label class="skmt-form__label" for="skmt-wl-right-text"><?php esc_html_e( 'Texte droit du footer (si non masqué)', 'studio-kyne-mini-tools' ); ?></label>
				<input type="text" class="skmt-input" id="skmt-wl-right-text" name="skmt_module_settings[footer][right_text]" value="<?php echo esc_attr( $footer['right_text'] ?? '' ); ?>">
				<p class="skmt-form__help"><?php esc_html_e( 'Remplace « Version X.X.X ». Laissez vide pour garder la valeur WordPress par défaut.', 'studio-kyne-mini-tools' ); ?></p>
			</div>

		</div>
	</div>

	<div class="skmt-page__footer">
		<button type="submit" class="skmt-btn skmt-btn--primary">
			<?php esc_html_e( 'Enregistrer les réglages', 'studio-kyne-mini-tools' ); ?>
		</button>
	</div>

</form>
<script>
(function() {
	var toggle = document.getElementById('skmt-wl-hide-right');
	var field  = document.getElementById('skmt-wl-right-text');
	if (!toggle || !field) return;
	function sync() { field.disabled = toggle.checked; field.closest('.skmt-form__group').style.opacity = toggle.checked ? '0.4' : '1'; }
	toggle.addEventListener('change', sync);
	sync();
})();
</script>
