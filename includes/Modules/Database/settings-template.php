<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div
	class="skmt-db"
	id="skmt-db-manager"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'skmt_admin_nonce' ) ); ?>"
>

	<!-- LAYOUT DEUX COLONNES : sidebar tables + zone principale -->
	<div class="skmt-db__layout">

		<!-- SIDEBAR TABLES -->
		<div class="skmt-db__sidebar">
			<div class="skmt-db__sidebar-header">
				<div class="skmt-search skmt-search--sm">
					<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
					<input type="search" class="skmt-search__input" id="skmt-db-search-table"
						placeholder="<?php esc_attr_e( 'Rechercher une table…', 'studio-kyne-mini-tools' ); ?>">
				</div>
			</div>
			<div class="skmt-db__table-list" id="skmt-db-table-list">
				<div class="skmt-db__loading"><?php esc_html_e( 'Chargement…', 'studio-kyne-mini-tools' ); ?></div>
			</div>
		</div>

		<!-- ZONE PRINCIPALE -->
		<div class="skmt-db__main" id="skmt-db-main">

			<!-- État vide (aucune table sélectionnée) -->
			<div class="skmt-db__empty" id="skmt-db-empty">
				<p><?php esc_html_e( 'Sélectionnez une table dans la barre latérale.', 'studio-kyne-mini-tools' ); ?></p>
			</div>

			<!-- Vue table (masquée jusqu'à sélection) -->
			<div id="skmt-db-table-view" class="skmt-db__view" style="display:none">

				<!-- Header de la table -->
				<div class="skmt-db__table-header">
					<div class="skmt-db__table-title">
						<h2 class="skmt-db__table-name" id="skmt-db-table-name"></h2>
						<span class="skmt-db__table-meta" id="skmt-db-table-meta"></span>
					</div>
					<div class="skmt-db__table-actions">
						<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="skmt-db-add-row-btn">
							<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
							<?php esc_html_e( 'Ajouter une ligne', 'studio-kyne-mini-tools' ); ?>
						</button>

						<!-- Menu d'actions (regroupe les opérations dangereuses) -->
						<div class="skmt-db__menu-wrap" id="skmt-db-actions-menu">
							<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary skmt-db__menu-btn" id="skmt-db-actions-btn" aria-haspopup="true" aria-expanded="false">
								<?php esc_html_e( 'Outils', 'studio-kyne-mini-tools' ); ?>
								<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
							</button>
							<div class="skmt-db__menu" id="skmt-db-actions-dropdown" role="menu" hidden>
								<button type="button" class="skmt-db__menu-item" role="menuitem" data-action="query">
									<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
									<?php esc_html_e( 'Éditeur de requête SQL', 'studio-kyne-mini-tools' ); ?>
								</button>
								<button type="button" class="skmt-db__menu-item" role="menuitem" data-action="export">
									<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
									<?php esc_html_e( 'Exporter en .sql', 'studio-kyne-mini-tools' ); ?>
								</button>

								<div class="skmt-db__menu-sep"></div>
								<div class="skmt-db__menu-label"><?php esc_html_e( 'Zone dangereuse', 'studio-kyne-mini-tools' ); ?></div>

								<button type="button" class="skmt-db__menu-item skmt-db__menu-item--danger" role="menuitem" data-action="truncate">
									<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
									<?php esc_html_e( 'Vider la table', 'studio-kyne-mini-tools' ); ?>
								</button>
								<button type="button" class="skmt-db__menu-item skmt-db__menu-item--danger" role="menuitem" data-action="drop">
									<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
									<?php esc_html_e( 'Supprimer la table', 'studio-kyne-mini-tools' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Tabs Données / Structure / Requête SQL -->
				<div class="skmt-db__tabs">
					<button type="button" class="skmt-db__tab is-active" data-tab="data">
						<?php esc_html_e( 'Données', 'studio-kyne-mini-tools' ); ?>
					</button>
					<button type="button" class="skmt-db__tab" data-tab="structure">
						<?php esc_html_e( 'Structure', 'studio-kyne-mini-tools' ); ?>
					</button>
					<button type="button" class="skmt-db__tab" data-tab="query">
						<?php esc_html_e( 'Requête SQL', 'studio-kyne-mini-tools' ); ?>
					</button>
				</div>

				<!-- Contenu des tabs (généré en JS) -->
				<div id="skmt-db-tab-data" class="skmt-db__tab-content"></div>
				<div id="skmt-db-tab-structure" class="skmt-db__tab-content" style="display:none"></div>
				<div id="skmt-db-tab-query" class="skmt-db__tab-content" style="display:none"></div>

			</div><!-- #skmt-db-table-view -->
		</div><!-- .skmt-db__main -->
	</div><!-- .skmt-db__layout -->
</div>

<!-- MODAL SUPPRESSION DE TABLE (confirmation par saisie du nom) -->
<div class="skmt-modal-overlay" id="skmt-db-drop-modal" role="dialog" aria-modal="true" aria-labelledby="skmt-db-drop-title">
	<div class="skmt-modal">
		<div class="skmt-modal__header">
			<h3 id="skmt-db-drop-title" class="skmt-modal__title"><?php esc_html_e( 'Supprimer la table', 'studio-kyne-mini-tools' ); ?></h3>
		</div>
		<div class="skmt-modal__body">
			<p class="skmt-modal__message">
				<?php esc_html_e( 'Cette action supprime définitivement la table et toutes ses données. Elle est irréversible.', 'studio-kyne-mini-tools' ); ?>
			</p>
			<div class="skmt-form__group" style="margin-top:12px">
				<label class="skmt-form__label" for="skmt-db-drop-confirm-input">
					<?php esc_html_e( 'Tapez le nom de la table pour confirmer :', 'studio-kyne-mini-tools' ); ?>
					<code id="skmt-db-drop-name"></code>
				</label>
				<input type="text" class="skmt-input" id="skmt-db-drop-confirm-input" autocomplete="off" spellcheck="false">
			</div>
		</div>
		<div class="skmt-modal__footer">
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary skmt-modal-close"><?php esc_html_e( 'Annuler', 'studio-kyne-mini-tools' ); ?></button>
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--danger" id="skmt-db-drop-confirm-btn" disabled><?php esc_html_e( 'Supprimer définitivement', 'studio-kyne-mini-tools' ); ?></button>
		</div>
	</div>
</div>

<!-- MODAL AJOUT DE LIGNE (champs générés dynamiquement en JS) -->
<div class="skmt-modal-overlay" id="skmt-db-insert-modal" role="dialog" aria-modal="true" aria-labelledby="skmt-db-insert-title">
	<div class="skmt-modal skmt-modal--lg">
		<div class="skmt-modal__header">
			<h3 id="skmt-db-insert-title" class="skmt-modal__title"><?php esc_html_e( 'Ajouter une ligne', 'studio-kyne-mini-tools' ); ?></h3>
		</div>
		<div class="skmt-modal__body">
			<div class="skmt-db__insert-fields" id="skmt-db-insert-fields"></div>
		</div>
		<div class="skmt-modal__footer">
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary skmt-modal-close"><?php esc_html_e( 'Annuler', 'studio-kyne-mini-tools' ); ?></button>
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="skmt-db-insert-confirm-btn"><?php esc_html_e( 'Insérer la ligne', 'studio-kyne-mini-tools' ); ?></button>
		</div>
	</div>
</div>
