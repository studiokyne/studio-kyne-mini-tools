<?php
/**
 * Écran du module SMTP : serveur, expéditeur, test, journal des mails.
 *
 * Variables disponibles (via module-settings.php):
 * @var string          $module_id       ID du module (smtp)
 * @var array           $module          Infos du module
 * @var ModuleInterface $instance        Instance du module
 * @var array           $module_settings Settings actuels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StudioKyne\MiniTools\Modules\Smtp\Crypto;
use StudioKyne\MiniTools\Modules\Smtp\Mailer;
use StudioKyne\MiniTools\Modules\Smtp\Providers;

$smtp_user_const = defined( 'SKMT_SMTP_USER' );
$smtp_pass_const = defined( 'SKMT_SMTP_PASSWORD' );
$smtp_has_pass   = $smtp_pass_const || '' !== (string) get_option( Mailer::PASSWORD_OPTION, '' );
$smtp_pass_ok    = null !== Mailer::password();
$smtp_override   = Mailer::wp_mail_override();
$smtp_ready      = ( new Mailer( $module_settings ) )->smtp_ready();
$smtp_encryption = (string) $module_settings['encryption'];
$smtp_admin_mail = (string) wp_get_current_user()->user_email;
$smtp_providers  = Providers::all();
$smtp_provider   = (string) $module_settings['provider'];
?>

<form id="skmt-module-form" class="skmt-form skmt-module-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'skmt_save_settings', 'skmt_nonce' ); ?>
	<input type="hidden" name="action" value="skmt_save_settings">
	<input type="hidden" name="skmt_tab" value="<?php echo esc_attr( $tab ); ?>">

	<div class="skmt-tabs" role="tablist" data-skmt-tabs="smtp" aria-label="<?php esc_attr_e( 'Sections du module SMTP', 'studio-kyne-mini-tools' ); ?>">
		<button type="button" class="skmt-tabs__tab is-active" role="tab" data-skmt-tab="settings"><?php esc_html_e( 'Réglages', 'studio-kyne-mini-tools' ); ?></button>
		<button type="button" class="skmt-tabs__tab" role="tab" data-skmt-tab="test"><?php esc_html_e( 'Test', 'studio-kyne-mini-tools' ); ?></button>
		<button type="button" class="skmt-tabs__tab" role="tab" data-skmt-tab="log"><?php esc_html_e( 'Journal', 'studio-kyne-mini-tools' ); ?></button>
	</div>

	<div class="skmt-module-form__scroll">

	<?php if ( '' !== $smtp_override ) : ?>
		<div class="skmt-notice skmt-notice--error">
			<?php
			/* translators: %s: chemin du fichier qui redéfinit wp_mail(). */
			echo esc_html( sprintf( __( 'Une autre extension remplace la fonction d\'envoi de WordPress (%s). Les réglages ci-dessous risquent de ne pas s\'appliquer : désactivez l\'autre extension SMTP.', 'studio-kyne-mini-tools' ), $smtp_override ) );
			?>
		</div>
	<?php endif; ?>

	<?php if ( ! Crypto::available() && ! $smtp_pass_const ) : ?>
		<div class="skmt-notice skmt-notice--error">
			<?php esc_html_e( 'L\'extension PHP OpenSSL est absente : le mot de passe ne peut pas être chiffré et ne sera pas enregistré. Définissez-le dans wp-config.php avec la constante SKMT_SMTP_PASSWORD.', 'studio-kyne-mini-tools' ); ?>
		</div>
	<?php elseif ( ! $smtp_pass_ok ) : ?>
		<div class="skmt-notice skmt-notice--error">
			<?php esc_html_e( 'Le mot de passe enregistré ne se déchiffre plus (les clés de wp-config.php ont changé, après une migration par exemple). Saisissez-le à nouveau.', 'studio-kyne-mini-tools' ); ?>
		</div>
	<?php endif; ?>

	<div class="skmt-tabs__panel" role="tabpanel" data-skmt-tabs-group="smtp" data-skmt-tab-panel="settings">

	<!-- ============================================================
		SERVEUR SMTP
		============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Serveur SMTP', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc">
				<?php
				if ( $smtp_ready ) {
					/* translators: 1: hôte SMTP, 2: port. */
					echo esc_html( sprintf( __( 'Les mails du site partent par %1$s, port %2$s.', 'studio-kyne-mini-tools' ), $module_settings['host'], $module_settings['port'] ) );
				} else {
					esc_html_e( 'Les mails partent aujourd\'hui par la fonction mail() de PHP, que beaucoup de fournisseurs classent en indésirable. Un serveur SMTP authentifié les fait passer.', 'studio-kyne-mini-tools' );
				}
				?>
			</p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_sm_enabled" class="skmt-option__label"><?php esc_html_e( 'Envoyer par SMTP', 'studio-kyne-mini-tools' ); ?></label>
					<p class="skmt-option__desc"><?php esc_html_e( 'Sans hôte renseigné, l\'envoi reste sur mail() même activé.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" id="skmt_sm_enabled" name="skmt_module_settings[smtp_enabled]" value="1" <?php checked( ! empty( $module_settings['smtp_enabled'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<div class="skmt-form__group skmt-sm__provider">
				<label for="skmt_sm_provider" class="skmt-form__label"><?php esc_html_e( 'Fournisseur', 'studio-kyne-mini-tools' ); ?></label>
				<select id="skmt_sm_provider" name="skmt_module_settings[provider]" class="skmt-select skmt-select--sm">
					<option value="<?php echo esc_attr( Providers::CUSTOM ); ?>" <?php selected( $smtp_provider, Providers::CUSTOM ); ?>><?php esc_html_e( 'Serveur personnalisé', 'studio-kyne-mini-tools' ); ?></option>
					<?php foreach ( $smtp_providers as $provider_key => $provider ) : ?>
						<option value="<?php echo esc_attr( $provider_key ); ?>" <?php selected( $smtp_provider, $provider_key ); ?>><?php echo esc_html( $provider['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="skmt-form__help" id="skmt-sm-provider-hint"><?php echo esc_html( $smtp_providers[ $smtp_provider ]['hint'] ?? __( 'Choisir un fournisseur pré-remplit l\'hôte, le port et le chiffrement ; tout reste modifiable.', 'studio-kyne-mini-tools' ) ); ?></p>
			</div>

			<div class="skmt-form__row">
				<div class="skmt-form__group skmt-sm__wide">
					<label for="skmt_sm_host" class="skmt-form__label"><?php esc_html_e( 'Hôte', 'studio-kyne-mini-tools' ); ?></label>
					<input type="text" id="skmt_sm_host" name="skmt_module_settings[host]" class="skmt-input skmt-input--sm"
						value="<?php echo esc_attr( (string) $module_settings['host'] ); ?>" placeholder="smtp.example.com" autocomplete="off" spellcheck="false">
				</div>
				<div class="skmt-form__group">
					<label for="skmt_sm_encryption" class="skmt-form__label"><?php esc_html_e( 'Chiffrement', 'studio-kyne-mini-tools' ); ?></label>
					<select id="skmt_sm_encryption" name="skmt_module_settings[encryption]" class="skmt-select skmt-select--sm">
						<option value="tls" <?php selected( $smtp_encryption, 'tls' ); ?>><?php esc_html_e( 'STARTTLS (port 587)', 'studio-kyne-mini-tools' ); ?></option>
						<option value="ssl" <?php selected( $smtp_encryption, 'ssl' ); ?>><?php esc_html_e( 'SSL/TLS (port 465)', 'studio-kyne-mini-tools' ); ?></option>
						<option value="none" <?php selected( $smtp_encryption, 'none' ); ?>><?php esc_html_e( 'Aucun (port 25)', 'studio-kyne-mini-tools' ); ?></option>
					</select>
				</div>
				<div class="skmt-form__group skmt-sm__port">
					<label for="skmt_sm_port" class="skmt-form__label"><?php esc_html_e( 'Port', 'studio-kyne-mini-tools' ); ?></label>
					<input type="number" id="skmt_sm_port" name="skmt_module_settings[port]" class="skmt-input skmt-input--sm"
						value="<?php echo esc_attr( (string) $module_settings['port'] ); ?>" min="1" max="65535">
				</div>
			</div>

			<div class="skmt-option" id="skmt-sm-autotls-row" <?php echo 'none' === $smtp_encryption ? '' : 'hidden'; ?>>
				<div class="skmt-option__content">
					<label for="skmt_sm_auto_tls" class="skmt-option__label"><?php esc_html_e( 'TLS automatique', 'studio-kyne-mini-tools' ); ?></label>
					<p class="skmt-option__desc"><?php esc_html_e( 'Passe en STARTTLS si le serveur le propose. À couper seulement pour un serveur au certificat invalide.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" id="skmt_sm_auto_tls" name="skmt_module_settings[auto_tls]" value="1" <?php checked( ! empty( $module_settings['auto_tls'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_sm_auth" class="skmt-option__label"><?php esc_html_e( 'Authentification', 'studio-kyne-mini-tools' ); ?></label>
					<p class="skmt-option__desc"><?php esc_html_e( 'Presque tous les serveurs l\'exigent.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" id="skmt_sm_auth" name="skmt_module_settings[auth]" value="1" <?php checked( ! empty( $module_settings['auth'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<div class="skmt-form__row" id="skmt-sm-credentials" <?php echo empty( $module_settings['auth'] ) ? 'hidden' : ''; ?>>
				<div class="skmt-form__group skmt-sm__wide">
					<label for="skmt_sm_username" class="skmt-form__label">
						<?php esc_html_e( 'Identifiant', 'studio-kyne-mini-tools' ); ?>
						<?php if ( $smtp_user_const ) : ?>
							<?php echo $this->render_help_tip( __( 'Défini dans wp-config.php par SKMT_SMTP_USER.', 'studio-kyne-mini-tools' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</label>
					<input type="text" id="skmt_sm_username" name="skmt_module_settings[username]" class="skmt-input skmt-input--sm"
						value="<?php echo esc_attr( $smtp_user_const ? (string) SKMT_SMTP_USER : (string) $module_settings['username'] ); ?>"
						autocomplete="off" spellcheck="false" <?php disabled( $smtp_user_const ); ?>>
				</div>
				<div class="skmt-form__group skmt-sm__wide">
					<label for="skmt_sm_password" class="skmt-form__label">
						<?php esc_html_e( 'Mot de passe', 'studio-kyne-mini-tools' ); ?>
						<?php
						$smtp_pass_tip = $smtp_pass_const
							? __( 'Défini dans wp-config.php par SKMT_SMTP_PASSWORD.', 'studio-kyne-mini-tools' )
							: __( 'Chiffré en base avec les clés de wp-config.php, jamais réaffiché ni exporté. Laissez vide pour garder le mot de passe enregistré. Pour ne pas le stocker en base, définissez SKMT_SMTP_PASSWORD dans wp-config.php.', 'studio-kyne-mini-tools' );
						echo $this->render_help_tip( $smtp_pass_tip ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</label>
					<input type="password" id="skmt_sm_password" name="skmt_module_settings[password]" class="skmt-input skmt-input--sm"
						value="" autocomplete="new-password"
						placeholder="<?php echo $smtp_has_pass ? esc_attr__( 'Enregistré — laisser vide pour conserver', 'studio-kyne-mini-tools' ) : ''; ?>"
						<?php disabled( $smtp_pass_const ); ?>>
				</div>
			</div>
		</div>
	</div>

	<div class="skmt-divider"></div>

	<!-- ============================================================
		EXPÉDITEUR
		============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Expéditeur', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc">
				<?php
				/* translators: %s: adresse d'expédition par défaut de WordPress. */
				echo esc_html( sprintf( __( 'Remplace l\'expéditeur par défaut de WordPress (%s). Utilisez une adresse du domaine autorisé par le serveur SMTP, sinon les mails échouent au contrôle SPF/DMARC.', 'studio-kyne-mini-tools' ), Mailer::wp_default_from_email() ) );
				?>
			</p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-form__row">
				<div class="skmt-form__group skmt-sm__wide">
					<label for="skmt_sm_from_email" class="skmt-form__label"><?php esc_html_e( 'Adresse d\'expédition', 'studio-kyne-mini-tools' ); ?></label>
					<input type="email" id="skmt_sm_from_email" name="skmt_module_settings[from_email]" class="skmt-input skmt-input--sm"
						value="<?php echo esc_attr( (string) $module_settings['from_email'] ); ?>" placeholder="contact@example.com">
				</div>
				<div class="skmt-form__group skmt-sm__wide">
					<label for="skmt_sm_from_name" class="skmt-form__label"><?php esc_html_e( 'Nom d\'expéditeur', 'studio-kyne-mini-tools' ); ?></label>
					<input type="text" id="skmt_sm_from_name" name="skmt_module_settings[from_name]" class="skmt-input skmt-input--sm"
						value="<?php echo esc_attr( (string) $module_settings['from_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				</div>
			</div>

			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_sm_force_email" class="skmt-option__label"><?php esc_html_e( 'Forcer l\'adresse d\'expédition', 'studio-kyne-mini-tools' ); ?></label>
					<p class="skmt-option__desc"><?php esc_html_e( 'Remplace aussi l\'adresse choisie par une extension (formulaire de contact, boutique). Sans ça, seule l\'adresse par défaut de WordPress est remplacée.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" id="skmt_sm_force_email" name="skmt_module_settings[force_from_email]" value="1" <?php checked( ! empty( $module_settings['force_from_email'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_sm_force_name" class="skmt-option__label"><?php esc_html_e( 'Forcer le nom d\'expéditeur', 'studio-kyne-mini-tools' ); ?></label>
					<p class="skmt-option__desc"><?php esc_html_e( 'Sans ça, seul le nom « WordPress » est remplacé.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" id="skmt_sm_force_name" name="skmt_module_settings[force_from_name]" value="1" <?php checked( ! empty( $module_settings['force_from_name'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_sm_return_path" class="skmt-option__label">
						<?php esc_html_e( 'Return-Path sur l\'adresse d\'expédition', 'studio-kyne-mini-tools' ); ?>
						<?php echo $this->render_help_tip( __( 'Adresse d\'enveloppe : c\'est elle que vérifie SPF. Même quand une extension garde son propre expéditeur, l\'enveloppe reste sur l\'adresse configurée ci-dessus, que le serveur SMTP accepte.', 'studio-kyne-mini-tools' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</label>
					<p class="skmt-option__desc"><?php esc_html_e( 'Les avis de non-distribution arrivent à l\'adresse d\'expédition configurée.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" id="skmt_sm_return_path" name="skmt_module_settings[set_return_path]" value="1" <?php checked( ! empty( $module_settings['set_return_path'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>
		</div>
	</div>

	</div><!-- panneau Réglages -->

	<div class="skmt-tabs__panel" role="tabpanel" data-skmt-tabs-group="smtp" data-skmt-tab-panel="test" hidden>

	<!-- ============================================================
		MAIL DE TEST
		Champ sans attribut name : il ne part pas avec les réglages.
		============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Mail de test', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Envoie un mail avec les réglages enregistrés : enregistrez d\'abord vos modifications. En cas d\'échec, l\'échange avec le serveur s\'affiche, identifiants masqués.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-sm__test">
				<input type="email" id="skmt-sm-test-to" class="skmt-input skmt-input--sm" value="<?php echo esc_attr( $smtp_admin_mail ); ?>"
					aria-label="<?php esc_attr_e( 'Destinataire du mail de test', 'studio-kyne-mini-tools' ); ?>">
				<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="skmt-sm-test-send"><?php esc_html_e( 'Envoyer un mail de test', 'studio-kyne-mini-tools' ); ?></button>
			</div>
			<div class="skmt-sm__test-result" id="skmt-sm-test-result" hidden>
				<p class="skmt-notice" id="skmt-sm-test-message"></p>
				<pre class="skmt-sm__transcript" id="skmt-sm-test-transcript" hidden></pre>
			</div>
		</div>
	</div>

	</div><!-- panneau Test -->

	<div class="skmt-tabs__panel" role="tabpanel" data-skmt-tabs-group="smtp" data-skmt-tab-panel="log" hidden>

	<!-- ============================================================
		JOURNAL
		Filtres sans attribut name : ils ne partent pas avec les réglages.
		============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Journal des mails', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc">
				<?php
				if ( empty( $module_settings['log_enabled'] ) ) {
					esc_html_e( 'Journalisation désactivée : les mails envoyés ne sont plus enregistrés. Les mails déjà journalisés restent consultables.', 'studio-kyne-mini-tools' );
				} else {
					esc_html_e( 'Chaque mail envoyé par le site, réussi ou non. Cliquez sur une ligne pour voir le message et le renvoyer.', 'studio-kyne-mini-tools' );
				}
				?>
			</p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-sm" id="skmt-sm" data-nonce="<?php echo esc_attr( wp_create_nonce( 'skmt_admin_nonce' ) ); ?>">

				<div class="skmt-sm__filters">
					<div class="skmt-search skmt-search--sm skmt-sm__search">
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
						<input type="search" class="skmt-search__input" id="skmt-sm-search"
							placeholder="<?php esc_attr_e( 'Objet, destinataire, expéditeur…', 'studio-kyne-mini-tools' ); ?>"
							aria-label="<?php esc_attr_e( 'Rechercher dans le journal des mails', 'studio-kyne-mini-tools' ); ?>">
					</div>

					<select class="skmt-select skmt-select--sm" id="skmt-sm-status" aria-label="<?php esc_attr_e( 'Statut', 'studio-kyne-mini-tools' ); ?>">
						<option value=""><?php esc_html_e( 'Tous les statuts', 'studio-kyne-mini-tools' ); ?></option>
						<option value="sent"><?php esc_html_e( 'Envoyés', 'studio-kyne-mini-tools' ); ?></option>
						<option value="failed"><?php esc_html_e( 'Échecs', 'studio-kyne-mini-tools' ); ?></option>
					</select>

					<input type="date" class="skmt-input skmt-input--sm" id="skmt-sm-from" aria-label="<?php esc_attr_e( 'Depuis le', 'studio-kyne-mini-tools' ); ?>" data-skmt-tip="<?php esc_attr_e( 'Depuis le', 'studio-kyne-mini-tools' ); ?>">
					<input type="date" class="skmt-input skmt-input--sm" id="skmt-sm-to" aria-label="<?php esc_attr_e( 'Jusqu\'au', 'studio-kyne-mini-tools' ); ?>" data-skmt-tip="<?php esc_attr_e( 'Jusqu\'au', 'studio-kyne-mini-tools' ); ?>">

					<div class="skmt-sm__filter-actions">
						<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-sm-reset"><?php esc_html_e( 'Réinitialiser', 'studio-kyne-mini-tools' ); ?></button>
						<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--danger" id="skmt-sm-clear"><?php esc_html_e( 'Vider le journal', 'studio-kyne-mini-tools' ); ?></button>
					</div>
				</div>

				<div class="skmt-sm__table-wrap">
					<table class="skmt-sm__table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Date', 'studio-kyne-mini-tools' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Statut', 'studio-kyne-mini-tools' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Destinataire', 'studio-kyne-mini-tools' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Objet', 'studio-kyne-mini-tools' ); ?></th>
							</tr>
						</thead>
						<tbody id="skmt-sm-rows">
							<tr><td colspan="4" class="skmt-sm__state"><?php esc_html_e( 'Chargement…', 'studio-kyne-mini-tools' ); ?></td></tr>
						</tbody>
					</table>
				</div>

				<div class="skmt-sm__footer">
					<span id="skmt-sm-total"></span>
					<div class="skmt-sm__pager">
						<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-sm-prev" disabled aria-label="<?php esc_attr_e( 'Page précédente', 'studio-kyne-mini-tools' ); ?>" data-skmt-tip="<?php esc_attr_e( 'Page précédente', 'studio-kyne-mini-tools' ); ?>">
							<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
						</button>
						<span id="skmt-sm-page"></span>
						<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-sm-next" disabled aria-label="<?php esc_attr_e( 'Page suivante', 'studio-kyne-mini-tools' ); ?>" data-skmt-tip="<?php esc_attr_e( 'Page suivante', 'studio-kyne-mini-tools' ); ?>">
							<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="skmt-divider"></div>

	<!-- ============================================================
		CONSERVATION
		============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Conservation', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Le journal garde le contenu complet des mails, liens de réinitialisation de mot de passe compris : ne le conservez pas plus que nécessaire. Une purge quotidienne supprime les mails trop anciens, puis les plus anciens au-delà du plafond.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_sm_log_enabled" class="skmt-option__label"><?php esc_html_e( 'Journaliser les mails', 'studio-kyne-mini-tools' ); ?></label>
					<p class="skmt-option__desc"><?php esc_html_e( 'Enregistre chaque mail envoyé par le site, avec son statut et l\'erreur éventuelle.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" id="skmt_sm_log_enabled" name="skmt_module_settings[log_enabled]" value="1" <?php checked( ! empty( $module_settings['log_enabled'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>

			<div class="skmt-form__row">
				<div class="skmt-form__group">
					<label for="skmt_sm_retention_days" class="skmt-form__label"><?php esc_html_e( 'Durée de conservation (jours)', 'studio-kyne-mini-tools' ); ?></label>
					<input type="number" id="skmt_sm_retention_days" name="skmt_module_settings[log_retention_days]" class="skmt-input skmt-input--sm"
						value="<?php echo esc_attr( (string) $module_settings['log_retention_days'] ); ?>" min="1" max="3650">
				</div>
				<div class="skmt-form__group">
					<label for="skmt_sm_max_rows" class="skmt-form__label"><?php esc_html_e( 'Nombre maximal de mails', 'studio-kyne-mini-tools' ); ?></label>
					<input type="number" id="skmt_sm_max_rows" name="skmt_module_settings[log_max_rows]" class="skmt-input skmt-input--sm"
						value="<?php echo esc_attr( (string) $module_settings['log_max_rows'] ); ?>" min="100" max="1000000" step="100">
				</div>
			</div>
		</div>
	</div>

	</div><!-- panneau Journal -->

	</div><!-- .skmt-module-form__scroll -->

</form>

<!-- MODALE DE DÉTAIL (contenu généré en JS) -->
<div class="skmt-modal-overlay" id="skmt-sm-detail-modal" role="dialog" aria-modal="true" aria-labelledby="skmt-sm-detail-title">
	<div class="skmt-modal skmt-modal--lg">
		<div class="skmt-modal__header">
			<h3 id="skmt-sm-detail-title" class="skmt-modal__title"></h3>
		</div>
		<div class="skmt-modal__body">
			<dl class="skmt-sm__detail" id="skmt-sm-detail-meta"></dl>
			<p class="skmt-notice skmt-notice--error" id="skmt-sm-detail-error" hidden></p>
			<!-- sandbox vide : ni script, ni formulaire, ni même origine. Le
				corps d'un mail vient de n'importe qui (formulaire de contact). -->
			<iframe class="skmt-sm__preview" id="skmt-sm-detail-html" sandbox="" referrerpolicy="no-referrer" title="<?php esc_attr_e( 'Aperçu du message', 'studio-kyne-mini-tools' ); ?>" hidden></iframe>
			<pre class="skmt-sm__preview skmt-sm__preview--text" id="skmt-sm-detail-text" hidden></pre>
			<details class="skmt-sm__headers" id="skmt-sm-detail-headers-wrap" hidden>
				<summary><?php esc_html_e( 'En-têtes transmis', 'studio-kyne-mini-tools' ); ?></summary>
				<pre id="skmt-sm-detail-headers"></pre>
			</details>
		</div>
		<div class="skmt-modal__footer">
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary skmt-modal-close"><?php esc_html_e( 'Fermer', 'studio-kyne-mini-tools' ); ?></button>
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="skmt-sm-detail-resend"><?php esc_html_e( 'Renvoyer', 'studio-kyne-mini-tools' ); ?></button>
		</div>
	</div>
</div>
