<?php
namespace StudioKyne\MiniTools\Modules\ImageOptimizer;

/**
 * Support SVG sécurisé pour la médiathèque.
 *
 * - Autorise l'upload de .svg uniquement pour les rôles cochés dans les réglages.
 * - Assainit chaque fichier à l'upload (suppression du JS, gestionnaires d'événements,
 *   références externes, etc.) via une passe DOMDocument à liste blanche.
 * - Corrige la détection MIME de WordPress qui rejette sinon le fichier.
 *
 * Rien n'est branché tant que le réglage n'est pas activé (voir Module::init()).
 */
class SvgHandler {

	private const MIME = 'image/svg+xml';

	/** Éléments SVG autorisés (liste blanche). */
	private const ALLOWED_TAGS = [
		'a', 'circle', 'clippath', 'defs', 'desc', 'ellipse', 'feblend',
		'fecolormatrix', 'fecomponenttransfer', 'fecomposite', 'feconvolvematrix',
		'fediffuselighting', 'fedisplacementmap', 'fedistantlight', 'feflood',
		'fefunca', 'fefuncb', 'fefuncg', 'fefuncr', 'fegaussianblur', 'feimage',
		'femerge', 'femergenode', 'femorphology', 'feoffset', 'fepointlight',
		'fespecularlighting', 'fespotlight', 'fetile', 'feturbulence', 'filter',
		'g', 'image', 'line', 'lineargradient', 'marker', 'mask', 'metadata',
		'path', 'pattern', 'polygon', 'polyline', 'radialgradient', 'rect', 'stop',
		'style', 'svg', 'switch', 'symbol', 'text', 'textpath', 'title', 'tspan', 'use',
	];

	/** Réglages du module (svg_upload, svg_roles). */
	private array $settings;

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Branche les filtres si le support SVG est activé.
	 */
	public function init(): void {
		if ( empty( $this->settings['svg_upload'] ) ) {
			return;
		}

		add_filter( 'upload_mimes', [ $this, 'allow_mime' ] );
		add_filter( 'wp_check_filetype_and_ext', [ $this, 'fix_filetype' ], 10, 4 );
		add_filter( 'wp_handle_upload_prefilter', [ $this, 'sanitize_on_upload' ] );
	}

	/* ================================================================
	 * AUTORISATIONS
	 * ================================================================ */

	/**
	 * L'utilisateur courant a-t-il un rôle autorisé à uploader des SVG ?
	 */
	private function current_user_can_upload(): bool {
		$allowed = (array) ( $this->settings['svg_roles'] ?? [] );
		if ( empty( $allowed ) ) {
			return false;
		}

		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		return (bool) array_intersect( (array) $user->roles, $allowed );
	}

	/**
	 * Ajoute le MIME SVG à la liste autorisée pour les rôles habilités.
	 */
	public function allow_mime( $mimes ) {
		if ( $this->current_user_can_upload() ) {
			$mimes['svg'] = self::MIME;
		}
		return $mimes;
	}

	/**
	 * Corrige la détection type/extension de WordPress pour les .svg.
	 */
	public function fix_filetype( $data, $file, $filename, $mimes ) {
		if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
			return $data;
		}

		if ( 'svg' === strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) ) && $this->current_user_can_upload() ) {
			$data['ext']  = 'svg';
			$data['type'] = self::MIME;
		}

		return $data;
	}

	/* ================================================================
	 * ASSAINISSEMENT
	 * ================================================================ */

	/**
	 * Filtre wp_handle_upload_prefilter : assainit le SVG avant qu'il ne soit
	 * déplacé dans la médiathèque. Rejette le fichier si l'assainissement échoue.
	 */
	public function sanitize_on_upload( $file ) {
		$type = $file['type'] ?? '';
		$name = $file['name'] ?? '';

		$is_svg = self::MIME === $type
			|| 'svg' === strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( ! $is_svg ) {
			return $file;
		}

		if ( ! $this->current_user_can_upload() ) {
			$file['error'] = __( 'Votre rôle n\'est pas autorisé à téléverser des fichiers SVG.', 'studio-kyne-mini-tools' );
			return $file;
		}

		$path = $file['tmp_name'] ?? '';
		if ( ! $path || ! is_readable( $path ) ) {
			return $file;
		}

		$dirty = file_get_contents( $path );
		if ( false === $dirty || '' === trim( (string) $dirty ) ) {
			$file['error'] = __( 'Le fichier SVG est vide ou illisible.', 'studio-kyne-mini-tools' );
			return $file;
		}

		$clean = $this->sanitize( $dirty );
		if ( null === $clean ) {
			$file['error'] = __( 'Le fichier SVG est invalide ou n\'a pas pu être assaini.', 'studio-kyne-mini-tools' );
			return $file;
		}

		file_put_contents( $path, $clean );

		return $file;
	}

	/**
	 * Assainit une chaîne SVG. Retourne le SVG nettoyé, ou null si invalide.
	 */
	public function sanitize( string $svg ): ?string {
		// Retire une éventuelle BOM et les instructions de traitement PHP.
		$svg = preg_replace( '/<\?php.*?\?>/is', '', $svg );

		// Bloque les définitions de type de document (attaques XXE / entités externes).
		if ( preg_match( '/<!DOCTYPE/i', $svg ) && preg_match( '/<!ENTITY/i', $svg ) ) {
			return null;
		}

		$libxml_previous = libxml_use_internal_errors( true );

		// libxml 2.9+ désactive déjà le chargement d'entités externes par défaut ;
		// on ne force l'ancien garde-fou que sur PHP < 8.0 (déprécié au-delà).
		$entity_previous = null;
		if ( \PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) ) {
			$entity_previous = libxml_disable_entity_loader( true );
		}

		$dom = new \DOMDocument();
		$dom->preserveWhiteSpace = false;

		// NB : on n'ajoute jamais LIBXML_NOENT — l'expansion d'entités est un vecteur d'attaque.
		$loaded = $dom->loadXML( $svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );

		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_previous );
		if ( null !== $entity_previous && \PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) ) {
			libxml_disable_entity_loader( $entity_previous );
		}

		if ( ! $loaded || ! $dom->documentElement ) {
			return null;
		}

		if ( 'svg' !== strtolower( $dom->documentElement->nodeName ) ) {
			return null;
		}

		// Supprime les DOCTYPE et nœuds de type doctype.
		foreach ( iterator_to_array( $dom->childNodes ) as $child ) {
			if ( XML_DOCUMENT_TYPE_NODE === $child->nodeType ) {
				$dom->removeChild( $child );
			}
		}

		// Nettoie les attributs de la racine <svg> elle-même, puis récursivement les enfants.
		$this->clean_attributes( $dom->documentElement );
		$this->clean_node( $dom->documentElement );

		$out = $dom->saveXML( $dom->documentElement, LIBXML_NOEMPTYTAG );
		if ( false === $out ) {
			return null;
		}

		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $out;
	}

	/**
	 * Nettoie récursivement un nœud : supprime les balises hors liste blanche
	 * et tout attribut dangereux.
	 */
	private function clean_node( \DOMNode $node ): void {
		// Parcourt une copie : on mute les enfants pendant l'itération.
		if ( ! $node->hasChildNodes() ) {
			return;
		}
		foreach ( iterator_to_array( $node->childNodes ) as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				// Retire commentaires / PI / doctype résiduels.
				if ( in_array( $child->nodeType, [ XML_COMMENT_NODE, XML_PI_NODE ], true ) ) {
					$node->removeChild( $child );
				}
				continue;
			}

			$tag = strtolower( $child->localName ?: $child->nodeName );

			if ( ! in_array( $tag, self::ALLOWED_TAGS, true ) ) {
				$node->removeChild( $child );
				continue;
			}

			$this->clean_attributes( $child );
			$this->clean_node( $child );
		}
	}

	/**
	 * Supprime les attributs dangereux d'un élément.
	 */
	private function clean_attributes( \DOMElement $el ): void {
		foreach ( iterator_to_array( $el->attributes ) as $attr ) {
			$name  = strtolower( $attr->nodeName );
			$value = $attr->nodeValue;

			// Tout gestionnaire d'événement (onload, onclick, …).
			if ( 0 === strpos( $name, 'on' ) ) {
				$el->removeAttributeNode( $attr );
				continue;
			}

			// href / xlink:href : n'autorise que les schémas sûrs ou les ancres internes.
			if ( 'href' === $name || 'xlink:href' === $name ) {
				if ( ! $this->is_safe_href( (string) $value ) ) {
					$el->removeAttributeNode( $attr );
				}
				continue;
			}

			// Attributs pouvant embarquer du script.
			$decoded = html_entity_decode( (string) $value, ENT_QUOTES );
			$decoded = preg_replace( '/\s+/', '', $decoded );
			if ( preg_match( '/(javascript|data:text\/html|vbscript):/i', $decoded ) ) {
				$el->removeAttributeNode( $attr );
				continue;
			}

			// style : bloque url(javascript:…), expression(), et @import.
			if ( 'style' === $name && preg_match( '/(javascript:|expression\(|@import|url\(\s*["\']?\s*data:text\/html)/i', $decoded ) ) {
				$el->removeAttributeNode( $attr );
			}
		}
	}

	/**
	 * Un href est-il sûr ? Ancres internes (#id) et data: d'image uniquement.
	 */
	private function is_safe_href( string $value ): bool {
		$value = trim( html_entity_decode( $value, ENT_QUOTES ) );

		if ( '' === $value || 0 === strpos( $value, '#' ) ) {
			return true;
		}

		// data:image/... autorisé ; data:text/html interdit.
		if ( preg_match( '/^data:image\/(png|jpe?g|gif|webp);base64,/i', $value ) ) {
			return true;
		}

		return false;
	}
}
