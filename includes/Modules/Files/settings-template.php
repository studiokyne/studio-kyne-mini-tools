<?php
/**
 * Template du module Fichiers — Gestionnaire de fichiers.
 *
 * Variables disponibles (injectées par module-settings.php) :
 * @var string          $module_id
 * @var array           $module
 * @var \StudioKyne\MiniTools\Modules\Files\Module $instance
 * @var array           $module_settings
 * @var string          $tab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div
	class="skmt-files"
	id="skmt-files-manager"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'skmt_admin_nonce' ) ); ?>"
>

	<!-- TOOLBAR ----------------------------------------------------------- -->
	<div class="skmt-files__toolbar">
		<nav class="skmt-files__breadcrumb" id="skmt-files-breadcrumb" aria-label="<?php esc_attr_e( 'Navigation', 'studio-kyne-mini-tools' ); ?>">
			<button type="button" class="skmt-files__bc-item skmt-files__bc-home" data-path="" title="<?php esc_attr_e( 'Racine WordPress', 'studio-kyne-mini-tools' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
			</button>
		</nav>

		<div class="skmt-files__toolbar-right">
			<label class="skmt-btn skmt-btn--sm skmt-btn--secondary skmt-files__upload-label" title="<?php esc_attr_e( 'Uploader des fichiers', 'studio-kyne-mini-tools' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
				<?php esc_html_e( 'Uploader', 'studio-kyne-mini-tools' ); ?>
				<input type="file" id="skmt-files-upload-input" multiple style="display:none" aria-hidden="true">
			</label>

			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-files-mkdir-btn" title="<?php esc_attr_e( 'Créer un dossier', 'studio-kyne-mini-tools' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
				<?php esc_html_e( 'Nouveau dossier', 'studio-kyne-mini-tools' ); ?>
			</button>
		</div>
	</div>

	<!-- SELECTION BAR ----------------------------------------------------- -->
	<div class="skmt-files__selection-bar" id="skmt-files-selection-bar" style="display:none">
		<span class="skmt-files__selection-count" id="skmt-files-selection-count"></span>
		<div class="skmt-files__selection-actions">
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-files-zip-btn">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
				<?php esc_html_e( 'Zip & Télécharger', 'studio-kyne-mini-tools' ); ?>
			</button>
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--danger" id="skmt-files-delete-btn">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3,6 5,6 21,6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
				<?php esc_html_e( 'Supprimer', 'studio-kyne-mini-tools' ); ?>
			</button>
		</div>
	</div>

	<!-- FILE TABLE --------------------------------------------------------- -->
	<div class="skmt-files__table-wrap">
		<table class="skmt-files__table">
			<thead>
				<tr>
					<th class="skmt-files__col-check">
						<input type="checkbox" id="skmt-files-check-all" title="<?php esc_attr_e( 'Tout sélectionner', 'studio-kyne-mini-tools' ); ?>">
					</th>
					<th class="skmt-files__col-name"><?php esc_html_e( 'Nom', 'studio-kyne-mini-tools' ); ?></th>
					<th class="skmt-files__col-size"><?php esc_html_e( 'Taille', 'studio-kyne-mini-tools' ); ?></th>
					<th class="skmt-files__col-modified"><?php esc_html_e( 'Modifié', 'studio-kyne-mini-tools' ); ?></th>
					<th class="skmt-files__col-perms"><?php esc_html_e( 'Droits', 'studio-kyne-mini-tools' ); ?></th>
					<th class="skmt-files__col-owner"><?php esc_html_e( 'Propriétaire', 'studio-kyne-mini-tools' ); ?></th>
					<th class="skmt-files__col-actions"><?php esc_html_e( 'Actions', 'studio-kyne-mini-tools' ); ?></th>
				</tr>
			</thead>
			<tbody id="skmt-files-tbody">
				<tr class="skmt-files__row-empty">
					<td colspan="7"><?php esc_html_e( 'Chargement...', 'studio-kyne-mini-tools' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- DRAG & DROP OVERLAY ----------------------------------------------- -->
	<div class="skmt-files__drop-overlay" id="skmt-files-drop-overlay" style="display:none" aria-hidden="true">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
		<p><?php esc_html_e( 'Déposez les fichiers ici', 'studio-kyne-mini-tools' ); ?></p>
	</div>

</div><!-- .skmt-files -->

<!-- ÉDITEUR (overlay plein écran) ----------------------------------------- -->
<div class="skmt-files-editor" id="skmt-files-editor" style="display:none" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Éditeur de fichier', 'studio-kyne-mini-tools' ); ?>">
	<div class="skmt-files-editor__panel">
		<div class="skmt-files-editor__header">
			<span class="skmt-files-editor__filename" id="skmt-editor-filename"></span>
			<div class="skmt-files-editor__header-actions">
				<select id="skmt-editor-lang" class="skmt-select skmt-select--sm" title="<?php esc_attr_e( 'Langage', 'studio-kyne-mini-tools' ); ?>">
					<option value="">Auto</option>
					<option value="application/x-httpd-php">PHP</option>
					<option value="text/javascript">JavaScript</option>
					<option value="text/css">CSS</option>
					<option value="text/html">HTML</option>
					<option value="text/xml">XML / SVG</option>
					<option value="application/json">JSON</option>
					<option value="text/x-sh">Shell</option>
					<option value="text/x-sql">SQL</option>
					<option value="text/x-markdown">Markdown</option>
					<option value="text/plain"><?php esc_html_e( 'Texte brut', 'studio-kyne-mini-tools' ); ?></option>
				</select>
				<span class="skmt-files-editor__header-sep" aria-hidden="true"></span>
				<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-editor-close">
					<?php esc_html_e( 'Fermer', 'studio-kyne-mini-tools' ); ?>
				</button>
				<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="skmt-editor-save" disabled>
					<?php esc_html_e( 'Enregistrer', 'studio-kyne-mini-tools' ); ?>
				</button>
			</div>
		</div>
		<div class="skmt-files-editor__body">
			<textarea id="skmt-editor-textarea" spellcheck="false"></textarea>
		</div>
	</div>
</div>

<!-- MODAL RENOMMER --------------------------------------------------------- -->
<div class="skmt-modal-overlay" id="skmt-modal-rename" role="dialog" aria-modal="true" aria-labelledby="skmt-rename-title">
	<div class="skmt-modal">
		<div class="skmt-modal__header">
			<h3 id="skmt-rename-title" class="skmt-modal__title"><?php esc_html_e( 'Renommer', 'studio-kyne-mini-tools' ); ?></h3>
		</div>
		<div class="skmt-modal__body">
			<div class="skmt-form__group">
				<label class="skmt-form__label" for="skmt-rename-input"><?php esc_html_e( 'Nouveau nom', 'studio-kyne-mini-tools' ); ?></label>
				<input type="text" class="skmt-input" id="skmt-rename-input" autocomplete="off">
			</div>
		</div>
		<div class="skmt-modal__footer">
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary skmt-modal-close"><?php esc_html_e( 'Annuler', 'studio-kyne-mini-tools' ); ?></button>
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="skmt-rename-confirm"><?php esc_html_e( 'Renommer', 'studio-kyne-mini-tools' ); ?></button>
		</div>
	</div>
</div>

<!-- MODAL DÉPLACER --------------------------------------------------------- -->
<div class="skmt-modal-overlay" id="skmt-modal-move" role="dialog" aria-modal="true" aria-labelledby="skmt-move-title">
	<div class="skmt-modal">
		<div class="skmt-modal__header">
			<h3 id="skmt-move-title" class="skmt-modal__title"><?php esc_html_e( 'Déplacer vers', 'studio-kyne-mini-tools' ); ?></h3>
		</div>
		<div class="skmt-modal__body">
			<p class="skmt-modal__message"><?php esc_html_e( 'Chemin relatif du dossier de destination (ex : wp-content/uploads).', 'studio-kyne-mini-tools' ); ?></p>
			<div class="skmt-form__group" style="margin-top:10px">
				<input type="text" class="skmt-input" id="skmt-move-input" autocomplete="off" placeholder="wp-content/uploads">
			</div>
		</div>
		<div class="skmt-modal__footer">
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary skmt-modal-close"><?php esc_html_e( 'Annuler', 'studio-kyne-mini-tools' ); ?></button>
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="skmt-move-confirm"><?php esc_html_e( 'Déplacer', 'studio-kyne-mini-tools' ); ?></button>
		</div>
	</div>
</div>

<!-- MODAL NOUVEAU DOSSIER -------------------------------------------------- -->
<div class="skmt-modal-overlay" id="skmt-modal-mkdir" role="dialog" aria-modal="true" aria-labelledby="skmt-mkdir-title">
	<div class="skmt-modal">
		<div class="skmt-modal__header">
			<h3 id="skmt-mkdir-title" class="skmt-modal__title"><?php esc_html_e( 'Nouveau dossier', 'studio-kyne-mini-tools' ); ?></h3>
		</div>
		<div class="skmt-modal__body">
			<div class="skmt-form__group">
				<label class="skmt-form__label" for="skmt-mkdir-input"><?php esc_html_e( 'Nom du dossier', 'studio-kyne-mini-tools' ); ?></label>
				<input type="text" class="skmt-input" id="skmt-mkdir-input" autocomplete="off">
			</div>
		</div>
		<div class="skmt-modal__footer">
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary skmt-modal-close"><?php esc_html_e( 'Annuler', 'studio-kyne-mini-tools' ); ?></button>
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="skmt-mkdir-confirm"><?php esc_html_e( 'Créer', 'studio-kyne-mini-tools' ); ?></button>
		</div>
	</div>
</div>
