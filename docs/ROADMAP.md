# SKMT Roadmap (Private Working Draft)

## Near Term (0-2 months)

- Improve media history filters in Image Optimizer (status, date range, format).
- Add one-click retry for failed image optimizations.
- Add dry-run mode for bulk conversion (estimate only, no write).
- Add conversion queue progress persistence (resume after admin refresh).
- Improve upload safety checks (disk space threshold and editor capability checks).
- Add dedicated settings backup history (last import metadata and rollback option).

## Mid Term (2-6 months)

- Add smart optimization profiles: quality presets by image type and dimensions.
- Add EXIF/metadata handling strategy options (preserve/strip selective fields).
- Add CDN-aware URL rewrite compatibility checks.
- Add optional WebP/AVIF fallback generation policy controls.
- Add batch scheduling via WP-Cron for off-peak optimization windows.
- Add module health dashboard with warnings and actionable suggestions.

## New Module Ideas

- Media Audit: detect oversized images, missing alt text, and duplicate assets.
- Broken Links Scanner: periodic scan with auto-report and fix suggestions.
- Admin Cleanup: streamline admin UI, hide noisy notices per role.
- SEO Snippets Assistant: metadata templates and missing fields detector.
- Performance Inspector: cache status, autoload option size alerts, transient bloat checks.

## DX / Ops Improvements

- Add WP-CLI commands for export/import, bulk run, and stats report.
- Add GitHub Actions for packaging and tagged releases.
- Add coding standards config (PHPCS) and baseline quality gates.
- Add integration tests for critical settings flows in wp-admin.
- Add dedicated migration helpers for future option/schema evolution.

## Product Quality Priorities

- Accessibility pass on all admin screens (keyboard, contrast, labels).
- Better empty states and guidance for first-time setup.
- Consistent localization workflow and translation files maintenance.
- Improve error observability (module-level diagnostics and admin notices).
