# Design system — composants réutilisables

Tous les composants réutilisables sont définis dans `assets/admin/css/components.css` et `assets/admin/js/admin.js`. **Toujours les utiliser ; ne jamais recoder un équivalent maison.**

## Conventions de classes CSS

L'interface d'administration utilise des classes BEM préfixées `skmt-`. Motifs clés : `skmt-section`, `skmt-section__header`, `skmt-option`, `skmt-option__control`, `skmt-toggle`, `skmt-form__group`, `skmt-badge` (modificateurs : `--success`, `--warning`, `--danger`, `--info`, `--inactive`), `skmt-btn` (modificateurs : `--primary`, `--secondary`, `--sm`).

## Design tokens (custom properties CSS)

Définis dans `assets/admin/css/reset.css` :
- Couleurs : `--skmt-accent`, `--skmt-success`, `--skmt-danger`, `--skmt-warning`
- Neutres : `--skmt-n50` … `--skmt-n950`, `--skmt-surface`, `--skmt-border`, `--skmt-text`, `--skmt-text-secondary`
- Rayons : `--skmt-radius`, `--skmt-radius-sm`, `--skmt-radius-xs`
- Ombres : `--skmt-shadow`, `--skmt-shadow-md`, `--skmt-shadow-lg`

## Modales

Deux systèmes complémentaires, tous deux définis dans `components.css` + `admin.js`.

### 1. Modale de confirmation programmatique

Pour les flux confirmer/annuler simples, sans champ de saisie :

```javascript
window.skmtModal.open({
  title:        "Titre",
  message:      "Message explicatif.",
  confirmLabel: "Confirmer",
  cancelLabel:  "Annuler",
  danger:       true,           // bouton rouge au lieu de bleu
  onConfirm:    function() {},  // callback si l'utilisateur confirme
});
window.skmtModal.close(); // fermeture programmatique
```

Utilise le singleton `#skmt-modal-overlay` de `templates/admin/layout.php`.

### 2. Modale nommée (HTML persistant)

Pour les modales avec champs de formulaire (inputs, selects, etc.) :

```javascript
window.skmtModalOpen('my-modal-id');   // ajoute .is-open
window.skmtModalClose('my-modal-id');  // retire .is-open
```

Structure HTML requise (copier ce gabarit) :

```html
<div class="skmt-modal-overlay" id="my-modal-id" role="dialog" aria-modal="true" aria-labelledby="my-modal-title">
  <div class="skmt-modal">
    <div class="skmt-modal__header">
      <h3 id="my-modal-title" class="skmt-modal__title">Titre</h3>
    </div>
    <div class="skmt-modal__body">
      <!-- contenu, inputs, etc. -->
    </div>
    <div class="skmt-modal__footer">
      <!-- .skmt-modal-close sur le bouton Annuler → fermeture automatique -->
      <button type="button" class="skmt-btn skmt-btn--sm skmt-btn--secondary skmt-modal-close">Annuler</button>
      <button type="button" class="skmt-btn skmt-btn--sm skmt-btn--primary" id="my-confirm-btn">Valider</button>
    </div>
  </div>
</div>
```

La classe `.skmt-modal-close` et le clic hors-boîte sont gérés automatiquement par `admin.js`. Idem pour la touche Échap.

## Formulaires

```html
<!-- Groupe label + input -->
<div class="skmt-form__group">
  <label class="skmt-form__label" for="my-input">Label</label>
  <input type="text" class="skmt-input" id="my-input">
  <p class="skmt-form__help">Texte d'aide optionnel.</p>
</div>

<!-- Select standard -->
<select class="skmt-select">...</select>
<select class="skmt-select skmt-select--sm">...</select>  <!-- petit -->

<!-- Toggle -->
<label class="skmt-toggle">
  <input type="checkbox" name="...">
  <span class="skmt-toggle__slider"></span>
</label>
```

## Onglets

Sous-onglets d'un écran, côté client (`components.css` + `initTabs()` dans `admin.js`, aucune initialisation à écrire) :

```html
<div class="skmt-tabs" role="tablist" data-skmt-tabs="smtp">
  <button type="button" class="skmt-tabs__tab is-active" role="tab" data-skmt-tab="settings">Réglages</button>
  <button type="button" class="skmt-tabs__tab" role="tab" data-skmt-tab="log">Journal</button>
</div>
<div class="skmt-tabs__panel" role="tabpanel" data-skmt-tabs-group="smtp" data-skmt-tab-panel="settings">…</div>
<div class="skmt-tabs__panel" role="tabpanel" data-skmt-tabs-group="smtp" data-skmt-tab-panel="log" hidden>…</div>
```

- Les panneaux restent dans le DOM : dans un formulaire de réglages, **tous** les champs partent à l'enregistrement, quel que soit l'onglet ouvert.
- Le dernier onglet est rappelé par `sessionStorage` (`skmt-tab:{groupe}`) : la redirection après enregistrement revient sur l'écran, pas sur l'onglet.
- Un champ invalide dans un panneau masqué ouvre son onglet : sans ça, le navigateur bloque la soumission sans pouvoir montrer le champ fautif.
- Chaque changement émet `skmt:tab` (`detail.group`, `detail.name`) sur la barre, qui remonte jusqu'au `document` : un module peut attendre l'ouverture d'un onglet pour charger ses données. `window.skmtTabs.activate(groupe, nom)` ouvre un onglet par programme.
- Dans un écran de module, la barre se place **dans** le formulaire, avant `.skmt-module-form__scroll`, pour rester fixe pendant le défilement.

## Boutons

```html
<button class="skmt-btn skmt-btn--primary">Principal</button>
<button class="skmt-btn skmt-btn--secondary">Secondaire</button>
<button class="skmt-btn skmt-btn--danger">Danger</button>
<!-- Tailles : ajouter --sm pour petit -->
```

Un bouton peut être un `<a>` (« Ouvrir la médiathèque … »). `buttons.css` redéclare donc la couleur sur `a.skmt-btn:hover/:focus/:active` par variante : sans ça, `a:hover { color:#135e96 }` de wp-admin l'emporte (l'état ajoute une pseudo-classe à la spécificité de `.skmt-btn--primary`) et le libellé vire au bleu au survol.

## Tooltips

```html
<button data-skmt-tip="Exporter ce menu en .json">…</button>
<button data-skmt-tip="…" data-skmt-tip-placement="right">…</button>   <!-- top par défaut -->
<?php echo $this->render_help_tip( __( 'Précision', 'studio-kyne-mini-tools' ) ); ?>  <!-- marqueur (i), dans le <label> -->
```

Système maison (pas de tippy.js : il tire Popper, et le plugin n'a ni build ni bundler), défini dans `components.css` + `admin.js`. **Aucune initialisation** : tout passe par délégation sur le `document`, donc le markup rendu en JS après coup (arbre du créateur de menu, listes AJAX) est couvert sans y penser. API : `window.skmtTooltip.hide()`, `.refresh()`, `.set(el, texte)`.

Trois points structurels :
- Le singleton est en `position:fixed` + `translate3d`, appendé au `<body>` : un tooltip enfant serait rogné par la première colonne en `overflow:hidden` (elles le sont toutes), et en `absolute` il devrait connaître les décalages de chacun de ses parents. Contrepartie : il faut suivre le défilement, d'où le recalcul sur `scroll`/`resize` throttlé en `requestAnimationFrame`, et le masquage automatique quand la référence sort de l'écran ou du DOM.
- La bascule (`top` → `bottom`…) n'a lieu que si le côté opposé offre **plus** de place, sinon la boîte oscille entre deux positions également trop petites. Après recadrage dans la fenêtre, la flèche est repositionnée sur la référence : sans ça elle pointe à côté dans les coins.
- Un `title` sur le même élément est retiré au premier survol (sauvegardé dans `data-skmt-tip-title`), sinon la bulle native double la nôtre. Les modules qui tournent **hors** des pages SKMT — `media.js`, chargé par `wp_enqueue_media` là où `admin.js` est absent — gardent donc les deux attributs : `title` sert de repli, `data-skmt-tip` prend le relais quand notre JS est là.

Pour une **précision secondaire** — la réserve qui compte mais qui allongerait la ligne — `Admin::render_help_tip( $texte )` pose un marqueur dans le `<label>` : l'icône Lucide `info` (`.skmt-tip-info`), pas une pastille dessinée en CSS ni un soulignement pointillé sous le libellé — la première fabrique une fausse icône, le second salit la ligne et ne se lit pas comme un contrôle. `menu-creator.js` en a l'équivalent JS (`helpTip()`, icône servie par `window.skmtLucide.info`). Ce qui **décrit** l'option reste dans le texte d'aide visible ; seul le détail passe sous le marqueur.

Le survol comme le focus déclenchent la bulle (ce que `title` ne fait pas) ; un tooltip déjà ouvert enchaîne sans délai sur le suivant. Sur les pages du plugin, préférer `data-skmt-tip` à `title` pour tout contrôle en icône seule.

## Toasts / notifications

```javascript
window.skmtShowToast("Message", "success"); // success | error | info | warning
```

Défini dans `assets/admin/js/notifications.js`, chargé globalement. Pour les notices qui doivent survivre à un rechargement, voir les notices persistantes dans [core.md](core.md#notices-persistantes).
