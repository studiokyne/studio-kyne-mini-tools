<?php
namespace StudioKyne\MiniTools\Modules\Smtp;

defined( 'ABSPATH' ) || exit;

/**
 * Préréglages SMTP des fournisseurs courants.
 *
 * Un préréglage ne fait que pré-remplir hôte, port, chiffrement et, le cas
 * échéant, l'identifiant : tout reste modifiable, et l'envoi passe par le même
 * relais SMTP générique. L'envoi par API HTTP (ports SMTP bloqués par
 * l'hébergeur) est un autre chantier.
 */
class Providers {

	/** Clé du choix « serveur personnalisé ». */
	const CUSTOM = 'custom';

	/**
	 * @return array<string, array{label: string, host: string, port: int, encryption: string, username: string, hint: string}>
	 */
	public static function all(): array {
		return [
			'brevo'      => [
				'label'      => 'Brevo',
				'host'       => 'smtp-relay.brevo.com',
				'port'       => 587,
				'encryption' => 'tls',
				'username'   => '',
				'hint'       => __( 'Identifiant et clé SMTP : Brevo › SMTP & API › onglet SMTP. La clé SMTP n\'est pas la clé API.', 'studio-kyne-mini-tools' ),
			],
			'mailgun'    => [
				'label'      => 'Mailgun (US)',
				'host'       => 'smtp.mailgun.org',
				'port'       => 587,
				'encryption' => 'tls',
				'username'   => '',
				'hint'       => __( 'Identifiants SMTP du domaine d\'envoi : Mailgun › Sending › Domain settings › SMTP credentials.', 'studio-kyne-mini-tools' ),
			],
			'mailgun_eu' => [
				'label'      => 'Mailgun (UE)',
				'host'       => 'smtp.eu.mailgun.org',
				'port'       => 587,
				'encryption' => 'tls',
				'username'   => '',
				'hint'       => __( 'Pour un domaine créé dans la région UE de Mailgun. Identifiants SMTP : Sending › Domain settings › SMTP credentials.', 'studio-kyne-mini-tools' ),
			],
			'sendgrid'   => [
				'label'      => 'SendGrid',
				'host'       => 'smtp.sendgrid.net',
				'port'       => 587,
				'encryption' => 'tls',
				'username'   => 'apikey',
				'hint'       => __( 'Identifiant : « apikey », littéralement. Mot de passe : une clé API avec le droit Mail Send.', 'studio-kyne-mini-tools' ),
			],
			'postmark'   => [
				'label'      => 'Postmark',
				'host'       => 'smtp.postmarkapp.com',
				'port'       => 587,
				'encryption' => 'tls',
				'username'   => '',
				'hint'       => __( 'Identifiant et mot de passe : le même Server API Token (Server › API Tokens).', 'studio-kyne-mini-tools' ),
			],
			'ses'        => [
				'label'      => 'Amazon SES',
				'host'       => 'email-smtp.eu-west-3.amazonaws.com',
				'port'       => 587,
				'encryption' => 'tls',
				'username'   => '',
				'hint'       => __( 'Remplacez eu-west-3 par la région de votre compte SES. Identifiants SMTP propres à SES (SMTP settings › Create SMTP credentials), différents des clés d\'accès IAM.', 'studio-kyne-mini-tools' ),
			],
			'mailjet'    => [
				'label'      => 'Mailjet',
				'host'       => 'in-v3.mailjet.com',
				'port'       => 587,
				'encryption' => 'tls',
				'username'   => '',
				'hint'       => __( 'Identifiant : clé API ; mot de passe : clé secrète (Account settings › API keys).', 'studio-kyne-mini-tools' ),
			],
			'ovh'        => [
				'label'      => 'OVHcloud (e-mail pro / MX Plan)',
				'host'       => 'ssl0.ovh.net',
				'port'       => 465,
				'encryption' => 'ssl',
				'username'   => '',
				'hint'       => __( 'Identifiant : l\'adresse e-mail complète ; mot de passe : celui de la boîte.', 'studio-kyne-mini-tools' ),
			],
			'gmail'      => [
				'label'      => 'Gmail / Google Workspace',
				'host'       => 'smtp.gmail.com',
				'port'       => 587,
				'encryption' => 'tls',
				'username'   => '',
				'hint'       => __( 'Identifiant : l\'adresse Gmail ; mot de passe : un mot de passe d\'application (validation en deux étapes requise), pas celui du compte. Limite d\'environ 500 envois par jour.', 'studio-kyne-mini-tools' ),
			],
			'office365'  => [
				'label'      => 'Microsoft 365 / Outlook',
				'host'       => 'smtp.office365.com',
				'port'       => 587,
				'encryption' => 'tls',
				'username'   => '',
				'hint'       => __( 'L\'authentification SMTP doit être autorisée pour la boîte dans le centre d\'administration Microsoft 365 ; elle y est souvent désactivée par défaut.', 'studio-kyne-mini-tools' ),
			],
		];
	}

	public static function is_valid( string $key ): bool {
		return self::CUSTOM === $key || isset( self::all()[ $key ] );
	}
}
