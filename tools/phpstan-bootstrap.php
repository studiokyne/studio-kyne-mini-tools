<?php
/**
 * Constantes que PHPStan ne trouve pas dans les stubs WordPress ni dans le code analysé.
 * Fichier d'outillage uniquement : jamais chargé par l'extension.
 */

define( 'WPINC', 'wp-includes' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- constante du cœur WordPress, déclarée pour PHPStan uniquement.
