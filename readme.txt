=== Studio Kyne Mini Tools ===
Contributors: studiokyne
Tags: media, images, optimization, admin
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Suite modulaire d'outils WordPress par Studio Kyne.

== Description ==

Studio Kyne Mini Tools (SKMT) est une base de plugin modulaire pensee pour accueillir plusieurs outils activables :
- Image Optimizer
- Securite (en cours)
- Administration (en cours)
- Fichiers (en cours)
- Google Reviews (en cours)

La V1 inclut deja :
- un coeur SKMT propre et extensible
- une gestion des modules activables
- une interface admin unifiee
- un module d optimisation d images
- des modules prioritaires en mode squelette pour accelerer la roadmap

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

= Securite =
- URL de connexion personnalisee (avec option blocage wp-login.php)
- limitation des tentatives de connexion
- politique de mot de passe fort
- desactivation inscription publique
- desactivation XML-RPC
- protection anti-enumeration utilisateur
- masquage de la version WordPress

= Administration =
- squelette admin et roadmap confort/branding/UX

= Fichiers =
- explorateur en table (selection, nom, taille, date, permissions, proprietaire)
- actions fichier/dossier: telechargement, suppression
- telechargement ZIP des dossiers et des selections
- editeur integre pour fichiers texte/code + sauvegarde
- upload dans le dossier courant

= Google Reviews =
- squelette admin et roadmap integration Google My Business

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
