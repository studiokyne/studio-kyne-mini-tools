# Module Database

`includes/Modules/Database/Module.php` + `assets/admin/js/modules/database.js`. Explorateur/éditeur de tables au-dessus de `$wpdb`. `Module.php` est une fine couche AJAX (pas de réglages — `get_settings()`/`save_settings()`/`get_defaults()` sont des no-ops) ; toute l'interface est construite côté client depuis un gabarit à deux volets (`settings-template.php` : liste des tables en barre latérale + onglets Données/Structure/Requête SQL). Capacité requise : `manage_network_options` sous multisite (voir [core.md](../core.md#capacité-requise)).

Actions AJAX : `skmt_db_get_tables`, `skmt_db_get_rows`, `skmt_db_get_structure`, `skmt_db_update_row`, `skmt_db_delete_row`, `skmt_db_insert_row`, `skmt_db_truncate`, `skmt_db_drop_table`, `skmt_db_export_sql`, `skmt_db_run_query`.

## Modèle de sécurité

Tout côté serveur, jamais de confiance au client.

- **Noms de tables/colonnes** : passent par `read_table()` → `validate_table()` (vérification contre `information_schema.tables` pour la base courante) et sont filtrés contre `get_columns_map()` (`SHOW COLUMNS`). Utilise `sanitize_text_field` (PAS `sanitize_key`) pour que les identifiants en casse mixte survivent.
- **Typage** : `column_format()` associe chaque colonne à `%d`/`%f`/`%s` pour `$wpdb->update`/`insert`/`delete` ; NULL est explicite (drapeau `set_null` / valeur null), rejeté côté serveur si la colonne est `NOT NULL`.
- **Éditeur SQL libre** (`ajax_run_query`). Tous les garde-fous testent la requête **normalisée** par `normalize_sql()`, jamais la chaîne brute ; c'est bien l'originale qui est exécutée ensuite.
  - `normalize_sql()` fait trois passes, et **l'ordre est le fond du sujet** : neutraliser les littéraux (`'…'`, `"…"`, `` `…` ``) d'abord — une ouverture de commentaire dans une chaîne n'ouvre rien, la traiter comme telle tronquerait la requête normalisée et masquerait la suite ; puis retirer les commentaires (`/* */`, `--`, `#`) ; puis compacter les espaces. Le trou exploité par l'audit était là : MySQL accepte un commentaire vide comme séparateur de mots, si bien qu'un `DROP` suivi d'un commentaire vide puis de `DATABASE` ne ressemblait à aucun mot-clé interdit tout en s'exécutant comme `DROP DATABASE`. **Étendre la liste noire n'y change rien** — il y a une infinité de façons d'écrire l'espace.
  - `find_forbidden_keyword()` compare des **mots entiers** (« migrant » ne contient plus « GRANT »). `FORBIDDEN_KEYWORDS` couvre aussi les accès disque du serveur MySQL : `INTO OUTFILE`, `INTO DUMPFILE`, `LOAD DATA`, `LOAD_FILE`.
  - `is_read_query()` ne se fonde **pas** sur le premier mot : un `SELECT` peut écrire (`INTO OUTFILE`), un `WITH … DELETE` s'ouvre sur un mot de lecture, et `SELECT 1; DELETE …` cache une seconde instruction. Il faut donc les trois : mot d'ouverture de lecture, aucun verbe d'écriture ailleurs, aucun point-virgule interne. Le sens de l'erreur est assumé — classer une lecture en écriture coûte une confirmation de plus, l'inverse coûte une table. `CREATE`/`DROP` sont volontairement hors de la liste des verbes d'écriture, pour que `SHOW CREATE TABLE` reste une lecture.
  - Toute écriture exige `$_POST['confirm']` (`database.js` pré-confirme via `window.skmtModal` et **miroite** `normalize_sql()`/`is_read_query()` — sinon l'interface croit la requête inoffensive et le serveur répond `needs_confirm`) ; sans lui, retour `needs_confirm`.
  - Le plafond `QUERY_ROW_CAP` (1000) ne s'applique qu'aux requêtes ouvertes par `SELECT` ou `WITH` : `SHOW`, `DESCRIBE` et `EXPLAIN` rendent un jeu déjà fini et **n'acceptent pas de `LIMIT`** — l'ajouter transformait `SHOW CREATE TABLE x` en erreur de syntaxe. La présence d'un `LIMIT` se lit sur la forme normalisée, sinon un `/* LIMIT 1 */` en commentaire faisait sauter le plafond.
- `friendly_db_error()` traduit les erreurs MySQL courantes (entrée dupliquée, contrainte FK, NOT NULL, valeur incorrecte) en messages lisibles.
- **Export** (`ajax_export_sql`) : émission des valeurs selon le type (numériques sans guillemets, hex `0x…` pour le binaire, `NULL`, liste de colonnes explicite, `SET NAMES utf8mb4`).

## Interface

La hauteur de la mise en page est en CSS pur via la chaîne flex `.skmt-admin-main:has(.skmt-db)` (pas de handler de redimensionnement JS). L'historique SQL est local au navigateur (`localStorage`, avec un bouton d'effacement) — pas persisté côté serveur. Toutes les chaînes JS visibles passent par `skmtAdmin.i18n` (alimenté par `get_admin_js_data()`). Pas d'édition dans l'onglet Structure, pas d'export CSV.

À venir : onglet Nettoyage (issue #16, remplacement de WP-Sweep).
