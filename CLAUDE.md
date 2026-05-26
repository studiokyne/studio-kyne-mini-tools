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
