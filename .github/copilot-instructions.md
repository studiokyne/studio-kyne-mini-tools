# Studio Kyne Mini Tools - Project Guidelines

## Scope And Stack

- WordPress plugin in PHP for admin-side tooling.
- No Node, Composer, or test runner is configured in this repository.
- Treat this as a WordPress runtime project: most validation is functional in wp-admin.

## Architecture

- Bootstrap entrypoint: [studio-kyne-mini-tools.php](studio-kyne-mini-tools.php).
- Core services live in [includes/core](includes/core): plugin lifecycle, loader, settings, logger, jobs, capabilities, module manager.
- Modules live in [includes/modules](includes/modules) and are auto-discovered from module.php files by [includes/core/class-skmt-module-manager.php](includes/core/class-skmt-module-manager.php).
- Every module must implement [includes/core/interfaces/interface-skmt-module.php](includes/core/interfaces/interface-skmt-module.php).

## Build And Validation

- Do not assume local build steps (no npm, no composer, no phpunit configs present).
- Preferred validation flow after changes:

1. Activate plugin in WordPress admin.
2. Open SKMT pages and verify related UI and settings save flows.
3. For image optimizer changes, test upload and bulk conversion behavior.

- If you suggest commands, keep them optional and clearly mark as environment-dependent.

## Code Conventions

- Follow existing style: guard ABSPATH early, compact class methods, WordPress helper functions.
- Sanitize all user input with WordPress sanitizers and cast numeric values explicitly.
- Enforce capability checks on admin endpoints and AJAX handlers via SKMT capabilities helpers.
- Use nonces for privileged actions and keep wpdb queries prepared.
- Keep text domain exactly studio-kyne-mini-tools for translatable strings.
- Prefer extending current module patterns over introducing new architectural abstractions.

## Data And Safety

- Respect optional cleanup behavior on uninstall (cleanup_on_uninstall setting).
- Be careful with destructive image operations: avoid deleting files until replacement is confirmed.
- Preserve existing options schema and defaults unless migration logic is included.

## Key References

- Project overview and install notes: [readme.txt](readme.txt).
- Good module example: [includes/modules/image-optimizer/class-module.php](includes/modules/image-optimizer/class-module.php).
- AJAX security and batch processing pattern: [includes/modules/image-optimizer/class-bulk-runner.php](includes/modules/image-optimizer/class-bulk-runner.php).
