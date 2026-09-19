<?php
/**
 * Écran du module Journal d'activité : liste filtrable, puis réglages.
 *
 * Variables disponibles (via module-settings.php):
 * @var string          $module_id       ID du module (activity_log)
 * @var array           $module          Infos du module
 * @var ModuleInterface $instance        Instance du module
 * @var array           $module_settings Settings actuels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StudioKyne\MiniTools\Modules\ActivityLog\Events;
use StudioKyne\MiniTools\Modules\ActivityLog\Store;

$excluded_groups = (array) ( $module_settings['excluded_groups'] ?? [] );
$excluded_roles  = (array) ( $module_settings['excluded_roles'] ?? [] );
$log_users       = Store::users();
?>

<form id="skmt-module-form" class="skmt-form skmt-module-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'skmt_save_settings', 'skmt_nonce' ); ?>
	<input type="hidden" name="action" value="skmt_save_settings">
	<input type="hidden" name="skmt_tab" value="<?php echo esc_attr( $tab ); ?>">

	<div class="skmt-tabs" role="tablist" data-skmt-tabs="activity_log" aria-label="<?php esc_attr_e( 'Sections du journal d\'activité', 'studio-kyne-mini-tools' ); ?>">
		<button type="button" class="skmt-tabs__tab is-active" role="tab" data-skmt-tab="log"><?php esc_html_e( 'Journal', 'studio-kyne-mini-tools' ); ?></button>
		<button type="button" class="skmt-tabs__tab" role="tab" data-skmt-tab="settings"><?php esc_html_e( 'Réglages', 'studio-kyne-mini-tools' ); ?></button>
	</div>

	<div class="skmt-module-form__scroll">

	<div class="skmt-tabs__panel" role="tabpanel" data-skmt-tabs-group="activity_log" data-skmt-tab-panel="log">

	<!-- ============================================================
		JOURNAL
		Les filtres n'ont pas d'attribut name : ils ne partent jamais avec
		le formulaire de réglages.
		============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Journal', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Les événements les plus récents d\'abord. Cliquez sur une ligne pour en voir le détail.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-al" id="skmt-al" data-nonce="<?php echo esc_attr( wp_create_nonce( 'skmt_admin_nonce' ) ); ?>">

				<div class="skmt-al__filters">
					<div class="skmt-search skmt-search--sm skmt-al__search">
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
						<input type="search" class="skmt-search__input" id="skmt-al-search"
							placeholder="<?php esc_attr_e( 'Objet, identifiant, IP…', 'studio-kyne-mini-tools' ); ?>"
							aria-label="<?php esc_attr_e( 'Rechercher dans le journal', 'studio-kyne-mini-tools' ); ?>">
					</div>

					<select class="skmt-select skmt-select--sm" id="skmt-al-user" aria-label="<?php esc_attr_e( 'Utilisateur', 'studio-kyne-mini-tools' ); ?>">
						<option value=""><?php esc_html_e( 'Tous les utilisateurs', 'studio-kyne-mini-tools' ); ?></option>
						<?php foreach ( $log_users as $log_user ) : ?>
							<option value="<?php echo esc_attr( (string) $log_user['user_id'] ); ?>"><?php echo esc_html( $log_user['user_login'] ); ?></option>
						<?php endforeach; ?>
					</select>

					<select class="skmt-select skmt-select--sm" id="skmt-al-type" aria-label="<?php esc_attr_e( 'Type d\'événement', 'studio-kyne-mini-tools' ); ?>">
						<option value=""><?php esc_html_e( 'Tous les événements', 'studio-kyne-mini-tools' ); ?></option>
						<?php foreach ( Events::groups() as $group_key => $group_label ) : ?>
							<optgroup label="<?php echo esc_attr( $group_label ); ?>">
								<option value="group:<?php echo esc_attr( $group_key ); ?>">
									<?php
									/* translators: %s: famille d'événements (Connexions, Contenus…). */
									echo esc_html( sprintf( __( '%s : tout', 'studio-kyne-mini-tools' ), $group_label ) );
									?>
								</option>
								<?php foreach ( Events::in_group( $group_key ) as $event_key ) : ?>
									<option value="event:<?php echo esc_attr( $event_key ); ?>"><?php echo esc_html( Events::label( $event_key ) ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>

					<input type="date" class="skmt-input skmt-input--sm" id="skmt-al-from" aria-label="<?php esc_attr_e( 'Depuis le', 'studio-kyne-mini-tools' ); ?>" data-skmt-tip="<?php esc_attr_e( 'Depuis le', 'studio-kyne-mini-tools' ); ?>">
					<input type="date" class="skmt-input skmt-input--sm" id="skmt-al-to" aria-label="<?php esc_attr_e( 'Jusqu\'au', 'studio-kyne-mini-tools' ); ?>" data-skmt-tip="<?php esc_attr_e( 'Jusqu\'au', 'studio-kyne-mini-tools' ); ?>">

					<div class="skmt-al__filter-actions">
						<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-al-reset"><?php esc_html_e( 'Réinitialiser', 'studio-kyne-mini-tools' ); ?></button>
						<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-al-export" data-skmt-tip="<?php esc_attr_e( 'Exporte les événements correspondant aux filtres', 'studio-kyne-mini-tools' ); ?>">
							<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/></svg>
							<?php esc_html_e( 'Exporter en CSV', 'studio-kyne-mini-tools' ); ?>
						</button>
					</div>
				</div>

				<div class="skmt-al__table-wrap">
					<table class="skmt-al__table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Date', 'studio-kyne-mini-tools' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Utilisateur', 'studio-kyne-mini-tools' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Événement', 'studio-kyne-mini-tools' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Objet', 'studio-kyne-mini-tools' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Adresse IP', 'studio-kyne-mini-tools' ); ?></th>
							</tr>
						</thead>
						<tbody id="skmt-al-rows">
							<tr><td colspan="5" class="skmt-al__state"><?php esc_html_e( 'Chargement…', 'studio-kyne-mini-tools' ); ?></td></tr>
						</tbody>
					</table>
				</div>

				<div class="skmt-al__footer">
					<span class="skmt-al__total" id="skmt-al-total"></span>
					<div class="skmt-al__pager">
						<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-al-prev" disabled aria-label="<?php esc_attr_e( 'Page précédente', 'studio-kyne-mini-tools' ); ?>" data-skmt-tip="<?php esc_attr_e( 'Page précédente', 'studio-kyne-mini-tools' ); ?>">
							<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
						</button>
						<span class="skmt-al__page" id="skmt-al-page"></span>
						<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-al-next" disabled aria-label="<?php esc_attr_e( 'Page suivante', 'studio-kyne-mini-tools' ); ?>" data-skmt-tip="<?php esc_attr_e( 'Page suivante', 'studio-kyne-mini-tools' ); ?>">
							<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	</div>

	<div class="skmt-tabs__panel" role="tabpanel" data-skmt-tabs-group="activity_log" data-skmt-tab-panel="settings" hidden>

	<!-- ============================================================
		CONSERVATION
		============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Conservation', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Une purge quotidienne supprime les événements trop anciens, puis les plus anciens au-delà du plafond. Exportez en CSV ce que vous voulez garder.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-form__row">
				<div class="skmt-form__group">
					<label for="skmt_al_retention_days" class="skmt-form__label"><?php esc_html_e( 'Durée de conservation (jours)', 'studio-kyne-mini-tools' ); ?></label>
					<input type="number" id="skmt_al_retention_days" name="skmt_module_settings[retention_days]" class="skmt-input skmt-input--sm"
						value="<?php echo esc_attr( (string) $module_settings['retention_days'] ); ?>" min="1" max="3650">
				</div>
				<div class="skmt-form__group">
					<label for="skmt_al_max_rows" class="skmt-form__label"><?php esc_html_e( 'Nombre maximal d\'événements', 'studio-kyne-mini-tools' ); ?></label>
					<input type="number" id="skmt_al_max_rows" name="skmt_module_settings[max_rows]" class="skmt-input skmt-input--sm"
						value="<?php echo esc_attr( (string) $module_settings['max_rows'] ); ?>" min="100" max="1000000" step="100">
				</div>
			</div>

			<div class="skmt-option">
				<div class="skmt-option__content">
					<label for="skmt_al_anonymize_ip" class="skmt-option__label">
						<?php esc_html_e( 'Anonymiser les adresses IP', 'studio-kyne-mini-tools' ); ?>
						<?php echo $this->render_help_tip( __( 'Ne s\'applique qu\'aux nouveaux événements. L\'adresse masquée ne permet plus d\'identifier un poste précis face à une intrusion.', 'studio-kyne-mini-tools' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</label>
					<p class="skmt-option__desc"><?php esc_html_e( 'Masque la fin de l\'adresse (dernier octet en IPv4) avant l\'enregistrement, comme les outils de confidentialité de WordPress.', 'studio-kyne-mini-tools' ); ?></p>
				</div>
				<div class="skmt-option__control">
					<label class="skmt-toggle">
						<input type="checkbox" id="skmt_al_anonymize_ip" name="skmt_module_settings[anonymize_ip]" value="1" <?php checked( ! empty( $module_settings['anonymize_ip'] ) ); ?>>
						<span class="skmt-toggle__slider"></span>
					</label>
				</div>
			</div>
		</div>
	</div>

	<div class="skmt-divider"></div>

	<!-- ============================================================
		PÉRIMÈTRE
		============================================================ -->
	<div class="skmt-section">
		<div class="skmt-section__header">
			<h2 class="skmt-section__title"><?php esc_html_e( 'Ce qui est journalisé', 'studio-kyne-mini-tools' ); ?></h2>
			<p class="skmt-section__desc"><?php esc_html_e( 'Décochez une famille ou un rôle pour ne plus enregistrer ses événements. Les événements déjà enregistrés restent.', 'studio-kyne-mini-tools' ); ?></p>
		</div>
		<div class="skmt-section__content">
			<div class="skmt-al__checks-title"><?php esc_html_e( 'Familles d\'événements', 'studio-kyne-mini-tools' ); ?></div>
			<div class="skmt-al__checks">
				<?php foreach ( Events::groups() as $group_key => $group_label ) : ?>
					<label class="skmt-al__check">
						<span class="skmt-toggle">
							<input type="checkbox" name="skmt_module_settings[tracked_groups][]" value="<?php echo esc_attr( $group_key ); ?>" <?php checked( ! in_array( $group_key, $excluded_groups, true ) ); ?>>
							<span class="skmt-toggle__slider"></span>
						</span>
						<span class="skmt-al__check-name"><?php echo esc_html( $group_label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="skmt-al__checks-title">
				<?php esc_html_e( 'Rôles', 'studio-kyne-mini-tools' ); ?>
				<?php echo $this->render_help_tip( __( 'Les échecs de connexion sont toujours enregistrés, quel que soit le compte visé : exclure les administrateurs ne doit pas masquer les attaques contre eux.', 'studio-kyne-mini-tools' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="skmt-al__checks">
				<?php foreach ( wp_roles()->get_names() as $role_slug => $role_name ) : ?>
					<label class="skmt-al__check">
						<span class="skmt-toggle">
							<input type="checkbox" name="skmt_module_settings[tracked_roles][]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( ! in_array( $role_slug, $excluded_roles, true ) ); ?>>
							<span class="skmt-toggle__slider"></span>
						</span>
						<span class="skmt-al__check-name"><?php echo esc_html( translate_user_role( $role_name ) ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	</div>

	</div><!-- .skmt-module-form__scroll -->

</form>

<!-- MODALE DE DÉTAIL (contenu généré en JS) -->
<div class="skmt-modal-overlay" id="skmt-al-detail-modal" role="dialog" aria-modal="true" aria-labelledby="skmt-al-detail-title">
	<div class="skmt-modal skmt-modal--lg">
		<div class="skmt-modal__header">
			<h3 id="skmt-al-detail-title" class="skmt-modal__title"></h3>
		</div>
		<div class="skmt-modal__body">
			<dl class="skmt-al__detail" id="skmt-al-detail-body"></dl>
		</div>
		<div class="skmt-modal__footer">
			<a class="skmt-btn skmt-btn--sm skmt-btn--secondary" id="skmt-al-detail-link" href="#" hidden><?php esc_html_e( 'Ouvrir l\'objet', 'studio-kyne-mini-tools' ); ?></a>
			<button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary skmt-modal-close"><?php esc_html_e( 'Fermer', 'studio-kyne-mini-tools' ); ?></button>
		</div>
	</div>
</div>
