<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Module_Files implements SKMT_Module_Interface {
	protected $plugin;
	protected $option_name = 'skmt_files_settings';
	protected $defaults = array(
		'enabled'       => 1,
		'allow_upload'  => 1,
		'allow_edit'    => 1,
		'allow_delete'  => 1,
		'max_upload_mb' => 20,
	);

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function get_id() { return 'files'; }
	public function get_name() { return __( 'Fichiers', 'studio-kyne-mini-tools' ); }
	public function get_description() { return __( 'Explorateur de fichiers SKMT avec édition et opérations sécurisées.', 'studio-kyne-mini-tools' ); }
	public function get_icon() { return 'folder'; }
	public function is_default_active() { return false; }
	public function is_configurable() { return true; }
	public function activate() { $this->maybe_seed_defaults(); }
	public function deactivate() {}

	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_skmt_files_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_skmt_files_save_file', array( $this, 'handle_save_file' ) );
		add_action( 'admin_post_skmt_files_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_skmt_files_download', array( $this, 'handle_download' ) );
		add_action( 'admin_post_skmt_files_bulk_action', array( $this, 'handle_bulk_action' ) );
	}

	public function register_admin_pages( $parent_slug ) {
		add_submenu_page(
			$parent_slug,
			__( 'Fichiers', 'studio-kyne-mini-tools' ),
			__( 'Fichiers', 'studio-kyne-mini-tools' ),
			SKMT_Capabilities::admin_capability(),
			'skmt-files',
			array( $this, 'render_admin_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'skmt_files_group',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->defaults,
			)
		);
	}

	public function get_settings() {
		$stored = get_option( $this->option_name, array() );
		$data   = wp_parse_args( is_array( $stored ) ? $stored : array(), $this->defaults );

		$data['enabled']       = 1;
		$data['allow_upload']  = empty( $data['allow_upload'] ) ? 0 : 1;
		$data['allow_edit']    = empty( $data['allow_edit'] ) ? 0 : 1;
		$data['allow_delete']  = empty( $data['allow_delete'] ) ? 0 : 1;
		$data['max_upload_mb'] = max( 1, min( 512, absint( $data['max_upload_mb'] ) ) );

		return $data;
	}

	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'enabled'       => 1,
			'allow_upload'  => empty( $input['allow_upload'] ) ? 0 : 1,
			'allow_edit'    => empty( $input['allow_edit'] ) ? 0 : 1,
			'allow_delete'  => empty( $input['allow_delete'] ) ? 0 : 1,
			'max_upload_mb' => max( 1, min( 512, absint( $input['max_upload_mb'] ?? $this->defaults['max_upload_mb'] ) ) ),
		);
	}

	public function render_admin_page() {
		if ( ! SKMT_Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Accès refusé.', 'studio-kyne-mini-tools' ) );
		}

		$settings    = $this->get_settings();
		$notice      = isset( $_GET['skmt_files_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['skmt_files_notice'] ) ) : '';
		$current_rel = $this->get_requested_path( 'dir' );
		$current_abs = $this->resolve_existing_path( $current_rel, true );
		if ( false === $current_abs ) {
			$current_abs = $this->get_base_dir();
			$current_rel = '';
		}
		$current_rel = $this->abs_to_rel( $current_abs );
		$items       = $this->list_directory_items( $current_abs, $current_rel );
		$parent_rel  = $this->get_parent_rel( $current_rel );

		$edit_rel         = $this->get_requested_path( 'edit' );
		$edit_abs         = false;
		$edit_content     = '';
		$edit_lang        = 'text';
		$edit_file_label  = '';
		if ( '' !== $edit_rel ) {
			$maybe_edit = $this->resolve_existing_path( $edit_rel, false );
			if ( false !== $maybe_edit && is_file( $maybe_edit ) && $this->is_editable_file( wp_basename( $maybe_edit ) ) && ! empty( $settings['allow_edit'] ) ) {
				$edit_abs        = $maybe_edit;
				$edit_file_label = wp_basename( $maybe_edit );
				$edit_lang       = $this->guess_language( $edit_file_label );
				$raw             = file_get_contents( $edit_abs ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( false !== $raw ) {
					$edit_content = (string) $raw;
				}
			}
		}

		$this->render_notice( $notice );
		?>
		<div class="wrap skmt-wrap">
			<div class="skmt-shell skmt-files-shell">
				<header class="skmt-page-head skmt-page-head--files">
					<div>
						<h1><?php echo esc_html__( 'Explorateur de fichiers', 'studio-kyne-mini-tools' ); ?></h1>
						<p><?php echo esc_html__( 'Vue rapide et lisible de votre wp-content, avec actions sécurisées et édition directe.', 'studio-kyne-mini-tools' ); ?></p>
					</div>
					<div class="skmt-files-head-badges">
						<span class="skmt-badge skmt-badge--success"><?php echo esc_html__( 'Actif', 'studio-kyne-mini-tools' ); ?></span>
						<span class="skmt-badge"><?php echo esc_html( sprintf( __( '%d éléments', 'studio-kyne-mini-tools' ), count( $items ) ) ); ?></span>
					</div>
				</header>

				<div class="skmt-card skmt-files-settings-card">
					<div class="skmt-card-head">
						<h2 class="skmt-title-inline"><i class="skmt-lucide" data-lucide="sliders-horizontal"></i><?php echo esc_html__( 'Réglages de l’explorateur', 'studio-kyne-mini-tools' ); ?></h2>
						<p class="description"><?php echo esc_html__( 'Ces options sont enregistrées automatiquement dès modification.', 'studio-kyne-mini-tools' ); ?></p>
					</div>

					<form class="skmt-form-grid" action="options.php" method="post" data-skmt-autosave="1">
						<?php settings_fields( 'skmt_files_group' ); ?>
						<div class="skmt-field">
							<label class="skmt-toggle">
								<input type="checkbox" name="skmt_files_settings[allow_upload]" value="1" <?php checked( ! empty( $settings['allow_upload'] ) ); ?> />
								<span></span>
								<strong><?php echo esc_html__( 'Autoriser les uploads', 'studio-kyne-mini-tools' ); ?></strong>
							</label>
						</div>
						<div class="skmt-field">
							<label class="skmt-toggle">
								<input type="checkbox" name="skmt_files_settings[allow_edit]" value="1" <?php checked( ! empty( $settings['allow_edit'] ) ); ?> />
								<span></span>
								<strong><?php echo esc_html__( 'Autoriser l’édition de fichiers texte/code', 'studio-kyne-mini-tools' ); ?></strong>
							</label>
						</div>
						<div class="skmt-field">
							<label class="skmt-toggle">
								<input type="checkbox" name="skmt_files_settings[allow_delete]" value="1" <?php checked( ! empty( $settings['allow_delete'] ) ); ?> />
								<span></span>
								<strong><?php echo esc_html__( 'Autoriser la suppression', 'studio-kyne-mini-tools' ); ?></strong>
							</label>
						</div>
						<div class="skmt-field">
							<label for="skmt-files-max-upload"><strong><?php echo esc_html__( 'Taille max upload (MB)', 'studio-kyne-mini-tools' ); ?></strong></label>
							<input id="skmt-files-max-upload" type="number" min="1" max="512" name="skmt_files_settings[max_upload_mb]" value="<?php echo esc_attr( (string) $settings['max_upload_mb'] ); ?>" />
						</div>
						<div class="skmt-actions">
							<?php submit_button( __( 'Enregistrer maintenant', 'studio-kyne-mini-tools' ), 'secondary', 'submit', false ); ?>
						</div>
					</form>
				</div>

				<div class="skmt-card skmt-files-explorer-card">
					<div class="skmt-files-toolbar">
						<div class="skmt-files-toolbar__path">
							<div class="skmt-files-breadcrumb">
								<?php $this->render_breadcrumbs( $current_rel ); ?>
							</div>
							<div class="skmt-files-toolbar__hint"><?php echo esc_html__( 'Racine sécurisée : wp-content', 'studio-kyne-mini-tools' ); ?></div>
						</div>
						<div class="skmt-actions skmt-files-toolbar__actions">
							<?php if ( '' !== $current_rel ) : ?>
								<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'skmt-files', 'dir' => $parent_rel ), admin_url( 'admin.php' ) ) ); ?>">
									<i class="skmt-lucide" data-lucide="arrow-up"></i>
									<?php echo esc_html__( 'Dossier parent', 'studio-kyne-mini-tools' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( ! empty( $settings['allow_upload'] ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="skmt-files-inline-upload">
									<input type="hidden" name="action" value="skmt_files_upload" />
									<input type="hidden" name="dir" value="<?php echo esc_attr( $current_rel ); ?>" />
									<?php wp_nonce_field( 'skmt_files_upload' ); ?>
									<label class="button button-primary" for="skmt-files-upload-inline">
										<i class="skmt-lucide" data-lucide="upload"></i>
										<?php echo esc_html__( 'Uploader', 'studio-kyne-mini-tools' ); ?>
									</label>
									<input id="skmt-files-upload-inline" type="file" name="upload_file" required class="skmt-hidden-file-input" />
									<button type="submit" class="button"><?php echo esc_html__( 'Envoyer', 'studio-kyne-mini-tools' ); ?></button>
								</form>
							<?php endif; ?>
						</div>
					</div>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="skmt-files-explorer-form">
						<input type="hidden" name="action" value="skmt_files_bulk_action" />
						<input type="hidden" name="dir" value="<?php echo esc_attr( $current_rel ); ?>" />
						<?php wp_nonce_field( 'skmt_files_bulk_action' ); ?>
						<div class="skmt-files-bulk-row">
							<select name="bulk_action" required>
								<option value=""><?php echo esc_html__( 'Action de masse', 'studio-kyne-mini-tools' ); ?></option>
								<option value="download_zip"><?php echo esc_html__( 'Télécharger la sélection (ZIP)', 'studio-kyne-mini-tools' ); ?></option>
								<?php if ( ! empty( $settings['allow_delete'] ) ) : ?>
									<option value="delete"><?php echo esc_html__( 'Supprimer la sélection', 'studio-kyne-mini-tools' ); ?></option>
								<?php endif; ?>
							</select>
							<button type="submit" class="button"><?php echo esc_html__( 'Appliquer', 'studio-kyne-mini-tools' ); ?></button>
						</div>

						<div class="skmt-files-table-viewport">
							<table class="widefat fixed striped skmt-table skmt-files-table">
								<thead>
									<tr>
										<th><input type="checkbox" id="skmt-files-select-all" /></th>
										<th><?php echo esc_html__( 'Nom', 'studio-kyne-mini-tools' ); ?></th>
										<th><?php echo esc_html__( 'Type', 'studio-kyne-mini-tools' ); ?></th>
										<th><?php echo esc_html__( 'Taille', 'studio-kyne-mini-tools' ); ?></th>
										<th><?php echo esc_html__( 'Dernière modification', 'studio-kyne-mini-tools' ); ?></th>
										<th><?php echo esc_html__( 'Permissions', 'studio-kyne-mini-tools' ); ?></th>
										<th><?php echo esc_html__( 'Propriétaire', 'studio-kyne-mini-tools' ); ?></th>
										<th><?php echo esc_html__( 'Actions rapides', 'studio-kyne-mini-tools' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( empty( $items ) ) : ?>
										<tr><td colspan="8"><?php echo esc_html__( 'Ce dossier est vide.', 'studio-kyne-mini-tools' ); ?></td></tr>
									<?php else : ?>
										<?php foreach ( $items as $item ) : ?>
											<?php
											$download_url = wp_nonce_url(
												add_query_arg(
													array(
														'action' => 'skmt_files_download',
														'path'   => $item['rel'],
													),
													admin_url( 'admin-post.php' )
												),
												'skmt_files_download_' . md5( $item['rel'] )
											);
											$type = $this->get_file_type( $item['name'], $item['is_dir'] );
											$icon = $this->get_file_icon( $type );
											$edit_link = add_query_arg( array( 'page' => 'skmt-files', 'dir' => $current_rel, 'edit' => $item['rel'] ), admin_url( 'admin.php' ) );
											?>
											<tr class="<?php echo esc_attr( $item['is_dir'] ? 'is-directory' : 'is-file' ); ?>">
												<td><input type="checkbox" name="items[]" value="<?php echo esc_attr( $item['rel'] ); ?>" /></td>
												<td class="skmt-files-cell-name">
													<span class="skmt-files-kind skmt-files-kind--<?php echo esc_attr( $type ); ?>">
														<i class="skmt-lucide" data-lucide="<?php echo esc_attr( $icon ); ?>"></i>
													</span>
													<?php if ( $item['is_dir'] ) : ?>
														<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'skmt-files', 'dir' => $item['rel'] ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $item['name'] ); ?>/</a>
													<?php elseif ( $item['editable'] && ! empty( $settings['allow_edit'] ) ) : ?>
														<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
													<?php else : ?>
														<?php echo esc_html( $item['name'] ); ?>
													<?php endif; ?>
												</td>
												<td><span class="skmt-files-pill skmt-files-pill--<?php echo esc_attr( $type ); ?>"><?php echo esc_html( ucfirst( $type ) ); ?></span></td>
												<td><?php echo esc_html( $this->format_bytes( $item['size'] ) ); ?></td>
												<td><?php echo esc_html( $this->format_modified( $item['modified'] ) ); ?></td>
												<td><?php echo esc_html( $item['permissions'] ); ?></td>
												<td><?php echo esc_html( $item['owner'] ); ?></td>
												<td>
													<div class="skmt-actions skmt-files-quick-actions">
														<a class="button" href="<?php echo esc_url( $download_url ); ?>" title="<?php echo esc_attr( $item['is_dir'] ? __( 'Télécharger en ZIP', 'studio-kyne-mini-tools' ) : __( 'Télécharger', 'studio-kyne-mini-tools' ) ); ?>">
															<i class="skmt-lucide" data-lucide="download"></i>
														</a>
														<?php if ( ! $item['is_dir'] && $item['editable'] && ! empty( $settings['allow_edit'] ) ) : ?>
															<a class="button" href="<?php echo esc_url( $edit_link ); ?>" title="<?php echo esc_attr__( 'Éditer', 'studio-kyne-mini-tools' ); ?>">
																<i class="skmt-lucide" data-lucide="file-pen-line"></i>
															</a>
														<?php endif; ?>
														<?php if ( ! empty( $settings['allow_delete'] ) ) : ?>
															<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Confirmer la suppression ?', 'studio-kyne-mini-tools' ) ); ?>');">
																<input type="hidden" name="action" value="skmt_files_delete" />
																<input type="hidden" name="path" value="<?php echo esc_attr( $item['rel'] ); ?>" />
																<input type="hidden" name="dir" value="<?php echo esc_attr( $current_rel ); ?>" />
																<?php wp_nonce_field( 'skmt_files_delete' ); ?>
																<button type="submit" class="button skmt-button-danger" title="<?php echo esc_attr__( 'Supprimer', 'studio-kyne-mini-tools' ); ?>">
																	<i class="skmt-lucide" data-lucide="trash-2"></i>
																</button>
															</form>
														<?php endif; ?>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</form>
				</div>
			</div>
		</div>

		<?php if ( false !== $edit_abs ) : ?>
			<div class="skmt-files-modal" data-skmt-files-modal data-unsaved="0">
				<div class="skmt-files-modal__backdrop" data-skmt-files-modal-close></div>
				<div class="skmt-files-modal__panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__( 'Éditeur de fichier', 'studio-kyne-mini-tools' ); ?>">
					<div class="skmt-files-modal__header">
						<div>
							<h2 class="skmt-title-inline"><i class="skmt-lucide" data-lucide="file-code-2"></i><?php echo esc_html__( 'Éditeur', 'studio-kyne-mini-tools' ); ?></h2>
							<p><?php echo esc_html( $edit_rel ); ?></p>
						</div>
						<button type="button" class="button" data-skmt-files-modal-close><?php echo esc_html__( 'Quitter', 'studio-kyne-mini-tools' ); ?></button>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-skmt-files-editor-form>
						<input type="hidden" name="action" value="skmt_files_save_file" />
						<input type="hidden" name="path" value="<?php echo esc_attr( $edit_rel ); ?>" />
						<input type="hidden" name="dir" value="<?php echo esc_attr( $current_rel ); ?>" />
						<?php wp_nonce_field( 'skmt_files_save_file' ); ?>
						<div class="skmt-files-modal__meta">
							<span class="skmt-files-pill skmt-files-pill--text"><?php echo esc_html( strtoupper( $edit_lang ) ); ?></span>
							<span><?php echo esc_html( $edit_file_label ); ?></span>
						</div>
						<div class="skmt-files-editor-wrap" data-lang="<?php echo esc_attr( $edit_lang ); ?>">
							<pre class="skmt-files-editor-highlight" aria-hidden="true"></pre>
							<textarea name="file_content" class="skmt-files-editor" data-skmt-files-editor spellcheck="false"><?php echo esc_textarea( $edit_content ); ?></textarea>
						</div>
						<div class="skmt-files-modal__footer">
							<button type="submit" class="button button-primary"><?php echo esc_html__( 'Enregistrer', 'studio-kyne-mini-tools' ); ?></button>
							<button type="button" class="button" data-skmt-files-modal-close><?php echo esc_html__( 'Quitter', 'studio-kyne-mini-tools' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		<?php elseif ( '' !== $edit_rel ) : ?>
			<div class="wrap skmt-wrap">
				<div class="skmt-shell">
					<div class="skmt-card">
						<p class="description"><?php echo esc_html__( 'Fichier non éditable ou inaccessible.', 'studio-kyne-mini-tools' ); ?></p>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<script>
			(function () {
				const selectAll = document.getElementById('skmt-files-select-all');
				if (selectAll) {
					const itemInputs = Array.from(document.querySelectorAll('input[name="items[]"]'));
					selectAll.addEventListener('change', function () {
						itemInputs.forEach((input) => {
							input.checked = selectAll.checked;
						});
					});
				}

				const uploadInput = document.getElementById('skmt-files-upload-inline');
				if (uploadInput) {
					uploadInput.addEventListener('change', function () {
						if (uploadInput.files && uploadInput.files[0]) {
							const label = uploadInput.closest('form')?.querySelector('label[for="skmt-files-upload-inline"]');
							if (label) {
								label.dataset.filename = uploadInput.files[0].name;
							}
						}
					});
				}
			})();
		</script>
		<?php
	}

	public function handle_upload() {
		$this->assert_capability();
		check_admin_referer( 'skmt_files_upload' );

		$settings = $this->get_settings();
		$dir_rel  = isset( $_POST['dir'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['dir'] ) ) : '';
		$dir_abs  = $this->resolve_existing_path( $dir_rel, true );

		if ( false === $dir_abs ) {
			$this->redirect_admin( 'invalid-path', '' );
		}

		if ( empty( $settings['allow_upload'] ) ) {
			$this->redirect_admin( 'upload-disabled', $dir_rel );
		}

		if ( empty( $_FILES['upload_file']['name'] ) ) {
			$this->redirect_admin( 'upload-missing', $dir_rel );
		}

		$file = $_FILES['upload_file'];
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->redirect_admin( 'upload-failed', $dir_rel );
		}

		$max_bytes = absint( $settings['max_upload_mb'] ) * MB_IN_BYTES;
		$size      = isset( $file['size'] ) ? absint( $file['size'] ) : 0;
		if ( $size > $max_bytes ) {
			$this->redirect_admin( 'upload-too-large', $dir_rel );
		}

		$filename = sanitize_file_name( wp_unslash( (string) $file['name'] ) );
		if ( '' === $filename ) {
			$this->redirect_admin( 'upload-invalid-name', $dir_rel );
		}

		$filename   = wp_unique_filename( $dir_abs, $filename );
		$target_abs = wp_normalize_path( $dir_abs . '/' . $filename );
		if ( ! $this->is_within_base( $target_abs, $this->get_base_dir() ) ) {
			$this->redirect_admin( 'upload-failed', $dir_rel );
		}

		$moved = move_uploaded_file( $file['tmp_name'], $target_abs ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_move_uploaded_file
		if ( ! $moved ) {
			$this->redirect_admin( 'upload-failed', $dir_rel );
		}

		$this->redirect_admin( 'upload-success', $dir_rel );
	}

	public function handle_save_file() {
		$this->assert_capability();
		check_admin_referer( 'skmt_files_save_file' );

		$settings = $this->get_settings();
		if ( empty( $settings['allow_edit'] ) ) {
			$this->redirect_admin( 'edit-disabled', '' );
		}

		$path_rel = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['path'] ) ) : '';
		$dir_rel  = isset( $_POST['dir'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['dir'] ) ) : '';
		$path_abs = $this->resolve_existing_path( $path_rel, false );

		if ( false === $path_abs || ! is_file( $path_abs ) || ! $this->is_editable_file( wp_basename( $path_abs ) ) ) {
			$this->redirect_admin( 'save-invalid', $dir_rel );
		}

		$content = isset( $_POST['file_content'] ) ? (string) wp_unslash( $_POST['file_content'] ) : '';
		$result  = file_put_contents( $path_abs, $content, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $result ) {
			$this->redirect_admin( 'save-failed', $dir_rel, $path_rel );
		}

		$this->redirect_admin( 'save-success', $dir_rel, $path_rel );
	}

	public function handle_delete() {
		$this->assert_capability();
		check_admin_referer( 'skmt_files_delete' );

		$settings = $this->get_settings();
		if ( empty( $settings['allow_delete'] ) ) {
			$this->redirect_admin( 'delete-disabled', '' );
		}

		$path_rel = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['path'] ) ) : '';
		$dir_rel  = isset( $_POST['dir'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['dir'] ) ) : '';
		$path_abs = $this->resolve_existing_path( $path_rel, false );
		$base     = $this->get_base_dir();

		if ( false === $path_abs || $path_abs === $base ) {
			$this->redirect_admin( 'delete-invalid', $dir_rel );
		}

		$deleted = $this->delete_path( $path_abs );
		$this->redirect_admin( $deleted ? 'delete-success' : 'delete-failed', $dir_rel );
	}

	public function handle_download() {
		$this->assert_capability();

		$path_rel = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['path'] ) ) : '';
		check_admin_referer( 'skmt_files_download_' . md5( $path_rel ) );

		$path_abs = $this->resolve_existing_path( $path_rel, false );
		if ( false === $path_abs ) {
			wp_die( esc_html__( 'Chemin invalide.', 'studio-kyne-mini-tools' ) );
		}

		if ( is_file( $path_abs ) ) {
			$this->stream_file_download( $path_abs );
		}

		if ( is_dir( $path_abs ) ) {
			$zip_name = wp_basename( $path_abs ) . '-' . gmdate( 'Ymd-His' ) . '.zip';
			$this->stream_paths_zip(
				array(
					array(
						'abs' => $path_abs,
						'rel' => wp_basename( $path_abs ),
					),
				),
				$zip_name
			);
		}

		wp_die( esc_html__( 'Élément non téléchargeable.', 'studio-kyne-mini-tools' ) );
	}

	public function handle_bulk_action() {
		$this->assert_capability();
		check_admin_referer( 'skmt_files_bulk_action' );

		$settings = $this->get_settings();
		$dir_rel  = isset( $_POST['dir'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['dir'] ) ) : '';
		$action   = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['bulk_action'] ) ) : '';
		$raw      = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? $_POST['items'] : array();

		$targets = array();
		foreach ( $raw as $item_rel ) {
			$rel = sanitize_text_field( wp_unslash( (string) $item_rel ) );
			$abs = $this->resolve_existing_path( $rel, false );
			if ( false === $abs ) {
				continue;
			}
			if ( $abs === $this->get_base_dir() ) {
				continue;
			}

			$targets[] = array(
				'abs' => $abs,
				'rel' => $this->abs_to_rel( $abs ),
			);
		}

		if ( empty( $targets ) ) {
			$this->redirect_admin( 'bulk-empty', $dir_rel );
		}

		if ( 'download_zip' === $action ) {
			$this->stream_paths_zip( $targets, 'skmt-selection-' . gmdate( 'Ymd-His' ) . '.zip' );
		}

		if ( 'delete' === $action ) {
			if ( empty( $settings['allow_delete'] ) ) {
				$this->redirect_admin( 'delete-disabled', $dir_rel );
			}

			$success = 0;
			foreach ( $targets as $target ) {
				if ( $this->delete_path( $target['abs'] ) ) {
					++$success;
				}
			}

			$this->redirect_admin( $success > 0 ? 'bulk-delete-success' : 'bulk-delete-failed', $dir_rel );
		}

		$this->redirect_admin( 'bulk-invalid', $dir_rel );
	}

	protected function maybe_seed_defaults() {
		if ( false === get_option( $this->option_name, false ) ) {
			add_option( $this->option_name, $this->defaults );
		}
	}

	protected function assert_capability() {
		if ( ! SKMT_Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Accès refusé.', 'studio-kyne-mini-tools' ) );
		}
	}

	protected function get_base_dir() {
		$base = (string) apply_filters( 'skmt_files_base_directory', WP_CONTENT_DIR );
		$base = wp_normalize_path( $base );
		$real = realpath( $base );
		if ( false !== $real ) {
			$base = wp_normalize_path( $real );
		}

		return rtrim( $base, '/' );
	}

	protected function get_requested_path( $query_key ) {
		if ( ! isset( $_GET[ $query_key ] ) ) {
			return '';
		}

		return $this->normalize_rel_path( sanitize_text_field( wp_unslash( (string) $_GET[ $query_key ] ) ) );
	}

	protected function normalize_rel_path( $path ) {
		$path  = rawurldecode( (string) $path );
		$path  = str_replace( '\\', '/', $path );
		$path  = preg_replace( '/\0+/', '', $path );
		$parts = array();

		foreach ( explode( '/', $path ) as $segment ) {
			$segment = trim( (string) $segment );
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				continue;
			}
			$parts[] = $segment;
		}

		return implode( '/', $parts );
	}

	protected function resolve_existing_path( $rel_path, $must_be_dir = false ) {
		$base       = $this->get_base_dir();
		$normalized = $this->normalize_rel_path( $rel_path );
		$candidate  = '' === $normalized ? $base : $base . '/' . $normalized;
		if ( file_exists( $candidate ) && is_link( $candidate ) ) {
			return false;
		}
		$real = realpath( $candidate );

		if ( false === $real ) {
			return false;
		}

		$real = wp_normalize_path( $real );
		if ( ! $this->is_within_base( $real, $base ) ) {
			return false;
		}

		if ( $must_be_dir && ! is_dir( $real ) ) {
			return false;
		}

		return $real;
	}

	protected function is_within_base( $path, $base ) {
		$path = rtrim( wp_normalize_path( (string) $path ), '/' );
		$base = rtrim( wp_normalize_path( (string) $base ), '/' );
		if ( $path === $base ) {
			return true;
		}

		return 0 === strpos( $path, $base . '/' );
	}

	protected function abs_to_rel( $abs_path ) {
		$base = $this->get_base_dir();
		$abs  = wp_normalize_path( (string) $abs_path );
		if ( $abs === $base ) {
			return '';
		}

		$rel = ltrim( (string) substr( $abs, strlen( $base ) ), '/' );
		return $this->normalize_rel_path( $rel );
	}

	protected function get_parent_rel( $rel_path ) {
		$rel_path = $this->normalize_rel_path( $rel_path );
		if ( '' === $rel_path ) {
			return '';
		}

		$parts = explode( '/', $rel_path );
		array_pop( $parts );
		return implode( '/', $parts );
	}

	protected function list_directory_items( $dir_abs, $dir_rel ) {
		$entries = scandir( $dir_abs ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_scandir
		if ( ! is_array( $entries ) ) {
			return array();
		}

		$items = array();
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$entry_abs = wp_normalize_path( $dir_abs . '/' . $entry );
			if ( is_link( $entry_abs ) ) {
				continue;
			}
			$entry_real = realpath( $entry_abs );
			if ( false === $entry_real ) {
				continue;
			}

			$entry_real = wp_normalize_path( $entry_real );
			if ( ! $this->is_within_base( $entry_real, $this->get_base_dir() ) ) {
				continue;
			}

			$is_dir = is_dir( $entry_real );
			$rel    = '' === $dir_rel ? $entry : $dir_rel . '/' . $entry;

			$items[] = array(
				'name'        => $entry,
				'rel'         => $this->normalize_rel_path( $rel ),
				'is_dir'      => $is_dir,
				'size'        => $is_dir ? null : absint( filesize( $entry_real ) ),
				'modified'    => absint( filemtime( $entry_real ) ),
				'permissions' => $this->format_permissions( $entry_real ),
				'owner'       => $this->format_owner( $entry_real ),
				'editable'    => ! $is_dir && $this->is_editable_file( $entry ),
			);
		}

		usort(
			$items,
			static function ( $a, $b ) {
				if ( $a['is_dir'] !== $b['is_dir'] ) {
					return $a['is_dir'] ? -1 : 1;
				}

				return strnatcasecmp( (string) $a['name'], (string) $b['name'] );
			}
		);

		return $items;
	}

	protected function format_permissions( $path ) {
		$perms = fileperms( $path );
		if ( false === $perms ) {
			return '-';
		}

		return substr( sprintf( '%o', (int) $perms ), -4 );
	}

	protected function format_owner( $path ) {
		$owner = fileowner( $path );
		if ( false === $owner ) {
			return '-';
		}

		if ( function_exists( 'posix_getpwuid' ) ) {
			$payload = @posix_getpwuid( $owner ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $payload ) && ! empty( $payload['name'] ) ) {
				return sanitize_text_field( (string) $payload['name'] );
			}
		}

		return (string) absint( $owner );
	}

	protected function format_bytes( $bytes ) {
		if ( null === $bytes ) {
			return '-';
		}

		return size_format( absint( $bytes ), 2 );
	}

	protected function format_modified( $timestamp ) {
		$timestamp = absint( $timestamp );
		if ( $timestamp < 1 ) {
			return '-';
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	protected function is_editable_file( $filename ) {
		$ext = strtolower( (string) pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		if ( '' === $ext ) {
			return false;
		}

		$allowed = array(
			'php',
			'js',
			'css',
			'txt',
			'md',
			'json',
			'xml',
			'yml',
			'yaml',
			'ini',
			'conf',
			'config',
			'htaccess',
			'env',
			'log',
			'csv',
			'html',
			'htm',
			'svg',
		);

		return in_array( $ext, $allowed, true );
	}

	protected function get_file_type( $filename, $is_dir = false ) {
		if ( $is_dir ) {
			return 'folder';
		}

		$ext = strtolower( (string) pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'txt', 'md', 'json', 'xml', 'yml', 'yaml', 'ini', 'log', 'csv', 'php', 'js', 'css', 'html', 'htm', 'svg' ), true ) ) {
			return 'text';
		}
		if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico' ), true ) ) {
			return 'image';
		}
		if ( in_array( $ext, array( 'mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v' ), true ) ) {
			return 'video';
		}
		if ( in_array( $ext, array( 'zip', 'rar', '7z', 'gz', 'tar' ), true ) ) {
			return 'zip';
		}

		return 'file';
	}

	protected function get_file_icon( $type ) {
		$map = array(
			'folder' => 'folder',
			'text'   => 'file-code-2',
			'image'  => 'image',
			'video'  => 'film',
			'zip'    => 'archive',
			'file'   => 'file',
		);

		return $map[ $type ] ?? 'file';
	}

	protected function guess_language( $filename ) {
		$ext = strtolower( (string) pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		$map = array(
			'php'      => 'php',
			'js'       => 'javascript',
			'css'      => 'css',
			'json'     => 'json',
			'xml'      => 'xml',
			'html'     => 'html',
			'htm'      => 'html',
			'yml'      => 'yaml',
			'yaml'     => 'yaml',
			'md'       => 'markdown',
			'txt'      => 'text',
			'log'      => 'text',
			'conf'     => 'text',
			'config'   => 'text',
			'ini'      => 'text',
			'csv'      => 'text',
			'htaccess' => 'text',
			'env'      => 'text',
			'svg'      => 'xml',
		);

		return $map[ $ext ] ?? 'text';
	}

	protected function delete_path( $path ) {
		if ( is_file( $path ) || is_link( $path ) ) {
			return unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}

		if ( ! is_dir( $path ) ) {
			return false;
		}

		$items = scandir( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_scandir
		if ( ! is_array( $items ) ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$child = wp_normalize_path( $path . '/' . $item );
			if ( is_link( $child ) ) {
				if ( ! unlink( $child ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					return false;
				}
				continue;
			}
			$real = realpath( $child );
			if ( false === $real ) {
				continue;
			}

			$real = wp_normalize_path( $real );
			if ( ! $this->is_within_base( $real, $this->get_base_dir() ) ) {
				continue;
			}

			if ( ! $this->delete_path( $real ) ) {
				return false;
			}
		}

		return rmdir( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	protected function stream_file_download( $path_abs ) {
		if ( ! is_readable( $path_abs ) ) {
			wp_die( esc_html__( 'Fichier non lisible.', 'studio-kyne-mini-tools' ) );
		}

		$safe_name = sanitize_file_name( wp_basename( $path_abs ) );
		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $safe_name . '"' );
		header( 'Content-Length: ' . (string) filesize( $path_abs ) );
		readfile( $path_abs ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	protected function stream_paths_zip( $paths, $filename ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'ZipArchive non disponible sur ce serveur.', 'studio-kyne-mini-tools' ) );
		}

		$tmp_zip = wp_tempnam( 'skmt-files-' . gmdate( 'YmdHis' ) . '.zip' );
		if ( empty( $tmp_zip ) ) {
			wp_die( esc_html__( 'Impossible de préparer l’archive ZIP.', 'studio-kyne-mini-tools' ) );
		}

		$zip    = new ZipArchive();
		$opened = $zip->open( $tmp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		if ( true !== $opened ) {
			wp_die( esc_html__( 'Impossible de créer l’archive ZIP.', 'studio-kyne-mini-tools' ) );
		}

		foreach ( $paths as $path ) {
			$this->add_path_to_zip( $zip, $path['abs'], $path['rel'] );
		}

		$zip->close();

		$safe_name = sanitize_file_name( $filename );
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $safe_name . '"' );
		header( 'Content-Length: ' . (string) filesize( $tmp_zip ) );
		readfile( $tmp_zip ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		unlink( $tmp_zip ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		exit;
	}

	protected function add_path_to_zip( $zip, $abs_path, $zip_path ) {
		$zip_path = trim( str_replace( '\\', '/', (string) $zip_path ), '/' );
		if ( '' === $zip_path ) {
			$zip_path = wp_basename( $abs_path );
		}

		if ( is_file( $abs_path ) ) {
			$zip->addFile( $abs_path, $zip_path );
			return;
		}

		if ( ! is_dir( $abs_path ) ) {
			return;
		}

		$zip->addEmptyDir( $zip_path );

		$entries = scandir( $abs_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_scandir
		if ( ! is_array( $entries ) ) {
			return;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$child_abs = wp_normalize_path( $abs_path . '/' . $entry );
			if ( is_link( $child_abs ) ) {
				continue;
			}
			$child_real = realpath( $child_abs );
			if ( false === $child_real ) {
				continue;
			}

			$child_real = wp_normalize_path( $child_real );
			if ( ! $this->is_within_base( $child_real, $this->get_base_dir() ) ) {
				continue;
			}

			$this->add_path_to_zip( $zip, $child_real, $zip_path . '/' . $entry );
		}
	}

	protected function render_breadcrumbs( $current_rel ) {
		$segments = '' === $current_rel ? array() : explode( '/', $current_rel );
		$acc      = '';

		echo '<a href="' . esc_url( admin_url( 'admin.php?page=skmt-files' ) ) . '">' . esc_html__( 'wp-content', 'studio-kyne-mini-tools' ) . '</a>';
		foreach ( $segments as $segment ) {
			$acc = '' === $acc ? $segment : $acc . '/' . $segment;
			echo '<span>/</span>';
			echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'skmt-files', 'dir' => $acc ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $segment ) . '</a>';
		}
	}

	protected function render_notice( $notice ) {
		if ( '' === (string) $notice ) {
			return;
		}

		$map = array(
			'upload-success'      => array( 'type' => 'success', 'message' => __( 'Upload terminé.', 'studio-kyne-mini-tools' ) ),
			'upload-missing'      => array( 'type' => 'warning', 'message' => __( 'Aucun fichier sélectionné.', 'studio-kyne-mini-tools' ) ),
			'upload-too-large'    => array( 'type' => 'error', 'message' => __( 'Fichier trop volumineux.', 'studio-kyne-mini-tools' ) ),
			'upload-invalid-name' => array( 'type' => 'error', 'message' => __( 'Nom de fichier invalide.', 'studio-kyne-mini-tools' ) ),
			'upload-disabled'     => array( 'type' => 'warning', 'message' => __( 'Upload désactivé dans les réglages.', 'studio-kyne-mini-tools' ) ),
			'upload-failed'       => array( 'type' => 'error', 'message' => __( 'Échec de l’upload.', 'studio-kyne-mini-tools' ) ),
			'save-success'        => array( 'type' => 'success', 'message' => __( 'Fichier enregistré.', 'studio-kyne-mini-tools' ) ),
			'save-failed'         => array( 'type' => 'error', 'message' => __( 'Impossible d’enregistrer le fichier.', 'studio-kyne-mini-tools' ) ),
			'save-invalid'        => array( 'type' => 'error', 'message' => __( 'Fichier non éditable.', 'studio-kyne-mini-tools' ) ),
			'edit-disabled'       => array( 'type' => 'warning', 'message' => __( 'Édition désactivée dans les réglages.', 'studio-kyne-mini-tools' ) ),
			'delete-success'      => array( 'type' => 'success', 'message' => __( 'Élément supprimé.', 'studio-kyne-mini-tools' ) ),
			'delete-failed'       => array( 'type' => 'error', 'message' => __( 'Impossible de supprimer cet élément.', 'studio-kyne-mini-tools' ) ),
			'delete-disabled'     => array( 'type' => 'warning', 'message' => __( 'Suppression désactivée dans les réglages.', 'studio-kyne-mini-tools' ) ),
			'delete-invalid'      => array( 'type' => 'error', 'message' => __( 'Élément invalide.', 'studio-kyne-mini-tools' ) ),
			'bulk-empty'          => array( 'type' => 'warning', 'message' => __( 'Aucun élément sélectionné.', 'studio-kyne-mini-tools' ) ),
			'bulk-invalid'        => array( 'type' => 'error', 'message' => __( 'Action de masse invalide.', 'studio-kyne-mini-tools' ) ),
			'bulk-delete-success' => array( 'type' => 'success', 'message' => __( 'Sélection supprimée.', 'studio-kyne-mini-tools' ) ),
			'bulk-delete-failed'  => array( 'type' => 'error', 'message' => __( 'Échec de suppression de la sélection.', 'studio-kyne-mini-tools' ) ),
			'invalid-path'        => array( 'type' => 'error', 'message' => __( 'Chemin invalide.', 'studio-kyne-mini-tools' ) ),
		);

		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}

		$payload = $map[ $notice ];
		echo '<div class="skmt-toast-stack" data-skmt-toast-stack>';
		echo '<div class="skmt-toast skmt-toast--' . esc_attr( $payload['type'] ) . '" role="status" aria-live="polite" data-skmt-toast>';
		echo '<div class="skmt-toast__message">' . esc_html( $payload['message'] ) . '</div>';
		echo '<button type="button" class="skmt-toast__close" aria-label="' . esc_attr__( 'Fermer la notification', 'studio-kyne-mini-tools' ) . '" data-skmt-toast-close>&times;</button>';
		echo '</div>';
		echo '</div>';
	}

	protected function redirect_admin( $notice, $dir = '', $edit = '' ) {
		$args = array(
			'page'              => 'skmt-files',
			'skmt_files_notice' => sanitize_key( (string) $notice ),
		);

		$dir = $this->normalize_rel_path( $dir );
		if ( '' !== $dir ) {
			$args['dir'] = $dir;
		}

		$edit = $this->normalize_rel_path( $edit );
		if ( '' !== $edit ) {
			$args['edit'] = $edit;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
