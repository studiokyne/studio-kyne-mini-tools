<?php
/**
 * Template pour les settings du module Sécurité.
 *
 * Variables disponibles:
 * @var array $settings Les settings actuels du module
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$auth      = $settings['authentication'] ?? [];
$hardening = $settings['hardening'] ?? [];
$logging   = $settings['logging'] ?? [];
?>

<div id="skmt-security-messages"></div>

<form id="skmt-security-settings-form" class="skmt-settings-form">

	<!-- ============================================================
		 AUTHENTIFICATION
		 ============================================================ -->
	<div class="skmt-security-section">
		<h3><?php esc_html_e( 'Authentification', 'studio-kyne-mini-tools' ); ?></h3>

		<!-- Rate Limiting -->
		<div class="skmt-security-option">
			<label>
				<input
					type="checkbox"
					name="rate_limiting"
					data-security-setting="authentication.rate_limiting"
					data-security-toggle="rate_limiting"
					<?php checked( $auth['rate_limiting'] ?? false ); ?>
				/>
				<?php esc_html_e( 'Limiter les tentatives de connexion', 'studio-kyne-mini-tools' ); ?>
			</label>
			<div class="skmt-security-option-description">
				<?php esc_html_e( 'Bloquer les IPs après trop de tentatives échouées.', 'studio-kyne-mini-tools' ); ?>
			</div>
		</div>

		<!-- Rate Limiting Options (dépendant) -->
		<div
			data-depends-on="rate_limiting"
			style="<?php echo ( $auth['rate_limiting'] ?? false ) ? '' : 'display:none;'; ?>"
			class="skmt-security-option"
			style="margin-left: 24px; padding: 15px; background: #f9f9f9; border-radius: 4px; margin-top: 10px;"
		>
			<div class="skmt-security-field">
				<label for="rate_limit_attempts">
					<?php esc_html_e( 'Nombre de tentatives autorisées', 'studio-kyne-mini-tools' ); ?>
				</label>
				<input
					type="number"
					id="rate_limit_attempts"
					name="rate_limit_attempts"
					data-security-setting="authentication.rate_limit_attempts"
					value="<?php echo esc_attr( $auth['rate_limit_attempts'] ?? 5 ); ?>"
					min="1"
					max="20"
				/>
			</div>

			<div class="skmt-security-field">
				<label for="rate_limit_window">
					<?php esc_html_e( 'Fenêtre de temps (secondes)', 'studio-kyne-mini-tools' ); ?>
				</label>
				<input
					type="number"
					id="rate_limit_window"
					name="rate_limit_window"
					data-security-setting="authentication.rate_limit_window"
					value="<?php echo esc_attr( $auth['rate_limit_window'] ?? 900 ); ?>"
					min="60"
					step="60"
				/>
				<div class="skmt-security-option-description">
					<?php echo esc_html( sprintf( __( 'Actuellement: %d minutes', 'studio-kyne-mini-tools' ), (int) ( $auth['rate_limit_window'] ?? 900 ) / 60 ) ); ?>
				</div>
			</div>

			<div class="skmt-security-field">
				<label for="rate_limit_lockout">
					<?php esc_html_e( 'Durée du blocage (secondes)', 'studio-kyne-mini-tools' ); ?>
				</label>
				<input
					type="number"
					id="rate_limit_lockout"
					name="rate_limit_lockout"
					data-security-setting="authentication.rate_limit_lockout"
					value="<?php echo esc_attr( $auth['rate_limit_lockout'] ?? 1800 ); ?>"
					min="60"
					step="60"
				/>
				<div class="skmt-security-option-description">
				<?php echo esc_html( sprintf( __( 'Actuellement: %d minutes', 'studio-kyne-mini-tools' ), (int) ( ( $auth['rate_limit_lockout'] ?? 1800 ) / 60 ) ) ); ?>
				</div>
			</div>

			<div class="skmt-security-field">
				<label for="rate_limit_whitelist">
					<?php esc_html_e( 'IP whitelistées (une par ligne)', 'studio-kyne-mini-tools' ); ?>
				</label>
				<textarea
					id="rate_limit_whitelist"
					name="rate_limit_whitelist"
					data-security-setting="authentication.rate_limit_whitelist"
					data-is-array="true"
					rows="4"
					placeholder="192.168.1.1&#10;127.0.0.1"
				><?php echo esc_textarea( implode( "\n", $auth['rate_limit_whitelist'] ?? [] ) ); ?></textarea>
				<div class="skmt-security-option-description">
					<?php esc_html_e( 'Les IPs whitelistées ne seront jamais bloquées par le rate limiting.', 'studio-kyne-mini-tools' ); ?>
				</div>
			</div>
		</div>

		<!-- Disable Registration -->
		<div class="skmt-security-option">
			<label>
				<input
					type="checkbox"
					name="disable_registration"
					data-security-setting="authentication.disable_registration"
					data-security-toggle="disable_registration"
					<?php checked( $auth['disable_registration'] ?? false ); ?>
				/>
				<?php esc_html_e( 'Désactiver l\'inscription publique', 'studio-kyne-mini-tools' ); ?>
			</label>
			<div class="skmt-security-option-description">
				<?php esc_html_e( 'Empêcher les utilisateurs de s\'inscrire via le formulaire public.', 'studio-kyne-mini-tools' ); ?>
			</div>
		</div>
	</div>

	<!-- ============================================================
		 HARDENING
		 ============================================================ -->
	<div class="skmt-security-section">
		<h3><?php esc_html_e( 'Hardening', 'studio-kyne-mini-tools' ); ?></h3>

		<!-- Disable XML-RPC -->
		<div class="skmt-security-option">
			<label>
				<input
					type="checkbox"
					name="disable_xmlrpc"
					data-security-setting="hardening.disable_xmlrpc"
					data-security-toggle="disable_xmlrpc"
					<?php checked( $hardening['disable_xmlrpc'] ?? false ); ?>
				/>
				<?php esc_html_e( 'Désactiver XML-RPC', 'studio-kyne-mini-tools' ); ?>
			</label>
			<div class="skmt-security-option-description">
				<?php esc_html_e( 'Désactive l\'API XML-RPC (rarement utilisée, peut présenter des risques).', 'studio-kyne-mini-tools' ); ?>
			</div>
		</div>

		<!-- Prevent User Enumeration -->
		<div class="skmt-security-option">
			<label>
				<input
					type="checkbox"
					name="prevent_user_enum"
					data-security-setting="hardening.prevent_user_enum"
					data-security-toggle="prevent_user_enum"
					<?php checked( $hardening['prevent_user_enum'] ?? false ); ?>
				/>
				<?php esc_html_e( 'Empêcher l\'énumération des utilisateurs', 'studio-kyne-mini-tools' ); ?>
			</label>
			<div class="skmt-security-option-description">
				<?php esc_html_e( 'Bloque les requêtes ?author= et l\'accès REST aux utilisateurs.', 'studio-kyne-mini-tools' ); ?>
			</div>
		</div>

		<!-- Hide WordPress Version -->
		<div class="skmt-security-option">
			<label>
				<input
					type="checkbox"
					name="hide_wp_version"
					data-security-setting="hardening.hide_wp_version"
					data-security-toggle="hide_wp_version"
					<?php checked( $hardening['hide_wp_version'] ?? false ); ?>
				/>
				<?php esc_html_e( 'Masquer la version WordPress', 'studio-kyne-mini-tools' ); ?>
			</label>
			<div class="skmt-security-option-description">
				<?php esc_html_e( 'Retire la version WordPress des headers HTTP et du meta generator.', 'studio-kyne-mini-tools' ); ?>
			</div>
		</div>
	</div>

	<!-- ============================================================
		 LOGGING
		 ============================================================ -->
	<div class="skmt-security-section">
		<h3><?php esc_html_e( 'Journalisation', 'studio-kyne-mini-tools' ); ?></h3>

		<!-- Log Connections -->
		<div class="skmt-security-option">
			<label>
				<input
					type="checkbox"
					name="log_connections"
					data-security-setting="logging.log_connections"
					data-security-toggle="log_connections"
					<?php checked( $logging['log_connections'] ?? false ); ?>
				/>
				<?php esc_html_e( 'Enregistrer les connexions', 'studio-kyne-mini-tools' ); ?>
			</label>
			<div class="skmt-security-option-description">
				<?php esc_html_e( 'Log les tentatives de connexion (réussies et échouées) avec l\'IP.', 'studio-kyne-mini-tools' ); ?>
			</div>
		</div>

		<!-- Log User Actions -->
		<div class="skmt-security-option">
			<label>
				<input
					type="checkbox"
					name="log_user_actions"
					data-security-setting="logging.log_user_actions"
					data-security-toggle="log_user_actions"
					<?php checked( $logging['log_user_actions'] ?? false ); ?>
				/>
				<?php esc_html_e( 'Enregistrer les actions utilisateurs', 'studio-kyne-mini-tools' ); ?>
			</label>
			<div class="skmt-security-option-description">
				<?php esc_html_e( 'Log les créations et suppressions d\'utilisateurs.', 'studio-kyne-mini-tools' ); ?>
			</div>
		</div>

		<!-- Log Settings Changes -->
		<div class="skmt-security-option">
			<label>
				<input
					type="checkbox"
					name="log_settings_changes"
					data-security-setting="logging.log_settings_changes"
					data-security-toggle="log_settings_changes"
					<?php checked( $logging['log_settings_changes'] ?? false ); ?>
				/>
				<?php esc_html_e( 'Enregistrer les changements de configuration', 'studio-kyne-mini-tools' ); ?>
			</label>
			<div class="skmt-security-option-description">
				<?php esc_html_e( 'Log les modifications des settings du module Sécurité.', 'studio-kyne-mini-tools' ); ?>
			</div>
		</div>

		<!-- Log Retention -->
		<div class="skmt-security-option">
			<div class="skmt-security-field">
				<label for="log_retention_days">
					<?php esc_html_e( 'Rétention des logs (jours)', 'studio-kyne-mini-tools' ); ?>
				</label>
				<input
					type="number"
					id="log_retention_days"
					name="log_retention_days"
					data-security-setting="logging.log_retention_days"
					value="<?php echo esc_attr( $logging['log_retention_days'] ?? 30 ); ?>"
					min="1"
					max="365"
				/>
				<div class="skmt-security-option-description">
					<?php esc_html_e( 'Les logs plus anciens seront supprimés automatiquement.', 'studio-kyne-mini-tools' ); ?>
				</div>
			</div>
		</div>

		<!-- Warning about logging -->
		<div class="skmt-security-warning">
			<?php esc_html_e( '⚠️ La journalisation augmente la base de données. Utilisez avec modération sur les sites à fort trafic.', 'studio-kyne-mini-tools' ); ?>
		</div>
	</div>

	<!-- Save Button -->
	<div style="margin-top: 30px;">
		<button type="button" id="skmt-security-save-settings" class="button button-primary">
			<?php esc_html_e( 'Enregistrer les paramètres', 'studio-kyne-mini-tools' ); ?>
		</button>
	</div>

</form>
