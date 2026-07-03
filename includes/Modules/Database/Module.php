<?php
namespace StudioKyne\MiniTools\Modules\Database;

use StudioKyne\MiniTools\Core\AbstractModule;

/**
 * Module Base de données — exploration, édition et export des tables WordPress.
 */
class Module extends AbstractModule {

	/** Nb de lignes maximum renvoyées par une requête SELECT libre sans LIMIT explicite. */
	const QUERY_ROW_CAP = 1000;

	/** Mots-clés interdits dans l'éditeur SQL libre (opérations hors périmètre / destructrices au niveau serveur). */
	const FORBIDDEN_KEYWORDS = [ 'DROP DATABASE', 'DROP SCHEMA', 'CREATE USER', 'DROP USER', 'GRANT', 'REVOKE', 'SHUTDOWN', 'CREATE DATABASE' ];

	public function init(): void {
		add_action( 'wp_ajax_skmt_db_get_tables',    [ $this, 'ajax_get_tables' ] );
		add_action( 'wp_ajax_skmt_db_get_rows',      [ $this, 'ajax_get_rows' ] );
		add_action( 'wp_ajax_skmt_db_get_structure', [ $this, 'ajax_get_structure' ] );
		add_action( 'wp_ajax_skmt_db_update_row',    [ $this, 'ajax_update_row' ] );
		add_action( 'wp_ajax_skmt_db_delete_row',    [ $this, 'ajax_delete_row' ] );
		add_action( 'wp_ajax_skmt_db_insert_row',    [ $this, 'ajax_insert_row' ] );
		add_action( 'wp_ajax_skmt_db_truncate',      [ $this, 'ajax_truncate_table' ] );
		add_action( 'wp_ajax_skmt_db_drop_table',    [ $this, 'ajax_drop_table' ] );
		add_action( 'wp_ajax_skmt_db_export_sql',    [ $this, 'ajax_export_sql' ] );
		add_action( 'wp_ajax_skmt_db_run_query',     [ $this, 'ajax_run_query' ] );
	}

	public function get_settings(): array { return []; }
	public function save_settings( array $s ): bool { return false; }
	public static function get_defaults(): array { return []; }
	public static function get_uninstall_keys(): array { return [ 'options' => [], 'meta' => [] ]; }

	public function get_admin_css(): array {
		return [ SKMT_ASSETS_URL . 'admin/css/modules/database.css' ];
	}

	public function get_admin_js(): array {
		return [ SKMT_ASSETS_URL . 'admin/js/modules/database.js' ];
	}

	public function get_admin_js_data(): array {
		return [
			'i18n' => [
				'confirmDelete'    => __( 'Supprimer cette ligne ?', 'studio-kyne-mini-tools' ),
				'confirmTruncate'  => __( 'Vider la table ? Cette action est irréversible.', 'studio-kyne-mini-tools' ),
				'queryWarning'     => __( 'Attention : les requêtes de modification (UPDATE, DELETE, DROP…) s\'exécutent directement sur la base de données. Aucun undo possible.', 'studio-kyne-mini-tools' ),
				'confirmWrite'     => __( 'Cette requête modifie la base de données et est irréversible. Confirmer l\'exécution ?', 'studio-kyne-mini-tools' ),
				// Actions génériques
				'confirm'          => __( 'Confirmer', 'studio-kyne-mini-tools' ),
				'cancel'           => __( 'Annuler', 'studio-kyne-mini-tools' ),
				'delete'           => __( 'Supprimer', 'studio-kyne-mini-tools' ),
				'execute'          => __( 'Exécuter', 'studio-kyne-mini-tools' ),
				// États / feedback
				'loading'          => __( 'Chargement…', 'studio-kyne-mini-tools' ),
				'executing'        => __( 'Exécution…', 'studio-kyne-mini-tools' ),
				'inserting'        => __( 'Insertion…', 'studio-kyne-mini-tools' ),
				'rowAdded'         => __( 'Ligne ajoutée', 'studio-kyne-mini-tools' ),
				'rowUpdated'       => __( 'Ligne mise à jour', 'studio-kyne-mini-tools' ),
				'rowDeleted'       => __( 'Ligne supprimée', 'studio-kyne-mini-tools' ),
				'tableTruncated'   => __( 'Table vidée', 'studio-kyne-mini-tools' ),
				'tableDropped'     => __( 'Table supprimée', 'studio-kyne-mini-tools' ),
				'error'            => __( 'Erreur', 'studio-kyne-mini-tools' ),
				'networkError'     => __( 'Erreur réseau', 'studio-kyne-mini-tools' ),
				// Libellés de tableau / recherche
				'noTables'         => __( 'Aucune table trouvée.', 'studio-kyne-mini-tools' ),
				'noRows'           => __( 'Aucune ligne.', 'studio-kyne-mini-tools' ),
				'noColumn'         => __( 'Aucune colonne.', 'studio-kyne-mini-tools' ),
				'noHistory'        => __( 'Aucun historique.', 'studio-kyne-mini-tools' ),
				'clearHistory'     => __( 'Vider l\'historique', 'studio-kyne-mini-tools' ),
				'searchInTable'    => __( 'Rechercher dans la table…', 'studio-kyne-mini-tools' ),
				'rowsLabel'        => __( 'lignes', 'studio-kyne-mini-tools' ),
				'perPageLabel'     => __( 'Lignes / page', 'studio-kyne-mini-tools' ),
				'setNull'          => __( 'Définir NULL', 'studio-kyne-mini-tools' ),
				'queryTruncated'   => __( 'Résultat tronqué à %d lignes. Ajoutez une clause LIMIT pour cibler votre requête.', 'studio-kyne-mini-tools' ),
			],
		];
	}

	/* ================================================================
	 * SÉCURITÉ
	 * ================================================================ */

	private function guard(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) ] );
		}
		// Empêche $wpdb->print_error() d'ÉCHO du HTML d'erreur avant notre JSON
		// (sinon la réponse est corrompue → « Erreur réseau » côté JS au lieu du message SQL).
		global $wpdb;
		$wpdb->suppress_errors( true );
	}

	/* ================================================================
	 * AJAX — LISTE DES TABLES
	 * ================================================================ */

	public function ajax_get_tables(): void {
		$this->guard();

		global $wpdb;
		$prefix = $wpdb->prefix;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$tables_raw = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );

		$tables = [];
		foreach ( (array) $tables_raw as $t ) {
			$name  = $t['Name'];
			$is_wp = str_starts_with( $name, $prefix );

			$tables[] = [
				'name'         => $name,
				'rows'         => (int) $t['Rows'],
				'size'         => ( (int) $t['Data_length'] + (int) $t['Index_length'] ),
				'engine'       => $t['Engine'],
				'is_wp_prefix' => $is_wp,
				'prefix'       => $is_wp ? $prefix : '',
				'short_name'   => $is_wp ? substr( $name, strlen( $prefix ) ) : $name,
			];
		}

		wp_send_json_success( [ 'tables' => $tables, 'prefix' => $prefix ] );
	}

	/* ================================================================
	 * AJAX — À IMPLÉMENTER (prompts 1-07 / 1-08)
	 * ================================================================ */

	/**
	 * Vérifie qu'une table existe dans la base courante. Retourne le nom validé
	 * (échappable en backticks) ou null.
	 */
	private function validate_table( string $table ): ?string {
		if ( '' === $table ) {
			return null;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s',
			$table
		) );
		return $exists ? $table : null;
	}

	/**
	 * Lit et valide le paramètre `table` du POST. Utilise sanitize_text_field
	 * (et NON sanitize_key qui force en minuscules et casserait les noms de
	 * tables/colonnes sensibles à la casse selon le système de fichiers MySQL).
	 * La validation contre information_schema garantit que les backticks sont sûrs.
	 */
	private function read_table(): ?string {
		$table = isset( $_POST['table'] ) ? sanitize_text_field( wp_unslash( $_POST['table'] ) ) : '';
		return $this->validate_table( $table );
	}

	/**
	 * Récupère les colonnes réelles d'une table indexées par nom (whitelist + typage).
	 * @return array<string,array> Field => ligne SHOW COLUMNS.
	 */
	private function get_columns_map( string $table ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$columns = $wpdb->get_results( 'SHOW COLUMNS FROM `' . $table . '`', ARRAY_A );
		$map     = [];
		foreach ( (array) $columns as $col ) {
			$map[ $col['Field'] ] = $col;
		}
		return $map;
	}

	/** Détermine le placeholder $wpdb (%d/%f/%s) adapté au type SQL d'une colonne. */
	private function column_format( array $col ): string {
		$type = strtolower( $col['Type'] ?? '' );
		if ( preg_match( '/^(tinyint|smallint|mediumint|int|integer|bigint|bit|year)\b/', $type ) ) {
			return '%d';
		}
		if ( preg_match( '/^(decimal|dec|numeric|float|double|real)\b/', $type ) ) {
			return '%f';
		}
		return '%s';
	}

	/** Traduit les erreurs MySQL courantes en messages lisibles (le message brut reste en repli). */
	private function friendly_db_error( string $raw, string $fallback ): string {
		if ( '' === $raw ) {
			return $fallback;
		}
		if ( stripos( $raw, 'Duplicate entry' ) !== false ) {
			return __( 'Cette valeur existe déjà (contrainte d\'unicité).', 'studio-kyne-mini-tools' ) . ' — ' . $raw;
		}
		if ( stripos( $raw, 'foreign key' ) !== false ) {
			return __( 'Contrainte de clé étrangère non respectée.', 'studio-kyne-mini-tools' ) . ' — ' . $raw;
		}
		if ( stripos( $raw, 'cannot be null' ) !== false || stripos( $raw, "doesn't have a default" ) !== false ) {
			return __( 'Un champ obligatoire est manquant.', 'studio-kyne-mini-tools' ) . ' — ' . $raw;
		}
		if ( stripos( $raw, 'Incorrect' ) !== false && stripos( $raw, 'value' ) !== false ) {
			return __( 'Valeur de type incorrect pour une colonne.', 'studio-kyne-mini-tools' ) . ' — ' . $raw;
		}
		return $raw;
	}

	public function ajax_get_rows(): void {
		$this->guard();

		global $wpdb;
		$page      = max( 1, (int) ( $_POST['page'] ?? 1 ) );
		$per_page  = min( 200, max( 10, (int) ( $_POST['per_page'] ?? 50 ) ) );
		$search    = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$order_col = isset( $_POST['order_col'] ) ? sanitize_text_field( wp_unslash( $_POST['order_col'] ) ) : '';
		$order_dir = strtoupper( isset( $_POST['order_dir'] ) ? sanitize_text_field( wp_unslash( $_POST['order_dir'] ) ) : 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';

		$table = $this->read_table();
		if ( null === $table ) {
			wp_send_json_error( [ 'message' => __( 'Table introuvable.', 'studio-kyne-mini-tools' ) ] );
		}

		// Colonnes — $table validé via information_schema, backticks sûrs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$columns   = $wpdb->get_results( 'SHOW COLUMNS FROM `' . $table . '`', ARRAY_A );
		$col_names = array_column( $columns, 'Field' );
		$primary   = '';
		foreach ( $columns as $col ) {
			if ( 'PRI' === $col['Key'] ) { $primary = $col['Field']; break; }
		}

		// Recherche : WHERE sur toutes les colonnes de type texte (LIKE).
		$where = '';
		if ( '' !== $search ) {
			$text_cols = array_filter( $columns, static fn( $c ) => str_contains( strtolower( $c['Type'] ), 'char' ) || str_contains( strtolower( $c['Type'] ), 'text' ) );
			$clauses   = array_map( static fn( $c ) => '`' . $c['Field'] . '` LIKE ' . $wpdb->prepare( '%s', '%' . $wpdb->esc_like( $search ) . '%' ), $text_cols );
			if ( $clauses ) {
				$where = ' WHERE ' . implode( ' OR ', $clauses );
			}
		}

		// ORDER BY.
		$order = '';
		if ( $order_col && in_array( $order_col, $col_names, true ) ) {
			$order = ' ORDER BY `' . $order_col . '` ' . $order_dir;
		}

		// COUNT.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $table . '`' . $where );

		// ROWS.
		$offset = ( $page - 1 ) * $per_page;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( 'SELECT * FROM `' . $table . '`' . $where . $order . ' LIMIT ' . $per_page . ' OFFSET ' . $offset, ARRAY_A );

		wp_send_json_success( [
			'columns'  => $col_names,
			'primary'  => $primary,
			'rows'     => $rows,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => (int) ceil( $total / $per_page ),
		] );
	}

	public function ajax_get_structure(): void {
		$this->guard();

		global $wpdb;
		$table = $this->read_table();
		if ( null === $table ) {
			wp_send_json_error( [ 'message' => __( 'Table introuvable.', 'studio-kyne-mini-tools' ) ] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$columns = $wpdb->get_results( 'SHOW FULL COLUMNS FROM `' . $table . '`', ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$indexes = $wpdb->get_results( 'SHOW INDEX FROM `' . $table . '`', ARRAY_A );

		wp_send_json_success( [ 'columns' => $columns, 'indexes' => $indexes ] );
	}

	public function ajax_update_row(): void {
		$this->guard();

		global $wpdb;
		$primary_col = isset( $_POST['primary_col'] ) ? sanitize_text_field( wp_unslash( $_POST['primary_col'] ) ) : '';
		$primary_val = isset( $_POST['primary_val'] ) ? wp_unslash( $_POST['primary_val'] ) : '';
		$col         = isset( $_POST['col'] ) ? sanitize_text_field( wp_unslash( $_POST['col'] ) ) : '';
		$value       = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
		$set_null    = ! empty( $_POST['set_null'] );

		$table = $this->read_table();
		if ( null === $table || ! $primary_col || ! $col ) {
			wp_send_json_error( [ 'message' => __( 'Paramètres invalides.', 'studio-kyne-mini-tools' ) ] );
		}

		// Whitelist colonne + clé primaire contre les colonnes réelles de la table.
		$columns = $this->get_columns_map( $table );
		if ( ! isset( $columns[ $col ], $columns[ $primary_col ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Colonne inconnue.', 'studio-kyne-mini-tools' ) ] );
		}

		// NULL explicite → valeur null (interdit si la colonne n'accepte pas NULL).
		if ( $set_null ) {
			if ( 'YES' !== ( $columns[ $col ]['Null'] ?? 'NO' ) ) {
				wp_send_json_error( [ 'message' => __( 'Cette colonne n\'accepte pas la valeur NULL.', 'studio-kyne-mini-tools' ) ] );
			}
			$data    = [ $col => null ];
			$formats = null; // laisse $wpdb produire NULL.
		} else {
			$data    = [ $col => $value ];
			$formats = [ $this->column_format( $columns[ $col ] ) ];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update( $table, $data, [ $primary_col => $primary_val ], $formats, [ $this->column_format( $columns[ $primary_col ] ) ] );
		if ( false === $result ) {
			wp_send_json_error( [ 'message' => $wpdb->last_error ] );
		}
		wp_send_json_success( [ 'is_null' => $set_null ] );
	}

	public function ajax_delete_row(): void {
		$this->guard();

		global $wpdb;
		$primary_col = isset( $_POST['primary_col'] ) ? sanitize_text_field( wp_unslash( $_POST['primary_col'] ) ) : '';
		$primary_val = isset( $_POST['primary_val'] ) ? wp_unslash( $_POST['primary_val'] ) : '';

		$table = $this->read_table();
		if ( null === $table || ! $primary_col ) {
			wp_send_json_error( [ 'message' => __( 'Paramètres invalides.', 'studio-kyne-mini-tools' ) ] );
		}

		$columns = $this->get_columns_map( $table );
		if ( ! isset( $columns[ $primary_col ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Colonne inconnue.', 'studio-kyne-mini-tools' ) ] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete( $table, [ $primary_col => $primary_val ], [ $this->column_format( $columns[ $primary_col ] ) ] );
		if ( false === $result ) {
			wp_send_json_error( [ 'message' => $wpdb->last_error ] );
		}
		wp_send_json_success();
	}

	public function ajax_insert_row(): void {
		$this->guard();

		$table = $this->read_table();
		if ( null === $table ) {
			wp_send_json_error( [ 'message' => __( 'Table introuvable.', 'studio-kyne-mini-tools' ) ] );
		}

		global $wpdb;

		// Champs soumis (col => valeur brute) + colonnes explicitement NULL.
		$fields = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : [];
		$nulls  = isset( $_POST['nulls'] ) && is_array( $_POST['nulls'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['nulls'] ) ) : [];

		// Colonnes réelles de la table (whitelist + typage).
		$columns = $this->get_columns_map( $table );

		$data    = [];
		$formats = [];
		foreach ( $columns as $field => $col ) {
			$extra = strtolower( $col['Extra'] ?? '' );

			// Colonne explicitement NULL → valeur null typée (refusée si NOT NULL sans défaut).
			if ( in_array( $field, $nulls, true ) ) {
				if ( 'YES' !== ( $col['Null'] ?? 'NO' ) ) {
					wp_send_json_error( [ 'message' => sprintf( __( 'La colonne « %s » n\'accepte pas NULL.', 'studio-kyne-mini-tools' ), $field ) ] );
				}
				$data[ $field ] = null;
				$formats[]      = $this->column_format( $col );
				continue;
			}
			// Auto-increment laissé vide → délégué à MySQL.
			if ( str_contains( $extra, 'auto_increment' ) && ( ! isset( $fields[ $field ] ) || '' === $fields[ $field ] ) ) {
				continue;
			}
			if ( ! array_key_exists( $field, $fields ) ) {
				continue;
			}
			$data[ $field ] = $fields[ $field ];
			$formats[]      = $this->column_format( $col );
		}

		if ( empty( $data ) ) {
			wp_send_json_error( [ 'message' => __( 'Aucune valeur à insérer.', 'studio-kyne-mini-tools' ) ] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, $data, $formats );
		if ( false === $result ) {
			wp_send_json_error( [ 'message' => $this->friendly_db_error( $wpdb->last_error, __( 'Insertion échouée.', 'studio-kyne-mini-tools' ) ) ] );
		}
		wp_send_json_success( [ 'insert_id' => $wpdb->insert_id ] );
	}

	public function ajax_truncate_table(): void {
		$this->guard();

		global $wpdb;
		$table = $this->read_table();
		if ( null === $table ) {
			wp_send_json_error( [ 'message' => __( 'Table introuvable.', 'studio-kyne-mini-tools' ) ] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query( 'TRUNCATE TABLE `' . $table . '`' );
		if ( false === $result ) {
			wp_send_json_error( [ 'message' => $wpdb->last_error ] );
		}
		wp_send_json_success();
	}

	public function ajax_drop_table(): void {
		$this->guard();

		global $wpdb;
		$table = $this->read_table();
		if ( null === $table ) {
			wp_send_json_error( [ 'message' => __( 'Table introuvable.', 'studio-kyne-mini-tools' ) ] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query( 'DROP TABLE `' . $table . '`' );
		if ( false === $result ) {
			wp_send_json_error( [ 'message' => $wpdb->last_error ] );
		}
		wp_send_json_success();
	}

	public function ajax_run_query(): void {
		$this->guard();

		global $wpdb;
		$sql = isset( $_POST['sql'] ) ? trim( (string) wp_unslash( $_POST['sql'] ) ) : '';
		if ( '' === $sql ) {
			wp_send_json_error( [ 'message' => __( 'Requête vide.', 'studio-kyne-mini-tools' ) ] );
		}

		// Garde-fou 1 : opérations interdites (gestion des bases/utilisateurs, arrêt serveur…).
		$upper = strtoupper( $sql );
		foreach ( self::FORBIDDEN_KEYWORDS as $kw ) {
			if ( str_contains( $upper, $kw ) ) {
				wp_send_json_error( [ 'message' => sprintf( __( 'Opération interdite dans cet éditeur : %s.', 'studio-kyne-mini-tools' ), $kw ) ] );
			}
		}

		// Détecter si c'est une requête de lecture.
		$is_select = preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN|PRAGMA|WITH)\b/i', $sql );

		// Garde-fou 2 : toute requête d'écriture exige une confirmation explicite côté client.
		if ( ! $is_select && empty( $_POST['confirm'] ) ) {
			wp_send_json_error( [
				'message'      => __( 'Cette requête modifie la base. Confirmation requise.', 'studio-kyne-mini-tools' ),
				'needs_confirm' => true,
			] );
		}

		$wpdb->flush();

		if ( $is_select ) {
			// Garde-fou 3 : borne mémoire — on plafonne les SELECT sans LIMIT explicite.
			$capped = $sql;
			$truncated = false;
			$bare = rtrim( $sql, "; \t\n\r" );
			if ( ! preg_match( '/\bLIMIT\b/i', $bare ) ) {
				$capped    = $bare . ' LIMIT ' . self::QUERY_ROW_CAP;
				$truncated = true;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results( $capped, ARRAY_A );
			if ( $wpdb->last_error ) {
				wp_send_json_error( [ 'message' => $wpdb->last_error ] );
			}
			$count = count( (array) $results );
			wp_send_json_success( [
				'type'      => 'select',
				'columns'   => ! empty( $results ) ? array_keys( $results[0] ) : [],
				'rows'      => $results,
				'total'     => $count,
				'truncated' => $truncated && $count >= self::QUERY_ROW_CAP ? self::QUERY_ROW_CAP : 0,
			] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query( $sql );
		if ( false === $result ) {
			wp_send_json_error( [ 'message' => $this->friendly_db_error( $wpdb->last_error, __( 'Requête échouée.', 'studio-kyne-mini-tools' ) ) ] );
		}
		wp_send_json_success( [
			'type'      => 'write',
			'affected'  => $result,
			'insert_id' => $wpdb->insert_id,
		] );
	}

	public function ajax_export_sql(): void {
		check_ajax_referer( 'skmt_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissions insuffisantes.', 'studio-kyne-mini-tools' ) );
		}

		global $wpdb;
		$table = $this->read_table();
		if ( null === $table ) {
			wp_die( esc_html__( 'Table invalide.', 'studio-kyne-mini-tools' ) );
		}

		$filename = $table . '_' . gmdate( 'Y-m-d_His' ) . '.sql';

		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		// Colonnes + typage : détermine quelles valeurs sont numériques (non quotées) ou binaires (hex).
		$columns   = $this->get_columns_map( $table );
		$col_names = array_keys( $columns );
		$is_num    = [];
		$is_binary = [];
		foreach ( $columns as $field => $col ) {
			$type              = strtolower( $col['Type'] ?? '' );
			$is_num[ $field ]  = (bool) preg_match( '/^(tinyint|smallint|mediumint|int|integer|bigint|decimal|dec|numeric|float|double|real|bit|year)\b/', $type );
			$is_binary[ $field ] = (bool) preg_match( '/(blob|binary)\b/', $type );
		}
		// Liste de colonnes échappées pour un INSERT explicite (réimportable même si l'ordre/nombre change).
		$col_list = '`' . implode( '`, `', array_map( 'esc_sql', $col_names ) ) . '`';

		// Structure de la table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$create = $wpdb->get_row( 'SHOW CREATE TABLE `' . $table . '`', ARRAY_N );

		echo "-- Studio Kyne Mini Tools - Export SQL\n";
		echo '-- Table: ' . $table . "\n";
		echo '-- Date: ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n\n";
		echo "SET NAMES utf8mb4;\n";
		echo 'DROP TABLE IF EXISTS `' . $table . "`;\n";
		echo $create[1] . ";\n\n";

		// Données par lots de 500.
		$offset = 0;
		$batch  = 500;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( 'SELECT * FROM `' . $table . '` LIMIT ' . $batch . ' OFFSET ' . $offset, ARRAY_A );
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $row ) {
				$values = [];
				foreach ( $row as $field => $v ) {
					if ( null === $v ) {
						$values[] = 'NULL';
					} elseif ( ! empty( $is_binary[ $field ] ) ) {
						// Données binaires → littéral hexadécimal (0x…), toujours réimportable.
						$values[] = '0x' . bin2hex( $v );
					} elseif ( ! empty( $is_num[ $field ] ) && is_numeric( $v ) ) {
						$values[] = $v; // numérique → non quoté.
					} else {
						$values[] = "'" . esc_sql( $v ) . "'";
					}
				}
				echo 'INSERT INTO `' . $table . '` (' . $col_list . ') VALUES (' . implode( ', ', $values ) . ");\n";
			}
			$offset += $batch;
		} while ( count( $rows ) === $batch );

		exit;
	}
}
