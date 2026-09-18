<?php
namespace StudioKyne\MiniTools\Modules\ImageOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Réécrit les URLs d'un média converti partout où elles ont déjà été insérées.
 *
 * Convertir une image change son nom de fichier (photo.jpg → photo.webp), et
 * ImageProcessor::convert() supprime l'original par défaut. Sans cette passe,
 * toute occurrence déjà insérée — contenu des articles, métas de constructeur
 * (Bricks, ACF…), options de thème, srcset — pointait vers un fichier disparu :
 * une optimisation en masse cassait chaque image déjà en page.
 *
 * Principes :
 *  - on cherche le chemin RELATIF au dossier uploads, précédé d'un « / »
 *    (`/2024/01/photo.jpg`) : indifférent au schéma, au domaine et à un CDN,
 *    et le « / » évite qu'`other-photo.jpg` ne contienne `photo.jpg` ;
 *  - la variante à barres échappées (`\/2024\/01\/photo.jpg`) est traitée
 *    aussi : c'est la forme des attributs de blocs Gutenberg et des JSON
 *    (Elementor, options de thème) ;
 *  - les valeurs sérialisées sont désérialisées, parcourues, puis
 *    re-sérialisées — un remplacement textuel y fausserait les longueurs de
 *    chaîne (`s:42:"…"`) et corromprait la valeur ;
 *  - une seule requête LIKE par table, sur la racine commune du média
 *    (`/2024/01/photo`), couvre l'original et toutes ses tailles.
 */
class UrlRewriter {

	/**
	 * Réécrit un jeu de correspondances dans les contenus, les métas et les options.
	 *
	 * @param array<string, string> $pairs Chemins relatifs au dossier uploads,
	 *                                     ancien => nouveau (ex. `2024/01/photo.jpg`
	 *                                     => `2024/01/photo.webp`).
	 * @return int Nombre de lignes modifiées.
	 */
	public function rewrite( array $pairs ): int {
		$pairs = $this->normalize_pairs( $pairs );
		if ( ! $pairs ) {
			return 0;
		}

		$stem = $this->common_stem( array_keys( $pairs ) );
		if ( '' === $stem ) {
			return 0;
		}

		global $wpdb;

		$updated = 0;
		$updated += $this->rewrite_table( $wpdb->posts, 'ID', [ 'post_content', 'post_excerpt' ], $stem, $pairs );
		$updated += $this->rewrite_table( $wpdb->postmeta, 'meta_id', [ 'meta_value' ], $stem, $pairs );
		$updated += $this->rewrite_table( $wpdb->options, 'option_id', [ 'option_value' ], $stem, $pairs );

		if ( $updated > 0 ) {
			// Les caches objet de ces lignes sont périmés.
			wp_cache_flush();
		}

		return $updated;
	}

	/**
	 * Ne garde que des paires valides et distinctes, sans slash de tête.
	 *
	 * @param array<string, string> $pairs
	 * @return array<string, string>
	 */
	private function normalize_pairs( array $pairs ): array {
		$clean = [];
		foreach ( $pairs as $old => $new ) {
			$old = ltrim( str_replace( '\\', '/', (string) $old ), '/' );
			$new = ltrim( str_replace( '\\', '/', (string) $new ), '/' );
			if ( '' === $old || '' === $new || $old === $new ) {
				continue;
			}
			$clean[ $old ] = $new;
		}
		return $clean;
	}

	/**
	 * Racine commune aux chemins (`2024/01/photo` pour `photo.jpg` et
	 * `photo-300x200.jpg`) : c'est le motif LIKE, précédé d'un « / ».
	 *
	 * @param string[] $paths
	 */
	private function common_stem( array $paths ): string {
		$stem = null;
		foreach ( $paths as $path ) {
			$without_ext = preg_replace( '/\.[a-z0-9]+$/i', '', $path );
			$stem        = null === $stem ? $without_ext : $this->common_prefix( $stem, $without_ext );
		}
		return (string) $stem;
	}

	private function common_prefix( string $a, string $b ): string {
		$len = min( strlen( $a ), strlen( $b ) );
		$i   = 0;
		while ( $i < $len && $a[ $i ] === $b[ $i ] ) {
			$i++;
		}
		return substr( $a, 0, $i );
	}

	/**
	 * Parcourt les lignes d'une table contenant la racine et réécrit celles
	 * qui changent réellement.
	 *
	 * @param string   $table   Nom de table.
	 * @param string   $id_col  Clé primaire.
	 * @param string[] $columns Colonnes texte à traiter.
	 * @param string   $stem    Racine commune (relative uploads, sans slash de tête).
	 * @param array    $pairs   ancien => nouveau.
	 */
	private function rewrite_table( string $table, string $id_col, array $columns, string $stem, array $pairs ): int {
		global $wpdb;

		$like    = '%' . $wpdb->esc_like( '/' . $stem ) . '%';
		$escaped = '%' . $wpdb->esc_like( '\/' . str_replace( '/', '\/', $stem ) ) . '%';

		$where = [];
		foreach ( $columns as $col ) {
			$where[] = "`{$col}` LIKE %s OR `{$col}` LIKE %s";
		}
		$cols_sql = '`' . implode( '`, `', $columns ) . '`';
		$args     = [];
		foreach ( $columns as $col ) {
			$args[] = $like;
			$args[] = $escaped;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT `{$id_col}`, {$cols_sql} FROM `{$table}` WHERE " . implode( ' OR ', $where ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$args
		), ARRAY_A );

		$updated = 0;
		foreach ( (array) $rows as $row ) {
			$changes = [];
			foreach ( $columns as $col ) {
				$new = $this->replace_in_value( $row[ $col ], $pairs );
				if ( $new !== $row[ $col ] ) {
					$changes[ $col ] = $new;
				}
			}
			if ( $changes ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update( $table, $changes, [ $id_col => $row[ $id_col ] ] );
				$updated++;
			}
		}

		return $updated;
	}

	/**
	 * Remplace dans une valeur brute de base : chaîne, ou sérialisé PHP.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function replace_in_value( $value, array $pairs ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		if ( is_serialized( $value ) ) {
			// Mêmes règles que le cœur (get_option, get_post_meta désérialisent
			// déjà ces valeurs avec toutes les classes) : rien de plus n'est
			// exposé ici. Un objet dont la classe n'est pas chargée arrive en
			// __PHP_Incomplete_Class : il est laissé tel quel (voir
			// replace_recursive) et se re-sérialise sous son nom d'origine.
			$data = @unserialize( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false !== $data || 'b:0;' === $value ) {
				$replaced = $this->replace_recursive( $data, $pairs );
				return serialize( $replaced ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			}
		}

		return $this->replace_in_string( $value, $pairs );
	}

	/**
	 * @param mixed $data
	 * @return mixed
	 */
	private function replace_recursive( $data, array $pairs ) {
		if ( is_string( $data ) ) {
			return $this->replace_in_string( $data, $pairs );
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				$data[ $k ] = $this->replace_recursive( $v, $pairs );
			}
			return $data;
		}
		if ( is_object( $data ) ) {
			if ( $data instanceof \__PHP_Incomplete_Class ) {
				return $data; // Propriétés non modifiables sans la classe.
			}
			foreach ( get_object_vars( $data ) as $k => $v ) {
				$data->$k = $this->replace_recursive( $v, $pairs );
			}
			return $data;
		}
		return $data;
	}

	private function replace_in_string( string $value, array $pairs ): string {
		foreach ( $pairs as $old => $new ) {
			$old_esc = str_replace( '/', '\/', $old );
			$new_esc = str_replace( '/', '\/', $new );

			// Frontière après l'extension : `photo.jpg` ne doit pas toucher
			// `photo.jpg-old.jpg` ni `photo.jpgx`, mais `photo.jpg?ver=3`,
			// `photo.jpg"` et `photo.jpg 300w` restent couverts.
			// Callbacks plutôt qu'une chaîne de remplacement : preg_replace y
			// interprète l'antislash, et `\/` en ressortirait doublé.
			$value = (string) preg_replace_callback(
				'#\\\\/' . preg_quote( $old_esc, '#' ) . '(?![A-Za-z0-9._-])#',
				static function () use ( $new_esc ) {
					return '\/' . $new_esc;
				},
				$value
			);
			$value = (string) preg_replace_callback(
				'#/' . preg_quote( $old, '#' ) . '(?![A-Za-z0-9._-])#',
				static function () use ( $new ) {
					return '/' . $new;
				},
				$value
			);
		}
		return $value;
	}
}
