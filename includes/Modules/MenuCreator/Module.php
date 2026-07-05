<?php
namespace StudioKyne\MiniTools\Modules\MenuCreator;

use StudioKyne\MiniTools\Core\AbstractModule;
use StudioKyne\MiniTools\Modules\WhiteLabel\MenuProfileManager;

/**
 * Module Créateur de menu — gestion des profils de navigation et application aux utilisateurs.
 */
class Module extends AbstractModule {

	/**
	 * Nombre maximal d'entrées de menu persistées par niveau (garde-fou payload).
	 */
	private const MAX_ITEMS = 300;

	/**
	 * Copie du menu WP admin AVANT toute personnalisation par ce module
	 * (séparateurs injectés, liens custom ajoutés, items masqués retirés).
	 * L'éditeur doit présenter à l'utilisateur le menu WP d'origine, pas la
	 * version déjà transformée — sinon nos propres séparateurs / liens custom
	 * y réapparaissent en double. Capturé au début de apply_menu_visibility.
	 *
	 * @var array<int, mixed>|null
	 */
	private static $pristine_menu = null;

	/**
	 * @var array<string, mixed>|null
	 */
	private static $pristine_submenu = null;

	public function init(): void {
		// Moteur d'application des menus personnalisés
		$has_active = ! empty( array_filter(
			MenuProfileManager::get_all(),
			fn( $p ) => ( $p['status'] ?? '' ) === 'active'
		) );

		if ( $has_active ) {
			// N'active l'ordre custom que si l'utilisateur courant a réellement
			// un profil actif (sinon on force tout le monde dans le chemin
			// custom_menu_order pour rien).
			add_filter( 'custom_menu_order', [ $this, 'maybe_enable_custom_order' ] );
			add_filter( 'menu_order',  [ $this, 'apply_menu_order' ],      9999 );
			add_action( 'admin_menu',  [ $this, 'apply_menu_visibility' ], 9999 );
			add_action( 'admin_head',  [ $this, 'inject_menu_icon_overrides' ] );
			add_action( 'admin_head',  [ $this, 'inject_custom_link_targets' ] );
		}

		// Uniformise l'opacité des icônes de menu (natives ET personnalisées) :
		// WP atténue par défaut #adminmenu .wp-menu-image img à 60% tant que
		// l'item n'est pas survolé/actif. Indépendant d'un profil actif, pour
		// que même les icônes natives (ex. l'icône du plugin lui-même) en profitent.
		add_action( 'admin_head', [ $this, 'inject_global_icon_opacity_fix' ] );

		// AJAX endpoints
		add_action( 'wp_ajax_skmt_wl_save_profile',      [ $this, 'ajax_save_profile' ] );
		add_action( 'wp_ajax_skmt_wl_delete_profile',    [ $this, 'ajax_delete_profile' ] );
		add_action( 'wp_ajax_skmt_wl_duplicate_profile', [ $this, 'ajax_duplicate_profile' ] );
		add_action( 'wp_ajax_skmt_wl_search_users',      [ $this, 'ajax_search_users' ] );

		// Médiathèque WP pour le picker d'icônes
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_media' ] );
	}

	/* ================================================================
	 * MOTEUR DE MENU
	 * ================================================================ */

	/**
	 * N'active le tri de menu personnalisé que pour un utilisateur réellement
	 * ciblé par un profil actif ; laisse la valeur des autres filtres intacte
	 * sinon.
	 *
	 * @param bool $enabled Valeur courante du filtre custom_menu_order.
	 */
	public function maybe_enable_custom_order( $enabled ): bool {
		if ( MenuProfileManager::get_active_for_user( get_current_user_id() ) ) {
			return true;
		}
		return (bool) $enabled;
	}

	public function apply_menu_order( array $menu_order ): array {
		$profile = MenuProfileManager::get_active_for_user( get_current_user_id() );
		if ( ! $profile || empty( $profile['items'] ) ) {
			return $menu_order;
		}

		$slugs = [];
		foreach ( $profile['items'] as $item ) {
			$type = $item['type'] ?? 'wp_item';

			// Un lien personnalisé est enregistré dans $menu par add_menu_page()
			// sous le slug que WordPress dérive de l'URL, PAS l'URL brute :
			// add_menu_page applique plugin_basename() sur le menu_slug reçu.
			// Il faut reproduire exactement la même transformation ici, sinon
			// le slug ne correspond à aucune entrée de $menu_order et le lien
			// retombe dans "remaining" (donc tout en bas du menu).
			if ( 'custom_link' === $type ) {
				if ( ! empty( $item['url'] ) ) {
					$slugs[] = $this->custom_link_slug( $item['url'] );
				}
				continue;
			}

			if ( ! empty( $item['slug'] ) ) {
				$slugs[] = $item['slug'];
			}
		}

		$remaining = array_values( array_diff( $menu_order, $slugs ) );
		return array_values( array_merge( $slugs, $remaining ) );
	}

	public function apply_menu_visibility(): void {
		$profile = MenuProfileManager::get_active_for_user( get_current_user_id() );
		if ( ! $profile || empty( $profile['items'] ) ) {
			return;
		}

		global $menu, $submenu;

		// Instantané du menu WP pristine, avant nos modifications : consommé
		// par get_admin_js_data() pour alimenter l'éditeur avec le vrai menu
		// WP (et non la version déjà personnalisée).
		if ( null === self::$pristine_menu ) {
			self::$pristine_menu    = is_array( $menu ) ? $menu : [];
			self::$pristine_submenu = is_array( $submenu ) ? $submenu : [];
		}

		$next_position = 9000;

		foreach ( $profile['items'] as $item ) {
			$type = $item['type'] ?? 'wp_item';
			$slug = $item['slug'] ?? '';

			if ( 'separator' === $type ) {
				if ( ! empty( $slug ) && is_array( $menu ) ) {
					// Les clés de $menu doivent rester des entiers (WordPress les
					// traite comme telles) : on cherche le prochain slot entier
					// libre plutôt que d'incrémenter en float, qui serait
					// silencieusement tronqué par PHP (et provoque des collisions).
					while ( isset( $menu[ $next_position ] ) ) {
						$next_position++;
					}
					$menu[ $next_position ] = [ '', 'read', $slug, '', 'wp-menu-separator' ];
					$next_position++;
				}
				continue;
			}

			if ( ! ( $item['visible'] ?? true ) && ! empty( $slug ) ) {
				remove_menu_page( $slug );
				continue;
			}

			if ( 'custom_link' === $type && ! empty( $item['url'] ) ) {
				$label    = sanitize_text_field( $item['label'] ?? __( 'Lien', 'studio-kyne-mini-tools' ) );
				$icon_url = $this->resolve_native_icon_url( $item['icon'] ?? null );
				add_menu_page( $label, $label, 'read', esc_url_raw( $item['url'] ), '', $icon_url, 999 );
				continue;
			}

			if ( 'wp_item' === $type && ! empty( $slug ) && is_array( $menu ) ) {
				foreach ( $menu as $key => $menu_item ) {
					if ( ! is_array( $menu_item ) || ( $menu_item[2] ?? '' ) !== $slug ) {
						continue;
					}
					if ( isset( $item['label'] ) && $item['label'] !== null ) {
						$menu[ $key ][0] = esc_html( $item['label'] );
					}
					if ( isset( $item['icon'] ) && $item['icon'] !== null && strpos( $item['icon'], 'dashicons-' ) === 0 ) {
						$menu[ $key ][6] = esc_attr( $item['icon'] );
					}
					break;
				}

				if ( ! empty( $item['children'] ) ) {
					$this->apply_submenu( $slug, (array) $item['children'] );
				}
			}
		}
	}

	/**
	 * Applique les personnalisations enfants (ordre, visibilité, label) au
	 * $submenu WP réel : le filtre menu_order ne gère QUE le premier niveau,
	 * les sous-menus doivent être réécrits directement dans le global $submenu.
	 *
	 * @param string               $parent_slug Slug du parent dans $submenu.
	 * @param array<int, mixed>    $children    Enfants du profil, dans l'ordre voulu.
	 */
	private function apply_submenu( string $parent_slug, array $children ): void {
		global $submenu;
		if ( empty( $submenu[ $parent_slug ] ) || ! is_array( $submenu[ $parent_slug ] ) ) {
			return;
		}

		// Indexe les entrées WP existantes par leur slug ([2]).
		$existing = [];
		foreach ( $submenu[ $parent_slug ] as $sub ) {
			if ( is_array( $sub ) && isset( $sub[2] ) ) {
				$existing[ $sub[2] ] = $sub;
			}
		}

		$reordered = [];
		foreach ( $children as $child ) {
			$child_slug = $child['slug'] ?? '';
			if ( '' === $child_slug || ! isset( $existing[ $child_slug ] ) ) {
				continue;
			}
			if ( ! ( $child['visible'] ?? true ) ) {
				unset( $existing[ $child_slug ] );
				continue;
			}
			$entry = $existing[ $child_slug ];
			if ( isset( $child['label'] ) && $child['label'] !== null && '' !== $child['label'] ) {
				$entry[0] = esc_html( $child['label'] );
			}
			$reordered[] = $entry;
			unset( $existing[ $child_slug ] );
		}

		// Entrées WP non listées dans le profil (ajoutées après sa création) :
		// conservées à la suite pour ne rien faire disparaître par surprise.
		foreach ( $existing as $entry ) {
			$reordered[] = $entry;
		}

		$submenu[ $parent_slug ] = $reordered;
	}

	/**
	 * Ajoute target="_blank" / rel="noopener" aux liens personnalisés qui le
	 * demandent : add_menu_page() ne sait pas poser d'attribut target, on le
	 * fait donc côté DOM après rendu du menu.
	 */
	public function inject_custom_link_targets(): void {
		$profile = MenuProfileManager::get_active_for_user( get_current_user_id() );
		if ( ! $profile || empty( $profile['items'] ) ) {
			return;
		}

		$slugs = [];
		foreach ( $profile['items'] as $item ) {
			if ( 'custom_link' !== ( $item['type'] ?? '' ) ) {
				continue;
			}
			if ( empty( $item['target_blank'] ) || empty( $item['url'] ) ) {
				continue;
			}
			$slugs[] = $this->custom_link_slug( $item['url'] );
		}

		if ( ! $slugs ) {
			return;
		}
		?>
		<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			var slugs = <?php echo wp_json_encode( $slugs ); ?>;
			var links = document.querySelectorAll( '#adminmenu a.menu-top' );
			slugs.forEach( function ( slug ) {
				for ( var i = 0; i < links.length; i++ ) {
					if ( ( links[ i ].getAttribute( 'href' ) || '' ).indexOf( slug ) !== -1 ) {
						links[ i ].setAttribute( 'target', '_blank' );
						links[ i ].setAttribute( 'rel', 'noopener noreferrer' );
					}
				}
			} );
		} );
		</script>
		<?php
	}

	/**
	 * Injecte les icônes SVG/média personnalisées dans le menu admin réel.
	 *
	 * Rendu via un vrai <img> (identique à l'aperçu dans l'éditeur), pas via
	 * un background-image CSS : ce dernier laissait le stroke/fill du SVG
	 * (currentColor) se faire écraser par les styles natifs de #adminmenu,
	 * ce qui rendait les icônes "pleines" au lieu de respecter leur tracé
	 * (stroke) d'origine. Un <img> avec la même source base64 s'affiche
	 * identiquement à ce que montrent déjà le picker et l'arbre de l'éditeur.
	 */
	public function inject_menu_icon_overrides(): void {
		$profile = MenuProfileManager::get_active_for_user( get_current_user_id() );
		if ( ! $profile || empty( $profile['items'] ) ) {
			return;
		}

		// On tient compte des sous-items en plus des items de premier niveau :
		// pour un rôle à capacités réduites (ex. auteur), WordPress promeut
		// certains sous-menus en items de premier niveau (profile.php « Profil »
		// remplace users.php « Comptes »). Le JS ne cible que les .menu-top,
		// donc côté admin — où le sous-item reste un sous-menu — ceci n'a aucun
		// effet visible.
		$candidates = [];
		foreach ( $profile['items'] as $item ) {
			$candidates[] = $item;
			foreach ( (array) ( $item['children'] ?? [] ) as $child ) {
				$candidates[] = $child;
			}
		}

		$icons = [];
		foreach ( $candidates as $item ) {
			if ( empty( $item['icon'] ) ) {
				continue;
			}
			$icon = $item['icon'];

			// Un lien personnalisé est enregistré dans $menu (donc dans le
			// href réel) sous le slug dérivé par add_menu_page (plugin_basename
			// de l'URL), pas sous son slug interne généré côté client ni l'URL
			// brute (voir apply_menu_visibility / apply_menu_order).
			$match_slug = 'custom_link' === ( $item['type'] ?? 'wp_item' )
				? ( ! empty( $item['url'] ) ? $this->custom_link_slug( $item['url'] ) : '' )
				: ( $item['slug'] ?? '' );

			if ( empty( $match_slug ) ) {
				continue;
			}

			if ( strpos( $icon, 'svg:' ) === 0 ) {
				$svg_b64 = substr( $icon, 4 );
				$svg_xml = base64_decode( $svg_b64, true );
				if ( false === $svg_xml ) {
					continue;
				}
				$img_src = 'data:image/svg+xml;base64,' . base64_encode( $this->neutralize_svg_color( $svg_xml ) );
			} elseif ( strpos( $icon, 'http' ) === 0 ) {
				$img_src = esc_url( $icon );
			} else {
				continue;
			}

			$icons[] = [
				'slug' => sanitize_text_field( $match_slug ),
				'src'  => $img_src,
			];
		}

		if ( ! $icons ) {
			return;
		}

		// CSS immédiat (avant peinture, admin_head) : masque le dashicon
		// d'origine tant que le JS n'a pas remplacé le contenu, pour éviter
		// le flash "dashicon puis icône custom" au chargement. L'inline style
		// posé ensuite par le script (opacity:1 !important) prend le dessus,
		// une déclaration inline important battant toujours une règle de
		// feuille de style important sur la même propriété.
		$hide_css = '';
		foreach ( $icons as $entry ) {
			$css_slug  = str_replace( [ '"', '<', '>', '\\' ], '', $entry['slug'] );
			$hide_css .= '#adminmenu a[href*="' . $css_slug . '"] .wp-menu-image{opacity:0!important}';
		}
		echo '<style>' . $hide_css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		?>
		<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			var icons = <?php echo wp_json_encode( $icons ); ?>;
			var links = document.querySelectorAll( '#adminmenu a.menu-top' );
			icons.forEach( function ( entry ) {
				for ( var i = 0; i < links.length; i++ ) {
					var href = links[ i ].getAttribute( 'href' ) || '';
					if ( href.indexOf( entry.slug ) === -1 ) {
						continue;
					}
					var imgEl = links[ i ].querySelector( '.wp-menu-image' );
					if ( ! imgEl ) {
						continue;
					}
					// Le dashicon d'origine est rendu via un ::before CSS sur les
					// classes dashicons-before/dashicons-xxx : vider innerHTML ne
					// le retire pas (les pseudo-éléments ne font pas partie du DOM).
					// Pour les liens personnalisés, WP a aussi pu poser lui-même un
					// background-image inline (via add_menu_page + icon_url data:)
					// sur ce même élément : on repart d'un style totalement vierge.
					imgEl.className = 'wp-menu-image';
					imgEl.removeAttribute( 'style' );
					// WP applique opacity:.6 sur #adminmenu .wp-menu-image img : il
					// faut forcer opacity:1 en !important pour battre cette règle,
					// et révéler l'icône masquée temporairement par le <style> ci-dessus.
					imgEl.style.setProperty( 'opacity', '1', 'important' );
					imgEl.innerHTML = '';
					var img = document.createElement( 'img' );
					img.src = entry.src;
					img.alt = '';
					img.style.cssText = 'width:20px;height:20px;object-fit:contain;';
					img.style.setProperty( 'opacity', '1', 'important' );
					// Aligne verticalement avec les dashicons natifs : WP force
					// padding-top:9px sur .wp-menu-image img (dashicons: 7px de
					// chaque côté pour un glyphe de 20px dans un conteneur de 34px).
					img.style.setProperty( 'padding-top', '7px', 'important' );
					imgEl.appendChild( img );
					break;
				}
			} );
		} );
		</script>
		<?php
	}

	/**
	 * Force l'opacité pleine des icônes de menu, natives ET personnalisées :
	 * WP atténue par défaut #adminmenu .wp-menu-image img à 60% tant que
	 * l'item n'est pas survolé/actif — comportement jugé peu lisible, à
	 * uniformiser indépendamment d'un profil Menu Creator actif.
	 *
	 * Corrige aussi l'alignement vertical : WP applique padding:9px 0 0 aux
	 * icônes <img> (ex. le logo du plugin lui-même) alors que les dashicons
	 * sont centrés à 7px — d'où un décalage de 2px. On n'override QUE le
	 * padding-top (pas de shorthand ni de box-sizing:border-box, qui ferait
	 * rentrer le padding DANS la taille 20×20 fixée en inline sur nos icônes
	 * custom injectées et les réduirait/désaligne­rait).
	 */
	public function inject_global_icon_opacity_fix(): void {
		echo '<style>#adminmenu .wp-menu-image img{opacity:1!important;padding-top:7px!important}</style>';
	}

	/**
	 * Slugs de menu WP jamais surfacés dans l'éditeur (et donc jamais appliqués).
	 *
	 * Le Gestionnaire de liens (link-manager.php + sa taxonomie link_category)
	 * est une fonctionnalité legacy désactivée par défaut depuis WP 3.5 : quand
	 * elle est off, elle n'apparaît pas dans le menu WP réel, donc la faire
	 * remonter dans le Créateur crée un item fantôme déroutant. On la masque
	 * par défaut. Filtrable pour les sites qui l'utilisent réellement.
	 *
	 * @return array<int, string>
	 */
	private function editor_excluded_slugs(): array {
		return (array) apply_filters( 'skmt_mc_editor_excluded_slugs', [
			'link-manager.php',
		] );
	}

	/**
	 * Reproduit la transformation qu'applique add_menu_page() à un menu_slug :
	 * plugin_basename( esc_url_raw( $url ) ). C'est sous CE slug que le lien
	 * personnalisé existe réellement dans $menu / $menu_order — le matcher
	 * ainsi garantit que l'ordre choisi et l'override d'icône ciblent la bonne
	 * entrée (au lieu de laisser le lien retomber en bas du menu).
	 */
	private function custom_link_slug( string $url ): string {
		return plugin_basename( esc_url_raw( $url ) );
	}

	/**
	 * Résout un icon_url natif WP (dashicon, data-URI SVG, ou URL média)
	 * à partir du format interne stocké côté client.
	 */
	private function resolve_native_icon_url( ?string $icon ): string {
		if ( empty( $icon ) ) {
			return 'dashicons-admin-links';
		}
		if ( strpos( $icon, 'dashicons-' ) === 0 ) {
			return sanitize_text_field( $icon );
		}
		if ( strpos( $icon, 'svg:' ) === 0 ) {
			$svg_b64 = substr( $icon, 4 );
			$svg_xml = base64_decode( $svg_b64, true );
			if ( false === $svg_xml ) {
				return 'dashicons-admin-links';
			}
			return 'data:image/svg+xml;base64,' . base64_encode( $this->neutralize_svg_color( $svg_xml ) );
		}
		if ( strpos( $icon, 'http' ) === 0 ) {
			return esc_url_raw( $icon );
		}
		return 'dashicons-admin-links';
	}

	/**
	 * Fige "currentColor" à la couleur de repos réelle des glyphes dashicons
	 * de ce skin admin (#f3f1f1, cf. colors/modern/colors.css) : rendu en
	 * <img>/background-image (pas de DOM inline), currentColor ne peut hériter
	 * d'aucune couleur de texte environnante et résoudrait sinon au noir par
	 * défaut. Fixe (pas de variante hover blanche) — l'écart visuel avec le
	 * blanc pur du survol natif est minime.
	 */
	private function neutralize_svg_color( string $svg_xml ): string {
		return str_replace( 'currentColor', '#f3f1f1', $svg_xml );
	}

	/* ================================================================
	 * AJAX
	 * ================================================================ */

	public function ajax_save_profile(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) ] );
		}

		$raw = isset( $_POST['profile'] ) ? wp_unslash( $_POST['profile'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( $raw ) || ! is_string( $raw ) ) {
			wp_send_json_error( [ 'message' => __( 'Données manquantes.', 'studio-kyne-mini-tools' ) ] );
		}

		$profile = json_decode( $raw, true );
		if ( ! is_array( $profile ) ) {
			wp_send_json_error( [ 'message' => __( 'JSON invalide.', 'studio-kyne-mini-tools' ) ] );
		}

		$sanitized = $this->sanitize_profile( $profile );
		MenuProfileManager::save( $sanitized );
		wp_send_json_success( [ 'profile' => $sanitized ] );
	}

	public function ajax_delete_profile(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) ] );
		}

		$profile_id = isset( $_POST['profile_id'] ) ? sanitize_text_field( wp_unslash( $_POST['profile_id'] ) ) : '';
		if ( empty( $profile_id ) ) {
			wp_send_json_error( [ 'message' => __( 'ID manquant.', 'studio-kyne-mini-tools' ) ] );
		}

		MenuProfileManager::delete( $profile_id );
		wp_send_json_success();
	}

	public function ajax_duplicate_profile(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) ] );
		}

		$profile_id = isset( $_POST['profile_id'] ) ? sanitize_text_field( wp_unslash( $_POST['profile_id'] ) ) : '';
		$original   = MenuProfileManager::get( $profile_id );
		if ( ! $original ) {
			wp_send_json_error( [ 'message' => __( 'Profil introuvable.', 'studio-kyne-mini-tools' ) ] );
		}

		$copy               = $original;
		$copy['id']         = wp_generate_uuid4();
		$copy['name']       = $original['name'] . ' ' . __( '(copie)', 'studio-kyne-mini-tools' );
		$copy['status']     = 'draft';
		$copy['updated_at'] = time();

		MenuProfileManager::save( $copy );
		wp_send_json_success( [ 'profile' => $copy ] );
	}

	public function ajax_search_users(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$query = sanitize_text_field( $_GET['q'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$users = get_users( [ 'search' => '*' . $query . '*', 'number' => 20 ] );

		$result = array_map(
			fn( \WP_User $u ) => [
				'id'    => $u->ID,
				'label' => $u->display_name . ' (' . $u->user_login . ')',
			],
			$users
		);
		wp_send_json_success( $result );
	}

	/* ================================================================
	 * HELPERS
	 * ================================================================ */

	private function sanitize_profile( array $profile ): array {
		return [
			'id'            => ! empty( $profile['id'] ) ? sanitize_text_field( $profile['id'] ) : wp_generate_uuid4(),
			'name'          => sanitize_text_field( $profile['name'] ?? '' ),
			'status'        => in_array( $profile['status'] ?? '', [ 'draft', 'active' ], true ) ? $profile['status'] : 'draft',
			'apply_to_all'  => ! empty( $profile['apply_to_all'] ),
			'include_roles' => array_map( 'sanitize_key', (array) ( $profile['include_roles'] ?? [] ) ),
			'include_users' => array_map( 'absint', (array) ( $profile['include_users'] ?? [] ) ),
			'exclude_roles' => array_map( 'sanitize_key', (array) ( $profile['exclude_roles'] ?? [] ) ),
			'exclude_users' => array_map( 'absint', (array) ( $profile['exclude_users'] ?? [] ) ),
			'items'         => $this->sanitize_menu_items( (array) ( $profile['items'] ?? [] ) ),
			'updated_at'    => time(),
		];
	}

	private function sanitize_menu_items( array $items, int $depth = 0 ): array {
		// Garde-fou anti-payload : borne le nombre d'entrées persistées par
		// niveau, pour éviter qu'un profil pathologique ne gonfle l'option.
		$items     = array_slice( array_values( $items ), 0, self::MAX_ITEMS );
		$sanitized = [];
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) continue;
			$type     = in_array( $item['type'] ?? '', [ 'wp_item', 'custom_link', 'separator' ], true )
				? $item['type'] : 'wp_item';
			$children = [];
			if ( 0 === $depth && ! empty( $item['children'] ) ) {
				$children = $this->sanitize_menu_items( (array) $item['children'], 1 );
			}
			$sanitized[] = [
				'type'         => $type,
				'slug'         => sanitize_text_field( $item['slug'] ?? '' ),
				'label'        => isset( $item['label'] ) && $item['label'] !== null ? sanitize_text_field( $item['label'] ) : null,
				'icon'         => $this->sanitize_icon_value( $item['icon'] ?? null ),
				'visible'      => isset( $item['visible'] ) ? (bool) $item['visible'] : true,
				'target_blank' => ! empty( $item['target_blank'] ),
				'url'          => 'custom_link' === $type ? esc_url_raw( $item['url'] ?? '' ) : '',
				'children'     => $children,
			];
		}
		return $sanitized;
	}

	/**
	 * Sanitise la valeur d'icône : dashicon / "svg:<base64>" via
	 * sanitize_text_field, mais URL média via esc_url_raw.
	 *
	 * @param mixed $icon
	 */
	private function sanitize_icon_value( $icon ): ?string {
		if ( $icon === null || '' === $icon ) {
			return null;
		}
		$icon = (string) $icon;
		if ( strpos( $icon, 'http' ) === 0 ) {
			return esc_url_raw( $icon );
		}
		return sanitize_text_field( $icon );
	}

	/* ================================================================
	 * SETTINGS
	 * ================================================================ */

	public function get_settings(): array {
		return [];
	}

	public function save_settings( array $settings ): bool {
		return true;
	}

	public static function get_defaults(): array {
		return [];
	}

	public static function get_uninstall_keys(): array {
		return [
			'options' => [ MenuProfileManager::OPTION_KEY ],
			'meta'    => [],
		];
	}

	/* ================================================================
	 * ASSETS
	 * ================================================================ */

	public function get_admin_css(): array {
		return [ SKMT_ASSETS_URL . 'admin/css/modules/menu-creator.css' ];
	}

	public function get_admin_js(): array {
		return [
			SKMT_ASSETS_URL . 'admin/js/vendor/sortable.min.js',
			SKMT_ASSETS_URL . 'admin/js/modules/menu-creator.js',
		];
	}

	public function get_admin_js_data(): array {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data = [
			'mcProfiles' => MenuProfileManager::get_all(),
			'i18n'       => [
				'newMenu'          => __( 'Nouveau menu', 'studio-kyne-mini-tools' ),
				'draft'            => __( 'Brouillon', 'studio-kyne-mini-tools' ),
				'active'           => __( 'Actif', 'studio-kyne-mini-tools' ),
				'unsavedChanges'   => __( 'Modifications non sauvegardées', 'studio-kyne-mini-tools' ),
				'leaveConfirm'     => __( 'Vos modifications seront perdues. Continuer ?', 'studio-kyne-mini-tools' ),
				'deleteConfirmMsg' => __( 'Cette action est irréversible.', 'studio-kyne-mini-tools' ),
			],
		];

		if ( 'module_menu_creator' === $tab && is_admin() && current_user_can( 'manage_options' ) ) {
			global $menu, $submenu;
			// Menu WP d'origine si disponible (capturé avant nos modifications),
			// sinon le global (déjà pristine quand aucun profil n'est actif).
			$src_menu    = null !== self::$pristine_menu    ? self::$pristine_menu    : ( is_array( $menu ) ? $menu : [] );
			$src_submenu = null !== self::$pristine_submenu ? self::$pristine_submenu : ( is_array( $submenu ) ? $submenu : [] );
			$excluded    = $this->editor_excluded_slugs();
			$wp_menu = [];
			foreach ( $src_menu as $item ) {
				if ( ! is_array( $item ) ) continue;
				if ( in_array( $item[2] ?? '', $excluded, true ) ) continue;
				$wp_menu[] = [
					'label' => wp_strip_all_tags( $item[0] ?? '' ),
					'cap'   => $item[1] ?? 'read',
					'slug'  => $item[2] ?? '',
					'icon'  => $item[6] ?? '',
				];
			}
			$wp_submenu = [];
			if ( is_array( $src_submenu ) ) {
				foreach ( $src_submenu as $parent => $subs ) {
					if ( in_array( $parent, $excluded, true ) ) continue;
					$wp_submenu[ $parent ] = [];
					foreach ( (array) $subs as $item ) {
						if ( ! is_array( $item ) ) continue;
						$wp_submenu[ $parent ][] = [
							'label' => wp_strip_all_tags( $item[0] ?? '' ),
							'cap'   => $item[1] ?? 'read',
							'slug'  => $item[2] ?? '',
						];
					}
				}
			}
			$recent_users = get_users( [ 'number' => 30, 'orderby' => 'registered', 'order' => 'DESC' ] );
			$data['wpMenu']        = $wp_menu;
			$data['wpSubmenu']     = $wp_submenu;
			$data['wpRoles']       = wp_roles()->get_names();
			$data['wpRecentUsers'] = array_map( function ( \WP_User $u ) {
				return [
					'id'    => (int) $u->ID,
					'label' => $u->display_name . ' (' . $u->user_login . ')',
				];
			}, $recent_users );
			$data['iconLibrary'] = $this->get_lucide_icons();
		}

		return $data;
	}

	/**
	 * Bibliothèque d'icônes Lucide embarquées (MIT).
	 * Retourne un tableau associatif slug → contenu SVG (sans le wrapper <svg>).
	 *
	 * @return array<string, string>
	 */
	private function get_lucide_icons(): array {
		$w = 'xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
		$icons = [
			'layout-dashboard'       => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
			'house'                  => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
			'gauge'                  => '<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
			'chart-column'           => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
			'settings'               => '<path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/>',
			'settings-2'             => '<path d="M14 17H5"/><path d="M19 7h-9"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
			'sliders-horizontal'     => '<path d="M10 5H3"/><path d="M12 19H3"/><path d="M14 3v4"/><path d="M16 17v4"/><path d="M21 12h-9"/><path d="M21 19h-5"/><path d="M21 5h-7"/><path d="M8 10v4"/><path d="M8 12H3"/>',
			'sliders-vertical'       => '<path d="M10 8h4"/><path d="M12 21v-9"/><path d="M12 8V3"/><path d="M17 16h4"/><path d="M19 12V3"/><path d="M19 21v-5"/><path d="M3 14h4"/><path d="M5 10V3"/><path d="M5 21v-7"/>',
			'wrench'                 => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/>',
			'user-round'             => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
			'users-round'            => '<path d="M18 21a8 8 0 0 0-16 0"/><circle cx="10" cy="8" r="5"/><path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"/>',
			'file'                   => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/>',
			'file-text'              => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
			'image'                  => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
			'gallery-horizontal'     => '<path d="M2 3v18"/><rect width="12" height="18" x="6" y="3" rx="2"/><path d="M22 3v18"/>',
			'gallery-horizontal-end' => '<path d="M2 7v10"/><path d="M6 5v14"/><rect width="12" height="18" x="10" y="3" rx="2"/>',
			'gallery-vertical'       => '<path d="M3 2h18"/><rect width="18" height="12" x="3" y="6" rx="2"/><path d="M3 22h18"/>',
			'gallery-vertical-end'   => '<path d="M7 2h10"/><path d="M5 6h14"/><rect width="18" height="12" x="3" y="10" rx="2"/>',
			'swatch-book'            => '<path d="M11 17a4 4 0 0 1-8 0V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2Z"/><path d="M16.7 13H19a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H7"/><path d="M 7 17h.01"/><path d="m11 8 2.3-2.3a2.4 2.4 0 0 1 3.404.004L18.6 7.6a2.4 2.4 0 0 1 .026 3.434L9.9 19.8"/>',
			'palette'                => '<path d="M12 22a1 1 0 0 1 0-20 10 9 0 0 1 10 9 5 5 0 0 1-5 5h-2.25a1.75 1.75 0 0 0-1.4 2.8l.3.4a1.75 1.75 0 0 1-1.4 2.8z"/><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>',
			'globe'                  => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
			'map'                    => '<path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"/><path d="M15 5.764v15"/><path d="M9 3.236v15"/>',
			'map-pin'                => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
			'map-pinned'             => '<path d="M18 8c0 3.613-3.869 7.429-5.393 8.795a1 1 0 0 1-1.214 0C9.87 15.429 6 11.613 6 8a6 6 0 0 1 12 0"/><circle cx="12" cy="8" r="2"/><path d="M8.714 14h-3.71a1 1 0 0 0-.948.683l-2.004 6A1 1 0 0 0 3 22h18a1 1 0 0 0 .948-1.316l-2-6a1 1 0 0 0-.949-.684h-3.712"/>',
			'pin'                    => '<path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/>',
			'megaphone'              => '<path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/><path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14"/><path d="M8 6v8"/>',
			'message-circle'         => '<path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/>',
			'message-square'         => '<path d="M22 17a2 2 0 0 1-2 2H6.828a2 2 0 0 0-1.414.586l-2.202 2.202A.71.71 0 0 1 2 21.286V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"/>',
			'life-buoy'              => '<circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/><path d="m14.83 14.83 4.24 4.24"/><path d="m9.17 14.83-4.24 4.24"/><circle cx="12" cy="12" r="4"/>',
			'paperclip'              => '<path d="m16 6-8.414 8.586a2 2 0 0 0 2.829 2.829l8.414-8.586a4 4 0 1 0-5.657-5.657l-8.379 8.551a6 6 0 1 0 8.485 8.485l8.379-8.551"/>',
			'layers'                 => '<path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z"/><path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12"/><path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17"/>',
			'package'                => '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><polyline points="3.29 7 12 12 20.71 7"/><path d="m7.5 4.27 9 5.15"/>',
			'package-2'              => '<path d="M12 3v6"/><path d="M16.76 3a2 2 0 0 1 1.8 1.1l2.23 4.479a2 2 0 0 1 .21.891V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9.472a2 2 0 0 1 .211-.894L5.45 4.1A2 2 0 0 1 7.24 3z"/><path d="M3.054 9.013h17.893"/>',
			'blocks'                 => '<path d="M10 22V7a1 1 0 0 0-1-1H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5a1 1 0 0 0-1-1H2"/><rect x="14" y="2" width="8" height="8" rx="1"/>',
			'toy-brick'              => '<rect width="18" height="12" x="3" y="8" rx="1"/><path d="M10 8V5c0-.6-.4-1-1-1H6a1 1 0 0 0-1 1v3"/><path d="M19 8V5c0-.6-.4-1-1-1h-3a1 1 0 0 0-1 1v3"/>',
			'puzzle'                 => '<path d="M15.39 4.39a1 1 0 0 0 1.68-.474 2.5 2.5 0 1 1 3.014 3.015 1 1 0 0 0-.474 1.68l1.683 1.682a2.414 2.414 0 0 1 0 3.414L19.61 15.39a1 1 0 0 1-1.68-.474 2.5 2.5 0 1 0-3.014 3.015 1 1 0 0 1 .474 1.68l-1.683 1.682a2.414 2.414 0 0 1-3.414 0L8.61 19.61a1 1 0 0 0-1.68.474 2.5 2.5 0 1 1-3.014-3.015 1 1 0 0 0 .474-1.68l-1.683-1.682a2.414 2.414 0 0 1 0-3.414L4.39 8.61a1 1 0 0 1 1.68.474 2.5 2.5 0 1 0 3.014-3.015 1 1 0 0 1-.474-1.68l1.683-1.682a2.414 2.414 0 0 1 3.414 0z"/>',
			'shopping-bag'           => '<path d="M16 10a4 4 0 0 1-8 0"/><path d="M3.103 6.034h17.794"/><path d="M3.4 5.467a2 2 0 0 0-.4 1.2V20a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6.667a2 2 0 0 0-.4-1.2l-2-2.667A2 2 0 0 0 17 2H7a2 2 0 0 0-1.6.8z"/>',
			'shopping-cart'          => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
			'banknote'               => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
			'dollar-sign'            => '<line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
			'euro'                   => '<path d="M4 10h12"/><path d="M4 14h9"/><path d="M19 6a7.7 7.7 0 0 0-5.2-2A7.9 7.9 0 0 0 6 12c0 4.4 3.5 8 7.8 8 2 0 3.8-.8 5.2-2"/>',
		];

		$result = [];
		foreach ( $icons as $name => $paths ) {
			$result[ $name ] = '<svg ' . $w . '>' . $paths . '</svg>';
		}
		return $result;
	}

	public function maybe_enqueue_media( string $hook ): void {
		if ( false === strpos( $hook, 'studio-kyne-mini-tools' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'module_menu_creator' === $tab ) {
			wp_enqueue_media();
			wp_enqueue_style( 'dashicons' );
		}
	}
}
