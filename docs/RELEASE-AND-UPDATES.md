# Release And Updates (GitHub)

## Release Pipeline (GitHub Actions)

Release creation is automated via `.github/workflows/release.yml`.

From GitHub:

1. Open `Actions > Release Plugin`.
2. Click `Run workflow`.
3. Fill `version` with a semantic version (`X.Y.Z`).
4. Optionally enable `prerelease`.

The workflow will:

- Validate the version format.
- Update plugin version in `studio-kyne-mini-tools.php`.
- Update `Stable tag` in `readme.txt`.
- Commit and push the version bump.
- Create and push tag `vX.Y.Z`.
- Build `dist/studio-kyne-mini-tools-X.Y.Z.zip`.
- Publish a GitHub Release with the ZIP attached.

## Plugin Update Detection From GitHub

The plugin supports update checks against GitHub Releases via `SKMT_Updater`.

Default repository:

```text
studiokyne/studio-kyne-mini-tools
```

No `SKMT_GITHUB_REPO` configuration is required for this project.

Optional in `wp-config.php`:

```php
define( 'SKMT_GITHUB_TOKEN', '' ); // Optional (private repo or API rate-limit mitigation)
```

Public repository setup:

- Repository is hardcoded in plugin bootstrap.
- `SKMT_GITHUB_TOKEN` is not required in the common public case.

Notes:

- The updater reads the latest release from GitHub API.
- It compares release tag version (example: `v0.2.0`) to `SKMT_VERSION`.
- WordPress then shows update availability and supports native auto-update toggle.

## Recommendations

- Use semantic version tags only (`v0.1.0`, `v0.1.1`, `v0.2.0`).
- Keep release notes meaningful (used in update details).
- Add CI checks before release (syntax and coding standards when available).
- Use protected branches with PR reviews for safer releases.
