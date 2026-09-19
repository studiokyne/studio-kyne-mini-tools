<!-- Titre de la PR : Conventional Commits, ex. `fix(security): …`, `feat(media): …`, `docs: …` -->

Closes #

## Quoi

<!-- Ce que change la PR, en deux ou trois phrases. -->

## Pourquoi

<!-- Le problème constaté ou le besoin. Si un comportement a cassé, dire ce qui cassait et pourquoi la solution retenue est celle-là. -->

## Comment tester

<!-- Étapes de vérification sur l'environnement local, écrans concernés, cas limites vérifiés. -->

## Checklist

- [ ] Branche créée depuis `dev`, PR vers `dev`
- [ ] Pas de bump de version manuel
- [ ] `composer check` passe (aucun constat, pas de baseline recréée)
- [ ] Nouveaux fichiers PHP : garde `defined( 'ABSPATH' ) || exit;`
- [ ] Icônes : SVG Lucide officiels uniquement
- [ ] `docs/` mis à jour si un piège ou une décision est né de cette PR
