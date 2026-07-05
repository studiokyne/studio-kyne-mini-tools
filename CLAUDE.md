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

Rate limiter entries are stored as individual WordPress options with key `_skmt_security_login_attempt_{md5(ip)}`.

### Module system

Each module is a class extending `AbstractModule` (which implements `ModuleInterface`). Required methods:

- `init(): void` — register all WordPress hooks here
- `get_settings(): array` — return current settings
- `save_settings(array $settings): bool` — sanitize and persist; the core applies no sanitization
- `static get_defaults(): array` — nested array of defaults, merged via `wp_parse_args`
- `static get_uninstall_keys(): array` — declare `options` and `meta` keys for uninstall cleanup

Optional overrides: `get_admin_css()`, `get_admin_js()`, `get_admin_js_data()`, `on_activate()`, `on_deactivate()`.

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

### Persistent notices

Separate from the ephemeral toast system (`skmtShowToast`), `Admin::add_persistent_notice(string $id, string $message, string $type, int $user_id = 0)` / `Admin::dismiss_persistent_notice(string $id)` store notices in a user's `skmt_notices` user meta so they survive page reloads. `$user_id` defaults to the current user but can target a specific user from a userless context (e.g. cron completing a background job). Rendered into `window.skmtPersistentNotices` in the notification drawer; dismissed client-side via the `wp_ajax_skmt_dismiss_notice` endpoint (`Admin::handle_dismiss_notice()`).

## Module specifics

### Security

- **RateLimiter**: hooks into the `authenticate` filter at priority 999 (returns `WP_Error` to block). Failure tracking via `wp_login_failed` hook. Success reset via `wp_login` hook. `maybe_block_login($user)` is the filter callback.
- **LoginUrlHandler**: hooks into `wp_loaded` to intercept both the custom login slug and direct `wp-login.php` access. Must check `action !== 'logout'` before redirecting logged-in users.
- **HardeningService**: user enumeration is blocked via `template_redirect` + `is_author()` (not `parse_query`). REST user endpoint uses `rest_request_before_callbacks` filter.

### WhiteLabel

Four concerns in one module (all `Module.php` except menu profiles), each hooked only when its own setting group is enabled:
- **Admin bar / footer cleanup**: each toggle in `admin_bar`/`footer` settings conditionally registers its own `admin_bar_menu`/`admin_head`/`gettext`/`show_admin_bar` hook in `init()` — nothing is hooked unless the corresponding setting is enabled.
- **Profile page cleanup** (`profile` settings): if any `profile.*` toggle is on, an `admin_head` hook (`clean_profile_page()`) injects CSS on `profile.php`/`user-edit.php` to hide chosen core sections (color scheme, keyboard shortcuts, toolbar toggle, application passwords, language, bio, sessions, editor options). Visual masking only — the underlying features stay server-side intact. Targets stable core `<tr>`/section classes rather than `remove_action` (registrations vary by WP version).
- **Local avatars** (`avatars.local` setting): when on, a `get_avatar_data` filter (`apply_local_avatar()`) swaps in the user's uploaded image; a media-picker field is rendered on the profile form via `personal_options` and saved to the `skmt_local_avatar` user meta (declared in `get_uninstall_keys()`). No avatar meta → WordPress falls back to Gravatar. The native "Profile Picture" (Gravatar) row is hidden via `admin_head` CSS.
- **Menu profile storage** (`MenuProfileManager.php`): a static, settings-independent CRUD/resolution layer stored under its own option `skmt_wl_menu_profiles` (not `skmt_module_white_label`). `get_active_for_user()` resolves the highest-priority active profile per user (include_users > include_roles > apply_to_all, exclusions always win, ties broken by `updated_at`) and caches the result in a per-user transient (`skmt_wl_menu_user_{id}`, 1h TTL) — call `clear_user_cache()`/`clear_all_cache()` after any profile mutation. This class is consumed by the **MenuCreator** module, not by WhiteLabel's own `Module.php`.

### ImageOptimizer

Orchestrator (`Module.php`) over `ImageProcessor` (conversion/resize), `MediaLibrary` (media-list integration), `BulkProcessor` (batched WP-Cron optimization of the whole library), and `SvgHandler` (secure SVG upload support). `BulkProcessor` persists its state (including the initiating `user_id`) under `{module_option_key}` + `BULK_STATE_SUFFIX` and takes an optional `on_complete_fn(int $user_id)` callback fired once when the run finishes — the module uses it to drop a persistent notice for that user (works even though the cron tick has no current user). Current bulk state is passed to JS via `get_admin_js_data()` as `bulkState` so the UI can resume progress display on load. The bulk flow has a pre-scan step (`ajax_bulk_scan` → `BulkProcessor::ajax_scan()`) before `ajax_bulk_start`.

`SvgHandler` only registers its filters when the `svg_upload` setting is on. It gates uploads by role (`svg_roles`, sanitized against real WP roles in `Module::sanitize_roles()`), adds `image/svg+xml` to `upload_mimes`, fixes WordPress' MIME/ext detection (`wp_check_filetype_and_ext`), and sanitizes every SVG on upload (`wp_handle_upload_prefilter`) via a whitelist DOMDocument pass — strips non-whitelisted tags, event handlers, unsafe `href`/`xlink:href` (only internal anchors and `data:image/*` allowed), script-bearing attributes/styles, and rejects DOCTYPE+ENTITY (XXE). Never loads `LIBXML_NOENT`. A file that fails sanitization is rejected with an error rather than stored.

### MenuCreator

Applies the `MenuProfileManager` profiles to the actual wp-admin menu: `custom_menu_order` (enabled per-user via `maybe_enable_custom_order`, not blindly) + `menu_order` filter for ordering, `admin_menu` action for visibility/relabeling/separators/custom links (including child/submenu reordering), `admin_head` for injecting per-item icon CSS overrides (base64 SVG or URL), custom-link `target` attributes, and a global menu-icon opacity fix (registered unconditionally). Menu-transforming hooks only register in `init()` if at least one profile is `status === 'active'`. `apply_menu_visibility()` snapshots the untouched WP menu into `self::$pristine_menu`/`$pristine_submenu` **before** mutating the globals, so the editor is fed the real WP menu (not our already-injected separators/custom links).

The editor gets its data through `get_admin_js_data()` (keys `mcProfiles`, `wpMenu`, `wpSubmenu`, `wpRoles`, `wpRecentUsers`, `iconLibrary`) — there is **no** `ajax_get_wp_menu` endpoint. Drag-and-drop reordering uses vendored SortableJS (`assets/admin/js/vendor/sortable.min.js`). Ships its own AJAX endpoints (`skmt_wl_*` action names despite living in the MenuCreator module) and embeds a Lucide icon library inline in `get_lucide_icons()` for the icon picker.

### Login

Customizes the WordPress login page via `login_*` hooks (`login_enqueue_scripts`, `login_head` CSS variables, `login_headerurl`/`login_headertext` logo, `login_footer` side panel + DOM tweaks). Each optional hide/toggle (language dropdown, lost-password, back-to-blog) is registered conditionally. Settings stored under `skmt_module_login`.

### Files

`FileManager.php` is a standalone filesystem service rooted at `ABSPATH`; `Module.php` is a thin AJAX/admin-post wrapper (`ajax_list`, `ajax_delete`, `ajax_rename`, `ajax_move`, `ajax_mkdir`, `ajax_zip`, `ajax_extract`, `ajax_get_content`, `ajax_save_content`, `ajax_upload`, plus a `admin_post_skmt_files_download` streaming download handler). All path input goes through `get_post_path()` (`sanitize_text_field` + `rawurldecode`) before reaching `FileManager` — never trust a client-supplied path directly.

### Database

Table explorer/editor over `$wpdb`. `Module.php` is a thin AJAX layer (no settings — `get_settings()`/`save_settings()`/`get_defaults()` are no-ops); the whole UI is built client-side in `assets/admin/js/modules/database.js` from a two-pane template (`settings-template.php`: sidebar table list + main Données/Structure/Requête SQL tabs).

AJAX actions: `skmt_db_get_tables`, `skmt_db_get_rows`, `skmt_db_get_structure`, `skmt_db_update_row`, `skmt_db_delete_row`, `skmt_db_insert_row`, `skmt_db_truncate`, `skmt_db_drop_table`, `skmt_db_export_sql`, `skmt_db_run_query`.

Safety model (all server-side, never trust the client):
- **Table/column names** go through `read_table()` → `validate_table()` (checks against `information_schema.tables` for the current DB) and are whitelisted against `get_columns_map()` (`SHOW COLUMNS`). Uses `sanitize_text_field` (NOT `sanitize_key`) so mixed-case identifiers survive.
- **Typing**: `column_format()` maps each column to `%d`/`%f`/`%s` for `$wpdb->update`/`insert`/`delete`; NULL is explicit (a `set_null` flag / null value), rejected server-side if the column is `NOT NULL`.
- **Free SQL editor** (`ajax_run_query`): rejects `FORBIDDEN_KEYWORDS` (server-level destructive ops); any write query requires `$_POST['confirm']` (JS pre-confirms via `window.skmtModal` and mirrors the read/write test) else returns `needs_confirm`; a `SELECT` with no explicit `LIMIT` is capped at `QUERY_ROW_CAP` (1000) rows and flagged `truncated`.
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
