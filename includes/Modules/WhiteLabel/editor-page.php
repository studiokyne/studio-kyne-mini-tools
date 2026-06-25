<?php
/**
 * Page admin dédiée au créateur de menu Marque Blanche.
 * Incluse par Module::render_editor_page().
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$list_url   = admin_url( 'admin.php?page=studio-kyne-mini-tools&tab=module_white_label' );
$editor_url = admin_url( 'admin.php?page=studio-kyne-mini-tools-menu-editor' );
$profile_id = isset( $_GET['profile_id'] ) ? sanitize_text_field( wp_unslash( $_GET['profile_id'] ) ) : '__new__'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$is_new     = '__new__' === $profile_id || empty( $profile_id );

// SVGs Lucide utilisés dans ce template
$svg = [
	'arrow-left'    => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>',
	'grip-vertical' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="12" r="1"/><circle cx="9" cy="5" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="19" r="1"/></svg>',
	'eye'           => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>',
	'eye-off'       => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>',
	'trash-2'       => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
	'x'             => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
	'chevron-right' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>',
	'search'        => '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
	'plus'          => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>',
];
?>
<div class="skmt-wl-ep">

	<!-- ============================================================
		 TOPBAR
		 ============================================================ -->
	<div class="skmt-wl-ep__topbar">
		<div class="skmt-wl-ep__topbar-left">
			<a href="<?php echo esc_url( $list_url ); ?>" class="skmt-wl-ep__back" id="skmt-wl-back-btn">
				<?php echo $svg['arrow-left']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Marque Blanche', 'studio-kyne-mini-tools' ); ?>
			</a>
			<span class="skmt-wl-ep__topbar-sep" aria-hidden="true">/</span>
			<h1 class="skmt-wl-ep__title" id="skmt-wl-ep-title">
				<?php echo $is_new ? esc_html__( 'Nouveau menu', 'studio-kyne-mini-tools' ) : esc_html__( 'Modifier le menu', 'studio-kyne-mini-tools' ); ?>
			</h1>
		</div>
		<div class="skmt-wl-ep__topbar-actions">
			<?php if ( ! $is_new ) : ?>
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--danger" id="skmt-wl-delete-btn">
				<?php echo $svg['trash-2']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Supprimer', 'studio-kyne-mini-tools' ); ?>
			</button>
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-wl-duplicate-btn">
				<?php esc_html_e( 'Dupliquer', 'studio-kyne-mini-tools' ); ?>
			</button>
			<?php endif; ?>
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="skmt-wl-save-btn">
				<?php esc_html_e( 'Mettre à jour', 'studio-kyne-mini-tools' ); ?>
			</button>
		</div>
	</div>

	<!-- ============================================================
		 CORPS (3 colonnes)
		 ============================================================ -->
	<div class="skmt-wl-ep__body">

		<!-- Colonne gauche : liste des menus -->
		<aside class="skmt-wl-ep__profiles-col">
			<div class="skmt-wl-ep__profiles-header">
				<span class="skmt-wl-ep__profiles-title"><?php esc_html_e( 'Créateur de menu', 'studio-kyne-mini-tools' ); ?></span>
				<a href="<?php echo esc_url( $editor_url . '&profile_id=__new__' ); ?>" class="skmt-wl-ep__profiles-add" title="<?php esc_attr_e( 'Nouveau menu', 'studio-kyne-mini-tools' ); ?>">
					<?php echo $svg['plus']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			</div>
			<div class="skmt-wl-ep__profiles-search-wrap">
				<span class="skmt-wl-ep__profiles-search-icon"><?php echo $svg['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<input type="search" class="skmt-wl-ep__profiles-search" id="skmt-wl-ep-search" placeholder="<?php esc_attr_e( 'Rechercher…', 'studio-kyne-mini-tools' ); ?>">
			</div>
			<div class="skmt-wl-ep__profiles-tabs">
				<button class="skmt-wl-ep__profiles-tab is-active" data-filter="all"><?php esc_html_e( 'Tous', 'studio-kyne-mini-tools' ); ?></button>
				<button class="skmt-wl-ep__profiles-tab" data-filter="active"><?php esc_html_e( 'Actifs', 'studio-kyne-mini-tools' ); ?></button>
				<button class="skmt-wl-ep__profiles-tab" data-filter="draft"><?php esc_html_e( 'Brouillons', 'studio-kyne-mini-tools' ); ?></button>
			</div>
			<div class="skmt-wl-ep__profiles-list" id="skmt-wl-ep-profiles-list"></div>
		</aside>

		<!-- Colonne centrale : arbre du menu -->
		<div class="skmt-wl-ep__tree-col">
			<div class="skmt-wl-ep__tree-actions">
				<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-wl-add-sep">
					+ <?php esc_html_e( 'Séparateur', 'studio-kyne-mini-tools' ); ?>
				</button>
				<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-wl-add-link">
					+ <?php esc_html_e( 'Lien personnalisé', 'studio-kyne-mini-tools' ); ?>
				</button>
			</div>
			<div class="skmt-wl-ep__tree" id="skmt-wl-tree"></div>
		</div>

		<!-- Colonne droite : paramètres -->
		<div class="skmt-wl-ep__settings-col" id="skmt-wl-settings-col">

			<!-- Panel profil (par défaut) -->
			<div id="skmt-wl-profile-settings" class="skmt-wl-settings-panel">
				<h2 class="skmt-wl-settings-panel__title"><?php esc_html_e( 'Paramètres du menu', 'studio-kyne-mini-tools' ); ?></h2>

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
					<div class="skmt-wl-seg" style="width:100%">
						<button type="button" class="skmt-wl-seg__btn" data-value="draft"  id="skmt-wl-status-draft"><?php  esc_html_e( 'Brouillon', 'studio-kyne-mini-tools' ); ?></button>
						<button type="button" class="skmt-wl-seg__btn" data-value="active" id="skmt-wl-status-active"><?php esc_html_e( 'Actif', 'studio-kyne-mini-tools' ); ?></button>
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

			<!-- Panel item (affiché quand un item est sélectionné) -->
			<div id="skmt-wl-item-settings" class="skmt-wl-settings-panel" style="display:none">
				<div class="skmt-wl-settings-panel__back">
					<button type="button" class="skmt-wl-ep__back" id="skmt-wl-item-back">
						<?php echo $svg['arrow-left']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php esc_html_e( 'Paramètres du menu', 'studio-kyne-mini-tools' ); ?>
					</button>
				</div>
				<h2 class="skmt-wl-settings-panel__title" id="skmt-wl-item-settings-title">
					<?php esc_html_e( 'Modifier l\'item', 'studio-kyne-mini-tools' ); ?>
				</h2>
				<div id="skmt-wl-item-fields"></div>
			</div>

		</div>
	</div>

	<!-- Données SVG exposées au JS -->
	<script>
	window.skmtLucide = {
		gripVertical: <?php echo wp_json_encode( $svg['grip-vertical'] ); ?>,
		eye:          <?php echo wp_json_encode( $svg['eye'] ); ?>,
		eyeOff:       <?php echo wp_json_encode( $svg['eye-off'] ); ?>,
		trash:        <?php echo wp_json_encode( $svg['trash-2'] ); ?>,
		x:            <?php echo wp_json_encode( $svg['x'] ); ?>,
		chevronRight: <?php echo wp_json_encode( $svg['chevron-right'] ); ?>,
	};
	window.skmtWlCurrentProfileId = <?php echo wp_json_encode( $profile_id ); ?>;
	</script>

</div><!-- .skmt-wl-ep -->
