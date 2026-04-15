=== Studio Kyne Mini Tools ===
Contributors: studiokyne
Tags: media, images, optimization, admin
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Suite modulaire d'outils WordPress par Studio Kyne.

== Description ==

Studio Kyne Mini Tools (SKMT) est une base de plugin modulaire pensée pour accueillir plusieurs outils activables :
- Image Optimizer
- futurs modules

La V1 inclut déjà :
- un coeur SKMT propre et extensible
- une gestion des modules activables
- une interface admin unifiée
- un module d’optimisation d’images

== Installation ==

1. Téléversez le dossier `studio-kyne-mini-tools` dans `/wp-content/plugins/`, ou installez l’archive ZIP depuis l’admin WordPress.
2. Activez le plugin via le menu Extensions.
3. Ouvrez `SKMT` dans l’administration.
4. Activez les modules souhaités dans `SKMT > Modules`.

== Included modules ==

= Image Optimizer =
- optimisation à l’upload
- redimensionnement max configurable
- conversion AVIF / WebP / Auto
- conservation optionnelle de l’original
- conversion de masse
- statistiques et historique par image

== Architecture ==

- `includes/core/` : coeur partagé
- `includes/modules/` : modules autonomes
- `assets/admin/` : UI admin
- `uninstall.php` : nettoyage optionnel des données

== Internationalisation ==

- Chaînes prêtes pour traduction via le text domain `studio-kyne-mini-tools`.
- Chargement du text domain via `load_plugin_textdomain`.

== Release et mise à jour ==

- Workflow GitHub Actions de release: `.github/workflows/release.yml`.
- Support de détection de nouvelle version via GitHub Releases (voir `docs/RELEASE-AND-UPDATES.md`).
