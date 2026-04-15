<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SKMT_Module_Feedback implements SKMT_Module_Interface {
	protected $plugin;
	protected $option_name = 'skmt_feedback_settings';
	protected $items_option_name = 'skmt_feedback_items';
	protected $share_query_arg = 'skmt_feedback_access';
	protected $session_cookie_name = 'skmt_feedback_session';
	protected $max_items = 1000;
	protected $defaults = array(
		'enabled'            => 1,
		'share_token'        => '',
		'share_password_hash'=> '',
		'share_expires_at'   => 0,
		'allow_mobile_mode'  => 1,
	);

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function get_id() { return 'feedback'; }
	public function get_name() { return __( 'Feedback', 'studio-kyne-mini-tools' ); }
	public function get_description() { return __( 'Lien sécurisé client pour laisser des retours in-page avec HUD, catégories et positions.', 'studio-kyne-mini-tools' ); }
	public function get_icon() { return 'message-circle'; }
	public function is_default_active() { return true; }
	public function is_configurable() { return true; }
	public function activate() { $this->maybe_seed_defaults(); }
	public function deactivate() { $this->clear_session_cookie(); }

	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_skmt_feedback_regenerate_link', array( $this, 'handle_regenerate_link' ) );
		add_action( 'admin_post_skmt_feedback_update_item', array( $this, 'handle_update_item' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );

		add_action( 'wp_ajax_skmt_feedback_auth', array( $this, 'ajax_auth' ) );
		add_action( 'wp_ajax_nopriv_skmt_feedback_auth', array( $this, 'ajax_auth' ) );
		add_action( 'wp_ajax_skmt_feedback_list', array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_nopriv_skmt_feedback_list', array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_skmt_feedback_add', array( $this, 'ajax_add' ) );
		add_action( 'wp_ajax_nopriv_skmt_feedback_add', array( $this, 'ajax_add' ) );
	}

	public function register_admin_pages( $parent_slug ) {
		add_submenu_page(
			$parent_slug,
			__( 'Feedback', 'studio-kyne-mini-tools' ),
			__( 'Feedback', 'studio-kyne-mini-tools' ),
			SKMT_Capabilities::admin_capability(),
			'skmt-feedback',
			array( $this, 'render_admin_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'skmt_feedback_group',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->defaults,
			)
		);
	}

	public function get_settings() {
		$stored   = get_option( $this->option_name, array() );
		$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), $this->defaults );

		if ( empty( $settings['share_token'] ) ) {
			$settings['share_token'] = $this->generate_share_token();
			update_option( $this->option_name, $settings, false );
		}

		$settings['enabled']           = empty( $settings['enabled'] ) ? 0 : 1;
		$settings['allow_mobile_mode'] = empty( $settings['allow_mobile_mode'] ) ? 0 : 1;
		$settings['share_expires_at']  = absint( $settings['share_expires_at'] );

		return $settings;
	}

	public function sanitize_settings( $input ) {
		$current = $this->get_settings();
		$input   = is_array( $input ) ? $input : array();

		$current['enabled']           = empty( $input['enabled'] ) ? 0 : 1;
		$current['allow_mobile_mode'] = empty( $input['allow_mobile_mode'] ) ? 0 : 1;

		$expires_value = isset( $input['share_expires_at'] ) ? sanitize_text_field( wp_unslash( (string) $input['share_expires_at'] ) ) : '';
		$current['share_expires_at'] = $this->parse_datetime_input( $expires_value );

		$new_password = isset( $input['share_password'] ) ? trim( (string) wp_unslash( $input['share_password'] ) ) : '';
		if ( '' !== $new_password ) {
			$current['share_password_hash'] = wp_hash_password( $new_password );
		}

		if ( empty( $current['share_token'] ) ) {
			$current['share_token'] = $this->generate_share_token();
		}

		return $current;
	}

	public function render_admin_page() {
		if ( ! SKMT_Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Vous n’avez pas l’autorisation d’accéder à cette page.', 'studio-kyne-mini-tools' ) );
		}

		$settings        = $this->get_settings();
		$share_url       = add_query_arg( $this->share_query_arg, (string) $settings['share_token'], home_url( '/' ) );
		$password_set    = ! empty( $settings['share_password_hash'] );
		$expires_display = $settings['share_expires_at'] > 0 ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $settings['share_expires_at'] ) : __( 'Jamais', 'studio-kyne-mini-tools' );
		$expires_input   = $settings['share_expires_at'] > 0 ? wp_date( 'Y-m-d\\TH:i', $settings['share_expires_at'] ) : '';
		$notice          = isset( $_GET['skmt_feedback_notice'] ) ? sanitize_key( wp_unslash( $_GET['skmt_feedback_notice'] ) ) : '';
		if ( '' === $notice && ! empty( $_GET['settings-updated'] ) ) {
			$notice = 'settings-saved';
		}
		$items           = $this->get_items();
		$open_count      = 0;
		$resolved_count  = 0;

		foreach ( $items as $item ) {
			$status = isset( $item['status'] ) ? sanitize_key( $item['status'] ) : 'open';
			if ( 'resolved' === $status ) {
				++$resolved_count;
			} else {
				++$open_count;
			}
		}

		$this->render_notice( $notice );
		?>
		<div class="wrap skmt-wrap">
			<div class="skmt-shell">
				<header class="skmt-page-head">
					<div>
						<h1><?php echo esc_html__( 'Feedback', 'studio-kyne-mini-tools' ); ?></h1>
						<p><?php echo esc_html__( 'Partagez un lien sécurisé avec le client pour collecter des retours visuels sur toutes les pages du site.', 'studio-kyne-mini-tools' ); ?></p>
					</div>
					<span class="skmt-badge skmt-badge--<?php echo ! empty( $settings['enabled'] ) ? 'success' : 'muted'; ?>"><?php echo esc_html( ! empty( $settings['enabled'] ) ? __( 'Actif', 'studio-kyne-mini-tools' ) : __( 'Inactif', 'studio-kyne-mini-tools' ) ); ?></span>
				</header>

				<div class="skmt-grid skmt-grid--3">
					<div class="skmt-card">
						<h2><?php echo esc_html__( 'Retours ouverts', 'studio-kyne-mini-tools' ); ?></h2>
						<div class="skmt-stat"><?php echo esc_html( (string) $open_count ); ?></div>
					</div>
					<div class="skmt-card">
						<h2><?php echo esc_html__( 'Retours résolus', 'studio-kyne-mini-tools' ); ?></h2>
						<div class="skmt-stat"><?php echo esc_html( (string) $resolved_count ); ?></div>
					</div>
					<div class="skmt-card">
						<h2><?php echo esc_html__( 'Expiration du lien', 'studio-kyne-mini-tools' ); ?></h2>
						<div class="skmt-stat skmt-stat--small"><?php echo esc_html( $expires_display ); ?></div>
					</div>
				</div>

				<div class="skmt-grid skmt-grid--2">
					<div class="skmt-card">
						<div class="skmt-card-head">
							<h2><?php echo esc_html__( 'Lien client sécurisé', 'studio-kyne-mini-tools' ); ?></h2>
						</div>
						<form action="options.php" method="post">
							<?php settings_fields( 'skmt_feedback_group' ); ?>
							<div class="skmt-field">
								<label class="skmt-toggle">
									<input type="checkbox" name="skmt_feedback_settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
									<span></span>
									<strong><?php echo esc_html__( 'Activer le module Feedback', 'studio-kyne-mini-tools' ); ?></strong>
								</label>
							</div>
							<div class="skmt-field">
								<label for="skmt-feedback-share-url"><strong><?php echo esc_html__( 'Lien à partager', 'studio-kyne-mini-tools' ); ?></strong></label>
								<input id="skmt-feedback-share-url" type="text" readonly value="<?php echo esc_attr( $share_url ); ?>" />
								<p class="description"><?php echo esc_html__( 'Un lien unique pour tout le projet. Le client peut naviguer librement de page en page.', 'studio-kyne-mini-tools' ); ?></p>
							</div>
							<div class="skmt-field">
								<label for="skmt-feedback-password"><strong><?php echo esc_html__( 'Mot de passe du lien', 'studio-kyne-mini-tools' ); ?></strong></label>
								<input id="skmt-feedback-password" type="password" name="skmt_feedback_settings[share_password]" autocomplete="new-password" placeholder="<?php echo esc_attr__( 'Laisser vide pour conserver le mot de passe actuel', 'studio-kyne-mini-tools' ); ?>" />
								<p class="description"><?php echo esc_html( $password_set ? __( 'Un mot de passe est déjà configuré.', 'studio-kyne-mini-tools' ) : __( 'Aucun mot de passe défini. Configurez-en un avant partage.', 'studio-kyne-mini-tools' ) ); ?></p>
							</div>
							<div class="skmt-field">
								<label for="skmt-feedback-expiration"><strong><?php echo esc_html__( 'Date d’expiration', 'studio-kyne-mini-tools' ); ?></strong></label>
								<input id="skmt-feedback-expiration" type="datetime-local" name="skmt_feedback_settings[share_expires_at]" value="<?php echo esc_attr( $expires_input ); ?>" />
								<p class="description"><?php echo esc_html__( 'Laissez vide pour ne pas expirer automatiquement le lien.', 'studio-kyne-mini-tools' ); ?></p>
							</div>
							<div class="skmt-field">
								<label class="skmt-toggle">
									<input type="checkbox" name="skmt_feedback_settings[allow_mobile_mode]" value="1" <?php checked( ! empty( $settings['allow_mobile_mode'] ) ); ?> />
									<span></span>
									<strong><?php echo esc_html__( 'Activer le mode mobile dédié dans le HUD', 'studio-kyne-mini-tools' ); ?></strong>
								</label>
							</div>
							<?php submit_button( __( 'Enregistrer les réglages', 'studio-kyne-mini-tools' ) ); ?>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="skmt_feedback_regenerate_link" />
							<?php wp_nonce_field( 'skmt_feedback_regenerate_link' ); ?>
							<div class="skmt-actions">
								<button type="submit" class="button"><?php echo esc_html__( 'Régénérer le lien', 'studio-kyne-mini-tools' ); ?></button>
							</div>
						</form>
					</div>

					<div class="skmt-card">
						<div class="skmt-card-head">
							<h2><?php echo esc_html__( 'Catégories disponibles', 'studio-kyne-mini-tools' ); ?></h2>
						</div>
						<ul class="skmt-status-list">
							<?php foreach ( $this->get_categories() as $key => $label ) : ?>
								<li>
									<span><?php echo esc_html( $label ); ?></span>
									<span class="skmt-feedback-category-pill skmt-feedback-category-pill--<?php echo esc_attr( '' !== $key ? $key : 'none' ); ?>"></span>
								</li>
							<?php endforeach; ?>
						</ul>
						<p class="description"><?php echo esc_html__( 'Le client choisit une catégorie optionnelle pour chaque remarque (comme dans les outils de design).', 'studio-kyne-mini-tools' ); ?></p>
					</div>
				</div>

				<div class="skmt-card">
					<div class="skmt-card-head">
						<h2><?php echo esc_html__( 'Retours collectés', 'studio-kyne-mini-tools' ); ?></h2>
					</div>
					<?php if ( empty( $items ) ) : ?>
						<p class="description"><?php echo esc_html__( 'Aucun feedback reçu pour le moment.', 'studio-kyne-mini-tools' ); ?></p>
					<?php else : ?>
						<div class="skmt-table-wrap skmt-table-wrap--history">
							<table class="widefat fixed striped skmt-table">
								<thead>
									<tr>
										<th><?php echo esc_html__( 'Date', 'studio-kyne-mini-tools' ); ?></th>
										<th><?php echo esc_html__( 'Page', 'studio-kyne-mini-tools' ); ?></th>
										<th><?php echo esc_html__( 'Catégorie', 'studio-kyne-mini-tools' ); ?></th>
										<th><?php echo esc_html__( 'Mode', 'studio-kyne-mini-tools' ); ?></th>
										<th><?php echo esc_html__( 'Message', 'studio-kyne-mini-tools' ); ?></th>
										<th><?php echo esc_html__( 'Statut', 'studio-kyne-mini-tools' ); ?></th>
										<th><?php echo esc_html__( 'Actions', 'studio-kyne-mini-tools' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $items as $item ) : ?>
										<?php
										$item_id      = sanitize_text_field( (string) ( $item['id'] ?? '' ) );
										$status       = sanitize_key( (string) ( $item['status'] ?? 'open' ) );
										$status_badge = 'resolved' === $status ? 'success' : 'warning';
										$category     = sanitize_key( (string) ( $item['category'] ?? '' ) );
										$mode         = sanitize_key( (string) ( $item['device_mode'] ?? 'desktop' ) );
										$page_url     = esc_url( (string) ( $item['page_url'] ?? home_url( '/' ) ) );
										$page_key     = sanitize_text_field( (string) ( $item['page_key'] ?? '/' ) );
										$created_at   = sanitize_text_field( (string) ( $item['created_at'] ?? '' ) );
										$message      = sanitize_textarea_field( (string) ( $item['comment'] ?? '' ) );
										?>
										<tr>
											<td><?php echo esc_html( $created_at ); ?></td>
											<td><a href="<?php echo $page_url; ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $page_key ); ?></a></td>
											<td><span class="skmt-feedback-category-pill skmt-feedback-category-pill--<?php echo esc_attr( '' !== $category ? $category : 'none' ); ?>"></span> <?php echo esc_html( $this->get_category_label( $category ) ); ?></td>
											<td><?php echo esc_html( 'mobile' === $mode ? __( 'Mobile', 'studio-kyne-mini-tools' ) : __( 'Desktop', 'studio-kyne-mini-tools' ) ); ?></td>
											<td><?php echo esc_html( $message ); ?></td>
											<td><span class="skmt-badge skmt-badge--<?php echo esc_attr( $status_badge ); ?>"><?php echo esc_html( 'resolved' === $status ? __( 'Résolu', 'studio-kyne-mini-tools' ) : __( 'Ouvert', 'studio-kyne-mini-tools' ) ); ?></span></td>
											<td>
												<div class="skmt-actions">
													<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
														<input type="hidden" name="action" value="skmt_feedback_update_item" />
														<input type="hidden" name="item_id" value="<?php echo esc_attr( $item_id ); ?>" />
														<input type="hidden" name="update_type" value="<?php echo esc_attr( 'resolved' === $status ? 'open' : 'resolved' ); ?>" />
														<?php wp_nonce_field( 'skmt_feedback_update_item' ); ?>
														<button type="submit" class="button"><?php echo esc_html( 'resolved' === $status ? __( 'Rouvrir', 'studio-kyne-mini-tools' ) : __( 'Marquer résolu', 'studio-kyne-mini-tools' ) ); ?></button>
													</form>
													<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
														<input type="hidden" name="action" value="skmt_feedback_update_item" />
														<input type="hidden" name="item_id" value="<?php echo esc_attr( $item_id ); ?>" />
														<input type="hidden" name="update_type" value="delete" />
														<?php wp_nonce_field( 'skmt_feedback_update_item' ); ?>
														<button type="submit" class="button skmt-button-danger"><?php echo esc_html__( 'Supprimer', 'studio-kyne-mini-tools' ); ?></button>
													</form>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public function handle_regenerate_link() {
		if ( ! SKMT_Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Accès refusé.', 'studio-kyne-mini-tools' ) );
		}

		check_admin_referer( 'skmt_feedback_regenerate_link' );

		$settings               = $this->get_settings();
		$settings['share_token']= $this->generate_share_token();
		update_option( $this->option_name, $settings, false );
		$this->clear_session_cookie();

		SKMT_Notifications::add( 'info', __( 'Lien Feedback régénéré.', 'studio-kyne-mini-tools' ) );
		$this->redirect_admin( 'link-regenerated' );
	}

	public function handle_update_item() {
		if ( ! SKMT_Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Accès refusé.', 'studio-kyne-mini-tools' ) );
		}

		check_admin_referer( 'skmt_feedback_update_item' );

		$item_id    = isset( $_POST['item_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['item_id'] ) ) : '';
		$update_type = isset( $_POST['update_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['update_type'] ) ) : '';

		if ( '' === $item_id || ! in_array( $update_type, array( 'resolved', 'open', 'delete' ), true ) ) {
			$this->redirect_admin( 'update-error' );
		}

		$items    = $this->get_items();
		$updated  = false;
		$new_items = array();

		foreach ( $items as $item ) {
			$current_id = sanitize_text_field( (string) ( $item['id'] ?? '' ) );
			if ( $current_id !== $item_id ) {
				$new_items[] = $item;
				continue;
			}

			$updated = true;
			if ( 'delete' === $update_type ) {
				continue;
			}

			$item['status']      = 'resolved' === $update_type ? 'resolved' : 'open';
			$item['updated_at']  = current_time( 'mysql' );
			$new_items[]         = $item;
		}

		if ( ! $updated ) {
			$this->redirect_admin( 'update-error' );
		}

		update_option( $this->items_option_name, array_values( $new_items ), false );

		if ( 'delete' === $update_type ) {
			SKMT_Notifications::add( 'warning', __( 'Feedback supprimé.', 'studio-kyne-mini-tools' ) );
			$this->redirect_admin( 'item-deleted' );
		}

		SKMT_Notifications::add( 'success', __( 'Statut du feedback mis à jour.', 'studio-kyne-mini-tools' ) );
		$this->redirect_admin( 'item-updated' );
	}

	public function enqueue_front_assets() {
		if ( is_admin() ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$has_session      = $this->has_valid_session( $settings );
		$request_token    = $this->get_request_token();
		$has_request_auth = $this->is_request_token_valid( $settings, $request_token );
		$is_admin_user    = SKMT_Capabilities::current_user_can_manage();

		if ( ! $is_admin_user && ! $has_session && ! $has_request_auth ) {
			return;
		}

		wp_enqueue_style( 'skmt-feedback-front', SKMT_PLUGIN_URL . 'assets/feedback/feedback.css', array(), SKMT_VERSION );
		wp_enqueue_script( 'skmt-feedback-front', SKMT_PLUGIN_URL . 'assets/feedback/feedback.js', array(), SKMT_VERSION, true );

		wp_localize_script(
			'skmt-feedback-front',
			'skmtFeedback',
			array(
				'enabled'         => true,
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'hasSession'      => $has_session || $is_admin_user,
				'tokenFromUrl'    => $has_request_auth ? $request_token : '',
				'allowMobileMode' => ! empty( $settings['allow_mobile_mode'] ),
				'categories'      => $this->get_categories(),
				'currentUrl'      => $this->get_current_url(),
				'strings'         => array(
					'hudTitle'           => __( 'Feedback', 'studio-kyne-mini-tools' ),
					'lockedTitle'        => __( 'Lien protégé', 'studio-kyne-mini-tools' ),
					'passwordPlaceholder'=> __( 'Mot de passe', 'studio-kyne-mini-tools' ),
					'unlock'             => __( 'Déverrouiller', 'studio-kyne-mini-tools' ),
					'unlockSuccess'      => __( 'Accès autorisé.', 'studio-kyne-mini-tools' ),
					'unlockError'        => __( 'Mot de passe invalide ou lien expiré.', 'studio-kyne-mini-tools' ),
					'pickElement'        => __( 'Sélectionner un élément', 'studio-kyne-mini-tools' ),
					'pickHint'           => __( 'Cliquez sur un élément de la page, puis rédigez votre remarque.', 'studio-kyne-mini-tools' ),
					'selectedElement'    => __( 'Élément sélectionné.', 'studio-kyne-mini-tools' ),
					'feedbackPlaceholder'=> __( 'Décrivez votre retour (texte, bug responsive, interaction, etc.)', 'studio-kyne-mini-tools' ),
					'sendFeedback'       => __( 'Envoyer la remarque', 'studio-kyne-mini-tools' ),
					'commentRequired'    => __( 'Merci de saisir un commentaire.', 'studio-kyne-mini-tools' ),
					'targetRequired'     => __( 'Sélectionnez un élément avant d’envoyer.', 'studio-kyne-mini-tools' ),
					'sendSuccess'        => __( 'Feedback envoyé.', 'studio-kyne-mini-tools' ),
					'loadError'          => __( 'Impossible de charger les feedbacks.', 'studio-kyne-mini-tools' ),
					'mobileMode'         => __( 'Mode mobile', 'studio-kyne-mini-tools' ),
					'mobileFrame'        => __( 'Cadre mobile visuel', 'studio-kyne-mini-tools' ),
					'categoryLabel'      => __( 'Catégorie', 'studio-kyne-mini-tools' ),
				),
			)
		);
	}

	public function ajax_auth() {
		$settings = $this->get_settings();

		if ( empty( $settings['enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Module désactivé.', 'studio-kyne-mini-tools' ) ), 403 );
		}

		$token    = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['token'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		if ( ! $this->is_request_token_valid( $settings, $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Lien invalide.', 'studio-kyne-mini-tools' ) ), 403 );
		}

		if ( $this->is_share_expired( $settings ) ) {
			wp_send_json_error( array( 'message' => __( 'Lien expiré.', 'studio-kyne-mini-tools' ) ), 403 );
		}

		if ( empty( $settings['share_password_hash'] ) || ! wp_check_password( $password, $settings['share_password_hash'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Mot de passe invalide.', 'studio-kyne-mini-tools' ) ), 403 );
		}

		$this->set_session_cookie( $settings );

		SKMT_Notifications::add( 'info', __( 'Session Feedback client ouverte.', 'studio-kyne-mini-tools' ) );
		wp_send_json_success( array( 'message' => __( 'Accès autorisé.', 'studio-kyne-mini-tools' ) ) );
	}

	public function ajax_list() {
		$settings = $this->get_settings();
		if ( ! $this->can_access_api( $settings ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'studio-kyne-mini-tools' ) ), 403 );
		}

		$page_url = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['page_url'] ) ) : $this->get_current_url();
		$page_key = $this->make_page_key( $page_url );
		$items    = $this->get_items();

		$filtered = array_values(
			array_filter(
				$items,
				function ( $item ) use ( $page_key ) {
					$item_key = sanitize_text_field( (string) ( $item['page_key'] ?? '' ) );
					return $item_key === $page_key;
				}
			)
		);

		wp_send_json_success( array( 'items' => $filtered ) );
	}

	public function ajax_add() {
		$settings = $this->get_settings();
		if ( ! $this->can_access_api( $settings ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'studio-kyne-mini-tools' ) ), 403 );
		}

		$comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['comment'] ) ) : '';
		if ( '' === trim( $comment ) ) {
			wp_send_json_error( array( 'message' => __( 'Commentaire obligatoire.', 'studio-kyne-mini-tools' ) ), 422 );
		}

		$page_url      = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['page_url'] ) ) : $this->get_current_url();
		$page_key      = $this->make_page_key( $page_url );
		$selector      = isset( $_POST['selector'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['selector'] ) ) : '';
		$category      = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( (string) $_POST['category'] ) ) : '';
		$device_mode   = isset( $_POST['device_mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['device_mode'] ) ) : 'desktop';
		$mobile_width  = isset( $_POST['mobile_width'] ) ? absint( wp_unslash( $_POST['mobile_width'] ) ) : 0;
		$viewport_w    = isset( $_POST['viewport_width'] ) ? absint( wp_unslash( $_POST['viewport_width'] ) ) : 0;
		$viewport_h    = isset( $_POST['viewport_height'] ) ? absint( wp_unslash( $_POST['viewport_height'] ) ) : 0;
		$x_percent     = isset( $_POST['x_percent'] ) ? $this->sanitize_percent( wp_unslash( $_POST['x_percent'] ) ) : 0;
		$y_percent     = isset( $_POST['y_percent'] ) ? $this->sanitize_percent( wp_unslash( $_POST['y_percent'] ) ) : 0;

		$item = array(
			'id'             => wp_generate_uuid4(),
			'page_url'       => $page_url,
			'page_key'       => $page_key,
			'selector'       => $selector,
			'comment'        => $comment,
			'category'       => $this->normalize_category( $category ),
			'status'         => 'open',
			'device_mode'    => in_array( $device_mode, array( 'desktop', 'mobile' ), true ) ? $device_mode : 'desktop',
			'mobile_width'   => $mobile_width,
			'viewport_width' => $viewport_w,
			'viewport_height'=> $viewport_h,
			'x_percent'      => $x_percent,
			'y_percent'      => $y_percent,
			'created_at'     => current_time( 'mysql' ),
		);

		$items = $this->get_items();
		array_unshift( $items, $item );
		if ( count( $items ) > $this->max_items ) {
			$items = array_slice( $items, 0, $this->max_items );
		}

		update_option( $this->items_option_name, array_values( $items ), false );

		SKMT_Notifications::add(
			'info',
			sprintf(
				/* translators: %s page key */
				__( 'Nouveau feedback reçu sur %s', 'studio-kyne-mini-tools' ),
				$page_key
			)
		);

		wp_send_json_success( array( 'item' => $item ) );
	}

	protected function can_access_api( $settings ) {
		if ( SKMT_Capabilities::current_user_can_manage() ) {
			return true;
		}

		if ( empty( $settings['enabled'] ) || $this->is_share_expired( $settings ) ) {
			return false;
		}

		return $this->has_valid_session( $settings );
	}

	protected function get_items() {
		$items = get_option( $this->items_option_name, array() );
		$items = is_array( $items ) ? $items : array();

		return array_values(
			array_map(
				array( $this, 'sanitize_item' ),
				$items
			)
		);
	}

	protected function sanitize_item( $item ) {
		$item = is_array( $item ) ? $item : array();

		return array(
			'id'              => sanitize_text_field( (string) ( $item['id'] ?? '' ) ),
			'page_url'        => esc_url_raw( (string) ( $item['page_url'] ?? home_url( '/' ) ) ),
			'page_key'        => sanitize_text_field( (string) ( $item['page_key'] ?? '/' ) ),
			'selector'        => sanitize_text_field( (string) ( $item['selector'] ?? '' ) ),
			'comment'         => sanitize_textarea_field( (string) ( $item['comment'] ?? '' ) ),
			'category'        => $this->normalize_category( (string) ( $item['category'] ?? '' ) ),
			'status'          => in_array( sanitize_key( (string) ( $item['status'] ?? 'open' ) ), array( 'open', 'resolved' ), true ) ? sanitize_key( (string) ( $item['status'] ?? 'open' ) ) : 'open',
			'device_mode'     => in_array( sanitize_key( (string) ( $item['device_mode'] ?? 'desktop' ) ), array( 'desktop', 'mobile' ), true ) ? sanitize_key( (string) ( $item['device_mode'] ?? 'desktop' ) ) : 'desktop',
			'mobile_width'    => absint( $item['mobile_width'] ?? 0 ),
			'viewport_width'  => absint( $item['viewport_width'] ?? 0 ),
			'viewport_height' => absint( $item['viewport_height'] ?? 0 ),
			'x_percent'       => $this->sanitize_percent( $item['x_percent'] ?? 0 ),
			'y_percent'       => $this->sanitize_percent( $item['y_percent'] ?? 0 ),
			'created_at'      => sanitize_text_field( (string) ( $item['created_at'] ?? '' ) ),
			'updated_at'      => sanitize_text_field( (string) ( $item['updated_at'] ?? '' ) ),
		);
	}

	protected function maybe_seed_defaults() {
		if ( false !== get_option( $this->option_name, false ) ) {
			return;
		}

		$defaults               = $this->defaults;
		$defaults['share_token']= $this->generate_share_token();
		add_option( $this->option_name, $defaults );
	}

	protected function generate_share_token() {
		return wp_generate_password( 32, false, false );
	}

	protected function parse_datetime_input( $datetime_value ) {
		$datetime_value = trim( (string) $datetime_value );
		if ( '' === $datetime_value ) {
			return 0;
		}

		$timestamp = strtotime( $datetime_value );
		if ( false === $timestamp ) {
			return 0;
		}

		return max( 0, (int) $timestamp );
	}

	protected function get_request_token() {
		return isset( $_GET[ $this->share_query_arg ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $this->share_query_arg ] ) ) : '';
	}

	protected function is_request_token_valid( $settings, $request_token ) {
		$request_token = (string) $request_token;
		$share_token   = (string) ( $settings['share_token'] ?? '' );

		if ( '' === $request_token || '' === $share_token || $this->is_share_expired( $settings ) ) {
			return false;
		}

		return hash_equals( $share_token, $request_token );
	}

	protected function is_share_expired( $settings ) {
		$expires_at = absint( $settings['share_expires_at'] ?? 0 );
		if ( $expires_at < 1 ) {
			return false;
		}

		return time() > $expires_at;
	}

	protected function has_valid_session( $settings ) {
		if ( $this->is_share_expired( $settings ) ) {
			return false;
		}

		if ( empty( $_COOKIE[ $this->session_cookie_name ] ) ) {
			return false;
		}

		$raw  = sanitize_text_field( wp_unslash( (string) $_COOKIE[ $this->session_cookie_name ] ) );
		$bits = explode( '|', $raw );
		if ( 3 !== count( $bits ) ) {
			return false;
		}

		$token     = (string) $bits[0];
		$expires   = absint( $bits[1] );
		$signature = (string) $bits[2];
		$password_hash = (string) ( $settings['share_password_hash'] ?? '' );
		$expected  = hash_hmac( 'sha256', $token . '|' . $expires . '|' . $password_hash, wp_salt( 'auth' ) );

		if ( ! hash_equals( (string) ( $settings['share_token'] ?? '' ), $token ) ) {
			return false;
		}

		if ( $expires < time() ) {
			return false;
		}

		if ( ! hash_equals( $expected, $signature ) ) {
			return false;
		}

		return true;
	}

	protected function set_session_cookie( $settings ) {
		$share_expiration = absint( $settings['share_expires_at'] ?? 0 );
		$expiration       = time() + DAY_IN_SECONDS;

		if ( $share_expiration > 0 ) {
			$expiration = min( $expiration, $share_expiration );
		}

		$token     = (string) ( $settings['share_token'] ?? '' );
		$password_hash = (string) ( $settings['share_password_hash'] ?? '' );
		$signature = hash_hmac( 'sha256', $token . '|' . $expiration . '|' . $password_hash, wp_salt( 'auth' ) );
		$value     = $token . '|' . $expiration . '|' . $signature;

		setcookie( $this->session_cookie_name, $value, $expiration, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		if ( COOKIEPATH !== SITECOOKIEPATH ) {
			setcookie( $this->session_cookie_name, $value, $expiration, SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		}
	}

	protected function clear_session_cookie() {
		setcookie( $this->session_cookie_name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		if ( COOKIEPATH !== SITECOOKIEPATH ) {
			setcookie( $this->session_cookie_name, '', time() - 3600, SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		}
	}

	protected function make_page_key( $url ) {
		$parsed = wp_parse_url( (string) $url );
		if ( ! is_array( $parsed ) ) {
			return '/';
		}

		$path  = isset( $parsed['path'] ) ? (string) $parsed['path'] : '/';
		$query = isset( $parsed['query'] ) && '' !== (string) $parsed['query'] ? '?' . (string) $parsed['query'] : '';

		$path = '/' . ltrim( $path, '/' );
		$path = preg_replace( '#/+#', '/', $path );

		return sanitize_text_field( $path . $query );
	}

	protected function get_current_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return esc_url_raw( home_url( $request_uri ) );
	}

	protected function sanitize_percent( $value ) {
		$number = floatval( $value );
		if ( $number < 0 ) {
			$number = 0;
		}
		if ( $number > 100 ) {
			$number = 100;
		}
		return round( $number, 4 );
	}

	protected function get_categories() {
		return array(
			''              => __( 'No category', 'studio-kyne-mini-tools' ),
			'development'   => __( 'Development', 'studio-kyne-mini-tools' ),
			'interaction'   => __( 'Interaction', 'studio-kyne-mini-tools' ),
			'accessibility' => __( 'Accessibility', 'studio-kyne-mini-tools' ),
			'content'       => __( 'Content', 'studio-kyne-mini-tools' ),
		);
	}

	protected function normalize_category( $category ) {
		$category = sanitize_key( (string) $category );
		$allowed  = array_keys( $this->get_categories() );

		if ( ! in_array( $category, $allowed, true ) ) {
			return '';
		}

		return $category;
	}

	protected function get_category_label( $category ) {
		$categories = $this->get_categories();
		$category   = $this->normalize_category( $category );
		return $categories[ $category ] ?? $categories[''];
	}

	protected function render_notice( $notice ) {
		if ( '' === (string) $notice ) {
			return;
		}

		$map = array(
			'settings-saved'  => array( 'type' => 'success', 'message' => __( 'Réglages Feedback enregistrés.', 'studio-kyne-mini-tools' ) ),
			'link-regenerated'=> array( 'type' => 'warning', 'message' => __( 'Le lien a été régénéré.', 'studio-kyne-mini-tools' ) ),
			'item-updated'    => array( 'type' => 'success', 'message' => __( 'Le feedback a été mis à jour.', 'studio-kyne-mini-tools' ) ),
			'item-deleted'    => array( 'type' => 'warning', 'message' => __( 'Le feedback a été supprimé.', 'studio-kyne-mini-tools' ) ),
			'update-error'    => array( 'type' => 'error', 'message' => __( 'Impossible de mettre à jour ce feedback.', 'studio-kyne-mini-tools' ) ),
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

	protected function redirect_admin( $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                 => 'skmt-feedback',
					'skmt_feedback_notice' => sanitize_key( (string) $notice ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
