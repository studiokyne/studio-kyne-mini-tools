# SKMT Modules Roadmap

Mise a jour: 2026-04-25

## Etat actuel

- Module Feedback: suspendu et retire du build/interface/code actif.
- Module Image Optimizer: actif et maintenu.
- Tous les modules sont desactives par defaut a l'installation.

## Priorites produit

1. Module Securite
2. Module Administration (confort / branding / ergonomie)
3. Module Fichiers (explorateur de fichiers)
4. Module Google Reviews

## 1) Module Securite

### Sous-categorie: Authentification

- [x] Deplacer l'URL de connexion
- [x] Limiter les tentatives de connexion
- [x] Forcer un mot de passe fort
- [x] Desactiver l'inscription publique

### Sous-categorie: Hardening

- [x] Desactiver XML-RPC
- [x] Empecher l'enumeration des utilisateurs
- [x] Masquer la version WordPress

### Notes techniques

- Toutes les actions admin et AJAX doivent garder les controles de capacites SKMT.
- Les reglages doivent etre sanitizes de maniere stricte.
- Les changements de securite doivent etre activables/desactivables individuellement.

## 2) Module Administration

Module oriente confort / branding / ergonomie.

### Sous-categorie: Interface

- [ ] Refonte de l'administration
- [ ] Amelioration du design de la page de connexion
- [ ] Masquer la barre d'administration

### Sous-categorie: Experience utilisateur

- [ ] Nettoyer les profils
- [ ] Ajustements UI/UX du back-office
- [ ] Personnalisation de certains ecrans admin

### Notes techniques

- Respecter le design system SKMT existant dans assets/admin/admin.css.
- Garder une granularite de reglages pour ne pas imposer des changements globaux.

## 3) Module Fichiers

Gestionnaire de fichiers accessible depuis la configuration WordPress.

### Fonctionnalites ciblees

- [x] Vue table type explorateur avec:
  - selection par checkbox
  - nom
  - taille
  - date de modification
  - permissions
  - proprietaire
  - actions (supprimer, editer, telecharger)
- [x] Telechargement de dossier avec option ZIP
- [x] Ouverture/edition des fichiers texte et code (php, js, css, txt, config, etc.)
- [x] Sauvegarde du contenu edite
- [x] Upload dans le dossier courant

### Notes techniques

- Restreindre strictement les chemins accessibles (base path controlee).
- Verifier les nonces et capacites sur toutes les operations sensibles.
- Ajouter une protection explicite avant suppression/ecrasement.

## 4) Module Google Reviews

Recuperer et afficher les avis Google d'une fiche My Business.

### Fonctionnalites ciblees

- [ ] Configuration de la source (place id / business profile)
- [ ] Recuperation des avis via API
- [ ] Stockage/cache local pour limiter les appels API
- [ ] Affichage configurable des avis (widget/bloc/shortcode)
- [ ] Reglages de style et de tri de base

### Notes techniques

- Prevoir la gestion de quota et erreurs API.
- Ajouter un mode fallback quand l'API ne repond pas.

## Ordonnancement recommande

### P0

- Demarrer Module Securite (auth + hardening minimal)

### P1

- Lancer Module Administration
- Demarrer socle Module Fichiers (listing + upload + download)

### P2

- Completer Module Fichiers (editeur + zip dossier)
- Lancer Module Google Reviews
