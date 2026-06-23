# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

**Studio Kyne Mini Tools** is a modular WordPress plugin (PHP 7.4+, WP 5.8+). No build tools, no Composer, no npm — pure PHP with a custom PSR-4 autoloader. There are no automated tests.

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

**Module settings template** lives at `includes/Modules/{ModuleName}/settings-template.php` (loaded by `templates/admin/module-settings.php`). Available variables: `$module_id`, `$module`, `$instance`, `$module_settings`, `$tab`.

Form fields must use `name="skmt_module_settings[field_name]"` and the hidden input `skmt_tab=module_{id}` so `Admin::handle_save_settings()` routes the POST correctly.

### Admin form flow

All settings forms POST to `admin-post.php`. The action name determines the handler:
- `skmt_save_settings` → `Admin::handle_save_settings()`
- `skmt_toggle_module` → `Admin::handle_toggle_module()`
- `skmt_update_modules` → `Admin::handle_update_modules()`
- `skmt_check_updates` → `Admin::handle_check_updates()`
- `skmt_reset_settings` → `Admin::handle_reset_settings()`

All handlers verify a nonce and `manage_options` capability, then redirect with `?skmt_notice=...`.

### Icon system

Icons are inline SVGs rendered via `Admin::render_icon(string $icon, string $size, string $extra_class)`. Available icons are defined in `Admin::get_icon_paths()`: `layout-dashboard`, `package`, `settings`, `image`, `check-circle`, `info`, `shield`.

## Security module specifics

- **RateLimiter**: hooks into the `authenticate` filter at priority 999 (returns `WP_Error` to block). Failure tracking via `wp_login_failed` hook. Success reset via `wp_login` hook. `maybe_block_login($user)` is the filter callback.
- **LoginUrlHandler**: hooks into `wp_loaded` to intercept both the custom login slug and direct `wp-login.php` access. Must check `action !== 'logout'` before redirecting logged-in users.
- **HardeningService**: user enumeration is blocked via `template_redirect` + `is_author()` (not `parse_query`). REST user endpoint uses `rest_request_before_callbacks` filter.

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
