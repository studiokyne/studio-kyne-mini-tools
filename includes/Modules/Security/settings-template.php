<?php
/**
 * Template des réglages du module Sécurité.
 *
 * Variables disponibles (via module-settings.php):
 * @var string          $module_id       ID du module (security)
 * @var array           $module          Infos du module
 * @var ModuleInterface $instance        Instance du module
 * @var array           $module_settings Settings actuels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$auth      = $module_settings['authentication'] ?? [];
$hardening = $module_settings['hardening'] ?? [];
$logging   = $module_settings['logging'] ?? [];
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'skmt_save_settings', 'skmt_nonce' ); ?>
	<input type="hidden" name="action" value="skmt_save_settings">
	<input type="hidden" name="skmt_tab" value="<?php echo esc_attr( $tab ); ?>">

	<!-- ============================================================
		 AUTHENTIFICATION
		 ============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Authentification', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Gérez les règles d\'accès et de connexion.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">

			<!-- Password Strength -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_password_strength" class="skmt-option__label">
						<?php esc_html_e( 'Forcer un mot de passe fort', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc">
						<?php esc_html_e( 'Minimum 12 caractères avec majuscules, minuscules, chiffres et caractères spéciaux.', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input
							type="checkbox"
							id="skmt_password_strength"
							name="skmt_module_settings[password_strength]"
							value="1"
							data-security-toggle="password_strength"
							<?php checked( $auth['password_strength'] ?? true ); ?>
						/>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Rate Limiting -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_rate_limiting" class="skmt-option__label">
						<?php esc_html_e( 'Limiter les tentatives de connexion', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc">
						<?php esc_html_e( 'Bloquer les IPs après trop de tentatives échouées.', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input
							type="checkbox"
							id="skmt_rate_limiting"
							name="skmt_module_settings[rate_limiting]"
							value="1"
							data-security-toggle="rate_limiting"
							<?php checked( $auth['rate_limiting'] ?? false ); ?>
						/>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Rate Limiting Options (sous-options conditionnelles) -->
			<div
				data-depends-on="rate_limiting"
				class="skmt-security-sub"
				<?php echo ( $auth['rate_limiting'] ?? false ) ? '' : 'style="display:none;"'; ?>
			>
				<div class="skmt-form__row">
					<div class="skmt-form__group">
						<label for="rate_limit_attempts" class="skmt-form__label">
							<?php esc_html_e( 'Tentatives autorisées', 'studio-kyne-mini-tools' ); ?>
						</label>
						<input
							type="number"
							id="rate_limit_attempts"
							name="skmt_module_settings[rate_limit_attempts]"
							class="skmt-input skmt-input--sm"
							value="<?php echo esc_attr( $auth['rate_limit_attempts'] ?? 5 ); ?>"
							min="1"
							max="20"
						/>
					</div>

					<div class="skmt-form__group">
						<label for="rate_limit_window" class="skmt-form__label">
							<?php esc_html_e( 'Fenêtre (secondes)', 'studio-kyne-mini-tools' ); ?>
						</label>
						<input
							type="number"
							id="rate_limit_window"
							name="skmt_module_settings[rate_limit_window]"
							class="skmt-input skmt-input--sm"
							value="<?php echo esc_attr( $auth['rate_limit_window'] ?? 900 ); ?>"
							min="60"
							step="60"
						/>
						<p class="skmt-form__help">
							<?php echo esc_html( sprintf( __( '%d minutes', 'studio-kyne-mini-tools' ), (int) ( ( $auth['rate_limit_window'] ?? 900 ) / 60 ) ) ); ?>
						</p>
					</div>

					<div class="skmt-form__group">
						<label for="rate_limit_lockout" class="skmt-form__label">
							<?php esc_html_e( 'Blocage (secondes)', 'studio-kyne-mini-tools' ); ?>
						</label>
						<input
							type="number"
							id="rate_limit_lockout"
							name="skmt_module_settings[rate_limit_lockout]"
							class="skmt-input skmt-input--sm"
							value="<?php echo esc_attr( $auth['rate_limit_lockout'] ?? 1800 ); ?>"
							min="60"
							step="60"
						/>
						<p class="skmt-form__help">
							<?php echo esc_html( sprintf( __( '%d minutes', 'studio-kyne-mini-tools' ), (int) ( ( $auth['rate_limit_lockout'] ?? 1800 ) / 60 ) ) ); ?>
						</p>
					</div>
				</div>

				<div class="skmt-form__group">
					<label for="rate_limit_whitelist" class="skmt-form__label">
						<?php esc_html_e( 'IP whitelistées (une par ligne)', 'studio-kyne-mini-tools' ); ?>
					</label>
					<textarea
						id="rate_limit_whitelist"
						name="skmt_module_settings[rate_limit_whitelist]"
						class="skmt-input skmt-security-sub__textarea"
						rows="4"
						placeholder="192.168.1.1&#10;127.0.0.1"
					><?php echo esc_textarea( implode( "\n", $auth['rate_limit_whitelist'] ?? [] ) ); ?></textarea>
					<p class="skmt-form__help"><?php esc_html_e( 'Ces IPs ne seront jamais bloquées par le rate limiting.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
			</div>

			<!-- Disable Registration -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_disable_registration" class="skmt-option__label">
						<?php esc_html_e( 'Désactiver l\'inscription publique', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc">
						<?php esc_html_e( 'Empêcher les utilisateurs de s\'inscrire via le formulaire public.', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input
							type="checkbox"
							id="skmt_disable_registration"
							name="skmt_module_settings[disable_registration]"
							value="1"
							data-security-toggle="disable_registration"
							<?php checked( $auth['disable_registration'] ?? true ); ?>
						/>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Custom Login URL Toggle + Input -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_enable_custom_login_url" class="skmt-option__label">
						<?php esc_html_e( 'URL personnalisée de connexion', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc">
						<?php esc_html_e( 'Remplace /wp-login.php par une URL personnalisée et bloque l\'accès à la page de connexion standard.', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input
							type="checkbox"
							id="skmt_enable_custom_login_url"
							name="skmt_module_settings[enable_custom_login_url]"
							value="1"
							data-security-toggle="enable_custom_login_url"
							<?php checked( $auth['enable_custom_login_url'] ?? true ); ?>
						/>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Custom Login URL Input (Conditional) -->
			<div
				data-depends-on="enable_custom_login_url"
				class="skmt-security-sub"
				<?php echo ( $auth['enable_custom_login_url'] ?? true ) ? '' : 'style="display:none;"'; ?>
			>
				<div class="skmt-form__group">
					<label for="custom_login_url" class="skmt-form__label">
						<?php esc_html_e( 'Slug personnalisé', 'studio-kyne-mini-tools' ); ?>
					</label>
					<div class="skmt-custom-login-url">
						<span class="skmt-custom-login-url__base"><?php echo esc_html( trailingslashit( site_url() ) ); ?></span>
						<input
							type="text"
							id="custom_login_url"
							name="skmt_module_settings[custom_login_url]"
							class="skmt-input skmt-custom-login-url__input"
							value="<?php echo esc_attr( ltrim( $auth['custom_login_url'] ?? '/connexion', '/' ) ); ?>"
							placeholder="connexion"
						/>
					</div>
					<p class="skmt-form__help">
						<?php esc_html_e( 'Exemples : connexion, login, admin, etc.', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
			</div>

		</div>
	</div>

	<div class="skmt-divider"></div>

	<!-- ============================================================
		 HARDENING
		 ============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Hardening', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Renforcez la sécurité du site en limitant l\'exposition.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">

			<!-- Disable XML-RPC -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_disable_xmlrpc" class="skmt-option__label">
						<?php esc_html_e( 'Désactiver XML-RPC', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc">
						<?php esc_html_e( 'Désactive l\'API XML-RPC (rarement utilisée, peut présenter des risques).', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input
							type="checkbox"
							id="skmt_disable_xmlrpc"
							name="skmt_module_settings[disable_xmlrpc]"
							value="1"
							data-security-toggle="disable_xmlrpc"
							<?php checked( $hardening['disable_xmlrpc'] ?? false ); ?>
						/>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Prevent User Enumeration -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_prevent_user_enum" class="skmt-option__label">
						<?php esc_html_e( 'Empêcher l\'énumération des utilisateurs', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc">
						<?php esc_html_e( 'Bloque les requêtes ?author= et l\'accès REST aux utilisateurs.', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input
							type="checkbox"
							id="skmt_prevent_user_enum"
							name="skmt_module_settings[prevent_user_enum]"
							value="1"
							data-security-toggle="prevent_user_enum"
							<?php checked( $hardening['prevent_user_enum'] ?? true ); ?>
						/>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Hide WordPress Version -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_hide_wp_version" class="skmt-option__label">
						<?php esc_html_e( 'Masquer la version WordPress', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc">
						<?php esc_html_e( 'Retire la version WordPress des headers HTTP et du meta generator.', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input
							type="checkbox"
							id="skmt_hide_wp_version"
							name="skmt_module_settings[hide_wp_version]"
							value="1"
							data-security-toggle="hide_wp_version"
							<?php checked( $hardening['hide_wp_version'] ?? true ); ?>
						/>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

		</div>
	</div>

	<div class="skmt-divider"></div>

	<!-- ============================================================
		 LOGGING
		 ============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Journalisation', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Enregistrez les événements de sécurité pour auditer l\'activité.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">

			<!-- Log Connections -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_log_connections" class="skmt-option__label">
						<?php esc_html_e( 'Enregistrer les connexions', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc">
						<?php esc_html_e( 'Log les tentatives de connexion (réussies et échouées) avec l\'IP.', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input
							type="checkbox"
							id="skmt_log_connections"
							name="skmt_module_settings[log_connections]"
							value="1"
							data-security-toggle="log_connections"
							<?php checked( $logging['log_connections'] ?? false ); ?>
						/>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Log User Actions -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_log_user_actions" class="skmt-option__label">
						<?php esc_html_e( 'Enregistrer les actions utilisateurs', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc">
						<?php esc_html_e( 'Log les créations et suppressions d\'utilisateurs.', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input
							type="checkbox"
							id="skmt_log_user_actions"
							name="skmt_module_settings[log_user_actions]"
							value="1"
							data-security-toggle="log_user_actions"
							<?php checked( $logging['log_user_actions'] ?? false ); ?>
						/>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Log Settings Changes -->
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_log_settings_changes" class="skmt-option__label">
						<?php esc_html_e( 'Enregistrer les changements de configuration', 'studio-kyne-mini-tools' ); ?>
					</label>
					<p class="skmt-option__desc">
						<?php esc_html_e( 'Log les modifications des settings du module Sécurité.', 'studio-kyne-mini-tools' ); ?>
					</p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input
							type="checkbox"
							id="skmt_log_settings_changes"
							name="skmt_module_settings[log_settings_changes]"
							value="1"
							data-security-toggle="log_settings_changes"
							<?php checked( $logging['log_settings_changes'] ?? false ); ?>
						/>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<!-- Log Retention -->
			<div class="skmt-form__group">
				<label for="log_retention_days" class="skmt-form__label">
					<?php esc_html_e( 'Rétention des logs (jours)', 'studio-kyne-mini-tools' ); ?>
				</label>
				<input
					type="number"
					id="log_retention_days"
					name="skmt_module_settings[log_retention_days]"
					class="skmt-input skmt-input--sm"
					value="<?php echo esc_attr( $logging['log_retention_days'] ?? 30 ); ?>"
					min="1"
					max="365"
				/>
				<p class="skmt-form__help"><?php esc_html_e( 'Les logs plus anciens seront supprimés automatiquement.', 'studio-kyne-mini-tools' ); ?></p>
			</div>

			<!-- Warning about logging -->
			<div class="skmt-notice skmt-notice--warning">
				<?php esc_html_e( 'La journalisation augmente la base de données. Utilisez avec modération sur les sites à fort trafic.', 'studio-kyne-mini-tools' ); ?>
			</div>

		</div>
	</div>

	<div class="skmt-page__footer">
		<button type="submit" class="skmt-btn skmt-btn--primary">
			<?php esc_html_e( 'Enregistrer les paramètres', 'studio-kyne-mini-tools' ); ?>
		</button>
	</div>

</form>
