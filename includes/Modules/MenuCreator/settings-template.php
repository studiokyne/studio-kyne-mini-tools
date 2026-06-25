<?php
/**
 * Template des réglages du module Créateur de menu.
 * Rend l'éditeur 3 colonnes intégré dans l'admin du plugin.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$svg = [
	'search'    => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
	'plus'      => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>',
	'grip'      => '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="12" r="1"/><circle cx="9" cy="5" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="19" r="1"/></svg>',
	'eye'       => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>',
	'eye-off'   => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>',
	'trash'     => '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
	'copy'      => '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>',
	'x'         => '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
	'chevron-r' => '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>',
	'chevron-d' => '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>',
	'chevron-u' => '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>',
	'chevron-l' => '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>',
	'menu-ph'   => '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/></svg>',
];
?>
<div class="skmt-mc-editor" id="skmt-mc-editor">

	<!-- ============================================================
		 COLONNE GAUCHE — liste des menus
		 ============================================================ -->
	<aside class="skmt-wl-ep__profiles-col">

		<div class="skmt-wl-ep__profiles-header">
			<span class="skmt-wl-ep__profiles-title"><?php esc_html_e( 'Menus', 'studio-kyne-mini-tools' ); ?></span>
			<button type="button" class="skmt-wl-ep__profiles-add" id="skmt-mc-new-btn" title="<?php esc_attr_e( 'Nouveau menu', 'studio-kyne-mini-tools' ); ?>">
				<?php echo $svg['plus']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
		</div>

		<div class="skmt-wl-ep__profiles-search-wrap">
			<?php echo $svg['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="search" class="skmt-wl-ep__profiles-search" id="skmt-wl-ep-search" placeholder="<?php esc_attr_e( 'Rechercher…', 'studio-kyne-mini-tools' ); ?>">
		</div>

		<div class="skmt-wl-ep__profiles-tabs">
			<button type="button" class="skmt-wl-ep__profiles-tab is-active" data-filter="all"><?php esc_html_e( 'Tous', 'studio-kyne-mini-tools' ); ?></button>
			<button type="button" class="skmt-wl-ep__profiles-tab" data-filter="active"><?php esc_html_e( 'Actifs', 'studio-kyne-mini-tools' ); ?></button>
			<button type="button" class="skmt-wl-ep__profiles-tab" data-filter="draft"><?php esc_html_e( 'Brouillons', 'studio-kyne-mini-tools' ); ?></button>
		</div>

		<div class="skmt-wl-ep__profiles-list" id="skmt-wl-ep-profiles-list"></div>

	</aside>

	<!-- ============================================================
		 COLONNE CENTRALE — placeholder + arbre
		 ============================================================ -->
	<div class="skmt-wl-ep__tree-col" id="skmt-mc-tree-col">

		<div class="skmt-wl-ep__tree-actions" id="skmt-mc-tree-actions" style="display:none">
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-wl-add-sep">
				+ <?php esc_html_e( 'Séparateur', 'studio-kyne-mini-tools' ); ?>
			</button>
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-wl-add-link">
				+ <?php esc_html_e( 'Lien personnalisé', 'studio-kyne-mini-tools' ); ?>
			</button>
		</div>

		<div class="skmt-mc-placeholder" id="skmt-mc-placeholder">
			<span class="skmt-mc-placeholder__icon"><?php echo $svg['menu-ph']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<h3><?php esc_html_e( 'Éditeur de menu', 'studio-kyne-mini-tools' ); ?></h3>
			<p><?php esc_html_e( 'Sélectionnez un menu dans la liste ou créez-en un nouveau pour modifier sa structure et ses paramètres.', 'studio-kyne-mini-tools' ); ?></p>
		</div>

		<div class="skmt-wl-ep__tree" id="skmt-wl-tree" style="display:none"></div>

	</div>

	<!-- ============================================================
		 COLONNE DROITE — paramètres (direct switch, no tabs)
		 ============================================================ -->
	<div class="skmt-wl-ep__settings-col" id="skmt-wl-settings-col" style="display:none">

		<!-- En-tête du panel (titre dynamique + bouton retour) -->
		<div class="skmt-mc-panel-header">
			<button type="button" class="skmt-mc-back-btn" id="skmt-mc-back-btn" style="display:none" title="<?php esc_attr_e( 'Retour aux paramètres du menu', 'studio-kyne-mini-tools' ); ?>">
				<?php echo $svg['chevron-l']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php esc_html_e( 'Menu', 'studio-kyne-mini-tools' ); ?></span>
			</button>
			<span class="skmt-mc-panel-title" id="skmt-mc-panel-title"><?php esc_html_e( 'Paramètres du menu', 'studio-kyne-mini-tools' ); ?></span>
		</div>

		<!-- Panel profil -->
		<div id="skmt-wl-profile-settings" class="skmt-wl-settings-panel">

			<div class="skmt-wl-settings-row">
				<div class="skmt-wl-settings-row__label">
					<span><?php esc_html_e( 'Nom du menu', 'studio-kyne-mini-tools' ); ?></span>
					<p class="skmt-form__help"><?php esc_html_e( 'Identifiant interne du profil.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<input type="text" class="skmt-input" id="skmt-wl-profile-name"
					placeholder="<?php esc_attr_e( 'Nom du menu…', 'studio-kyne-mini-tools' ); ?>">
			</div>

			<div class="skmt-wl-settings-row">
				<div class="skmt-wl-settings-row__label">
					<span><?php esc_html_e( 'Statut', 'studio-kyne-mini-tools' ); ?></span>
					<p class="skmt-form__help"><?php esc_html_e( 'Activez ce menu pour qu\'il s\'applique aux utilisateurs ciblés.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-mc-status-group">
					<div class="skmt-wl-seg">
						<button type="button" class="skmt-wl-seg__btn" data-value="draft"  id="skmt-wl-status-draft"><?php  esc_html_e( 'Brouillon', 'studio-kyne-mini-tools' ); ?></button>
						<button type="button" class="skmt-wl-seg__btn" data-value="active" id="skmt-wl-status-active"><?php esc_html_e( 'Actif', 'studio-kyne-mini-tools' ); ?></button>
					</div>
					<span class="skmt-mc-status-badge" id="skmt-mc-status-badge"></span>
				</div>
			</div>

			<div class="skmt-wl-settings-row skmt-wl-settings-row--inline">
				<div class="skmt-wl-settings-row__label">
					<span><?php esc_html_e( 'Appliquer à tous les utilisateurs', 'studio-kyne-mini-tools' ); ?></span>
					<p class="skmt-form__help"><?php esc_html_e( 'Ce menu sera appliqué à tous, sans restriction.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<label class="skmt-toggle">
					<input type="checkbox" id="skmt-wl-apply-all">
					<span class="skmt-toggle__slider"></span>
				</label>
			</div>

			<div class="skmt-wl-settings-row skmt-wl-settings-row--col" id="skmt-wl-targeting-rows">
				<div class="skmt-wl-settings-row__label">
					<span><?php esc_html_e( 'Inclure — rôles ou utilisateurs', 'studio-kyne-mini-tools' ); ?></span>
					<p class="skmt-form__help"><?php esc_html_e( 'Ce menu s\'applique à ces rôles / utilisateurs.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-wl-multiselect" id="skmt-wl-include-select"></div>
			</div>

			<div class="skmt-wl-settings-row skmt-wl-settings-row--col" id="skmt-wl-targeting-rows-ex">
				<div class="skmt-wl-settings-row__label">
					<span><?php esc_html_e( 'Exclure — rôles ou utilisateurs', 'studio-kyne-mini-tools' ); ?></span>
					<p class="skmt-form__help"><?php esc_html_e( 'Ces rôles / utilisateurs ne verront pas ce menu.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-wl-multiselect" id="skmt-wl-exclude-select"></div>
			</div>

		</div>

		<!-- Panel item -->
		<div id="skmt-wl-item-settings" class="skmt-wl-settings-panel" style="display:none">
			<div id="skmt-wl-item-fields"></div>
		</div>

		<!-- Pied de page ancré en bas : Enregistrer + Réinitialiser -->
		<div class="skmt-mc-panel-footer">
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-mc-reset-menu-btn">
				<?php esc_html_e( 'Réinitialiser', 'studio-kyne-mini-tools' ); ?>
			</button>
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="skmt-mc-save-panel-btn" disabled>
				<?php esc_html_e( 'Enregistrer', 'studio-kyne-mini-tools' ); ?>
			</button>
		</div>

	</div>
</div><!-- .skmt-mc-editor -->

<script>
window.skmtLucide = {
	grip:        <?php echo wp_json_encode( $svg['grip'] ); ?>,
	eye:         <?php echo wp_json_encode( $svg['eye'] ); ?>,
	eyeOff:      <?php echo wp_json_encode( $svg['eye-off'] ); ?>,
	trash:       <?php echo wp_json_encode( $svg['trash'] ); ?>,
	copy:        <?php echo wp_json_encode( $svg['copy'] ); ?>,
	x:           <?php echo wp_json_encode( $svg['x'] ); ?>,
	chevronR:    <?php echo wp_json_encode( $svg['chevron-r'] ); ?>,
	chevronD:    <?php echo wp_json_encode( $svg['chevron-d'] ); ?>,
	chevronU:    <?php echo wp_json_encode( $svg['chevron-u'] ); ?>,
};
</script>
