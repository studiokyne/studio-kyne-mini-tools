---
applyTo: "includes/modules/image-optimizer/**/*.php"
description: "Use when editing the image optimizer module: upload optimization, bulk conversion, stats persistence, and media processing safety."
---

# Image Optimizer Instructions

## Scope

- Applies to the image optimizer module only.
- Reuse existing module patterns from [includes/modules/image-optimizer/class-module.php](includes/modules/image-optimizer/class-module.php).

## Security And Validation

- Keep capability checks on admin and AJAX entry points via SKMT capability helpers.
- Keep nonce checks on privileged AJAX endpoints (`check_ajax_referer`).
- Sanitize and cast all inputs (`sanitize_key`, `sanitize_text_field`, `sanitize_mime_type`, `absint`).
- Keep database interactions prepared when raw SQL is required.

## Media Processing Safety

- Avoid destructive file operations until replacement output is confirmed valid and written.
- Keep graceful fallback behavior for format support (AVIF/WebP/Auto).
- Preserve current handling for unsupported or skipped files (for example animated GIF paths).

## Data And Options

- Keep option names and defaults backward compatible unless migration is added.
- Keep sanitize bounds for quality and dimensions strict and explicit.
- Preserve per-image stats/history update flows when processing succeeds, skips, or fails.

## Bulk Runner Behavior

- Keep lock-based concurrency control using SKMT jobs to avoid overlapping runs.
- Preserve cursor-based batch progression and bounded batch sizes.
- Keep localized user-facing status/error messages and use text domain `studio-kyne-mini-tools`.

## References

- [includes/modules/image-optimizer/class-module.php](includes/modules/image-optimizer/class-module.php)
- [includes/modules/image-optimizer/class-bulk-runner.php](includes/modules/image-optimizer/class-bulk-runner.php)
- [includes/modules/image-optimizer/class-image-processor.php](includes/modules/image-optimizer/class-image-processor.php)
- [includes/modules/image-optimizer/class-stats-repository.php](includes/modules/image-optimizer/class-stats-repository.php)
