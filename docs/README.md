# Documentation technique

Documentation interne du plugin, exclue des ZIP de release. `CLAUDE.md` à la racine porte les règles absolues et renvoie ici pour le détail.

- [core.md](core.md) — séquence de démarrage, autoloader, stockage des réglages, contrat `AbstractModule`, formulaires et endpoints AJAX, icônes, notices, updater, outillage (Composer, PHPCS, PHPStan, baselines).
- [design-system.md](design-system.md) — classes CSS, tokens, modales, formulaires, boutons, tooltips, toasts.

## Modules

| Module | Page | Ce qu'on y trouve |
|---|---|---|
| Security | [modules/security.md](modules/security.md) | résolution d'IP, rate limiter (dont mots de passe d'application), URL de connexion, anti-énumération, `X-Powered-By` |
| WhiteLabel | [modules/white-label.md](modules/white-label.md) | barre d'admin, profil, avatars locaux, `MenuProfileManager` et son cache |
| ImageOptimizer | [modules/image-optimizer.md](modules/image-optimizer.md) | détection des encodeurs, `UrlRewriter`, bulk, assainisseur SVG |
| MenuCreator | [modules/menu-creator.md](modules/menu-creator.md) | application des profils, blocage d'accès, export, historique, icônes |
| Login | [modules/login.md](modules/login.md) | hooks `login_*` |
| Files | [modules/files.md](modules/files.md) | `FileManager`, CodeMirror du cœur, `DISALLOW_FILE_*` |
| Media | [modules/media.md](modules/media.md) | taxonomie de dossiers, filtrage serveur, capacités, drag & drop |
| Database | [modules/database.md](modules/database.md) | validation des identifiants, `normalize_sql()`, éditeur SQL, export |

Chaque page de module documente les **pièges et décisions** (ce qui a cassé, pourquoi la solution est celle-là), pas le code lui-même : le code se lit dans `includes/Modules/<Module>/`.
