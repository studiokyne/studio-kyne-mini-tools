<?php
namespace StudioKyne\MiniTools\Modules\Security;

/**
 * Validateur de force des mots de passe.
 *
 * Standards 2026: 12+ caractères, majuscules, minuscules, chiffres, spéciaux.
 */
class PasswordValidator {

	private const PASSWORD_MIN_LENGTH = 12;
	private const PASSWORD_REQUIRE_UPPERCASE = true;
	private const PASSWORD_REQUIRE_LOWERCASE = true;
	private const PASSWORD_REQUIRE_DIGITS = true;
	private const PASSWORD_REQUIRE_SPECIAL = true;

	/**
	 * Valide la force d'un mot de passe.
	 *
	 * @param string $password
	 * @return array ['valid' => bool, 'errors' => string[]]
	 */
	public function validate( string $password ): array {
		$errors = [];

		if ( strlen( $password ) < self::PASSWORD_MIN_LENGTH ) {
			$errors[] = sprintf(
				__( 'Le mot de passe doit contenir au moins %d caractères.', 'studio-kyne-mini-tools' ),
				self::PASSWORD_MIN_LENGTH
			);
		}

		if ( self::PASSWORD_REQUIRE_UPPERCASE && ! preg_match( '/[A-Z]/', $password ) ) {
			$errors[] = __( 'Le mot de passe doit contenir au moins une majuscule.', 'studio-kyne-mini-tools' );
		}

		if ( self::PASSWORD_REQUIRE_LOWERCASE && ! preg_match( '/[a-z]/', $password ) ) {
			$errors[] = __( 'Le mot de passe doit contenir au moins une minuscule.', 'studio-kyne-mini-tools' );
		}

		if ( self::PASSWORD_REQUIRE_DIGITS && ! preg_match( '/[0-9]/', $password ) ) {
			$errors[] = __( 'Le mot de passe doit contenir au moins un chiffre.', 'studio-kyne-mini-tools' );
		}

		if ( self::PASSWORD_REQUIRE_SPECIAL && ! preg_match( '/[!@#$%^&*()\-+=\[\]{};\':"\\|,.<>\/?]/', $password ) ) {
			$errors[] = __( 'Le mot de passe doit contenir au moins un caractère spécial (!@#$%^&*, etc.).', 'studio-kyne-mini-tools' );
		}

		return [
			'valid'  => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Récupère le message de requirements du mot de passe.
	 *
	 * @return string
	 */
	public function get_requirements_text(): string {
		return sprintf(
			__( 'Minimum %d caractères avec majuscules, minuscules, chiffres et caractères spéciaux.', 'studio-kyne-mini-tools' ),
			self::PASSWORD_MIN_LENGTH
		);
	}

	/**
	 * Hook pour valider lors de user create/update.
	 * Appelé via user_profile_update_errors.
	 *
	 * @param \WP_Error $errors
	 * @param bool      $update
	 * @param ?\WP_User $user
	 * @return void
	 */
	public function validate_user_password( \WP_Error $errors, bool $update, ?\WP_User $user ): void {
		if ( ! isset( $_POST['user_pass'] ) || empty( $_POST['user_pass'] ) ) {
			return;
		}

		$password   = $_POST['user_pass'];
		$validation = $this->validate( $password );

		if ( ! $validation['valid'] ) {
			foreach ( $validation['errors'] as $error ) {
				$errors->add( 'password_strength', $error );
			}
		}
	}
}
