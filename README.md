# Studio Kyne Mini Tools

Plugin WordPress modulaire, leger et performant pour optimiser et ameliorer votre site.

## Fonctionnalités principales

- Architecture modulaire, modules activables a la demande
- Interface admin custom moderne et rapide
- Mises a jour via GitHub (canal Stable et Dev)
- Module Image Optimizer (AVIF/WebP, compression, resize, bulk)

## Installation

1. Telechargez le ZIP depuis les releases GitHub.
2. Uploadez le ZIP dans Extensions > Ajouter > Televerser.
3. Activez le plugin.
4. Ouvrez le menu SKMT dans l’admin.

## Mise a jour

Dans Reglages > Mises a jour GitHub :

- Choisissez le canal Stable (main) ou Dev (pre-release).
- Cliquez sur Verifier les mises a jour pour forcer un check.

## Image Optimizer

- Conversion AVIF/WebP (auto)
- Qualite configurable
- Redimensionnement
- Suppression EXIF
- Alt text auto
- Optimisation en masse

## Architecture

```
studio-kyne-mini-tools/
├── studio-kyne-mini-tools.php
├── includes/
│   ├── Core/
│   ├── Admin/
│   └── Modules/
├── templates/
│   ├── admin/
│   └── components/
└── assets/
    └── admin/
```

## Developpement

### Ajouter un module

1. Creer un dossier dans includes/Modules/MonModule/
2. Implementer ModuleInterface
3. Enregistrer le module via filtre `skmt_module_definitions`

```php
<?php
namespace StudioKyne\MiniTools\Modules\MonModule;

use StudioKyne\MiniTools\Core\AbstractModule;

class Module extends AbstractModule {
    public function init(): void {}
    public function get_settings(): array { return ['enabled' => true]; }
    public function save_settings( array $settings ): bool {
        return update_option( 'skmt_module_mon_module', $settings );
    }
    public function get_admin_css(): array {
        return [ SKMT_ASSETS_URL . 'admin/css/modules/mon-module.css' ];
    }
}
```

### Enregistrement extensible des modules

Le core expose un registre extensible pour eviter de modifier `Core/Modules.php` a chaque nouveau module.

```php
add_filter( 'skmt_module_definitions', function( array $modules ) {
    $modules['mon_module'] = [
        'name'        => __( 'Mon module', 'studio-kyne-mini-tools' ),
        'description' => __( 'Description courte', 'studio-kyne-mini-tools' ),
        'menu_label'  => __( 'Mon module', 'studio-kyne-mini-tools' ),
        'menu_desc'   => __( 'Action principale', 'studio-kyne-mini-tools' ),
        'class'       => 'StudioKyne\\MiniTools\\Modules\\MonModule\\Module',
        'icon'        => 'package',
    ];
    return $modules;
} );
```

### Recommandation architecture module (simple -> complexe)

- Module simple:
  - `Module.php` (hooks + settings + vue)
- Module complexe:
  - `Module.php` (orchestration)
  - `Services/` (metier, API, cron, stockage)
  - `Admin/` (UI, handlers, rendering)
  - `Domain/` (DTO, regles, validation)

Chaque module doit sanitiser ses propres settings dans `save_settings()` (le core n'applique plus de sanitization generique).

## Releases et versioning

- Stable: releases depuis la branche main
- Dev: pre-releases auto depuis la branche dev
- Le ZIP est attache aux releases (studio-kyne-mini-tools.zip)

## Licence

GPL-2.0+
