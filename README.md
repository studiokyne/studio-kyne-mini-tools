# Studio Kyne Mini Tools

[![Release](https://img.shields.io/github/v/release/studiokyne/studio-kyne-mini-tools?include_prereleases=false)](https://github.com/studiokyne/studio-kyne-mini-tools/releases)
[![Dev Releases](https://img.shields.io/github/v/release/studiokyne/studio-kyne-mini-tools?include_prereleases=true&label=dev)](https://github.com/studiokyne/studio-kyne-mini-tools/releases)
[![License](https://img.shields.io/github/license/studiokyne/studio-kyne-mini-tools)](LICENSE)
[![WP Version](https://img.shields.io/badge/wordpress-5.8%2B-blue)](https://wordpress.org)
[![PHP Version](https://img.shields.io/badge/php-7.4%2B-777bb4)](https://www.php.net)

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
3. Enregistrer le module dans Core/Modules.php

```php
<?php
namespace StudioKyne\MiniTools\Modules\MonModule;

use StudioKyne\MiniTools\Core\ModuleInterface;

class Module implements ModuleInterface {
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

## Releases et versioning

- Stable: releases depuis la branche main
- Dev: pre-releases auto depuis la branche dev
- Le ZIP est attache aux releases (studio-kyne-mini-tools.zip)

## Licence

GPL-2.0+
