<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class for validating password complexity.
 */
class CPasswordComplexityValidator extends CValidator {

	/**
	 * File of common used passwords.
	 *
	 * @var string
	 */
	private const TOP_PASSWORDS_FILE = __DIR__.'/../../../data/top_passwords.txt';

	/**
	 * Strings forbidden to be part of validated password.
	 *
	 * @var array
	 */
	private $context_data = [];

	/**
	 * An options array.
	 *
	 * @var array
	 */
	private $options = [
		'passwd_min_length' => 0,
		'passwd_check_rules' => 0x00
	];

	/**
	 * Class constructor.
	 *
	 * @param array $options
	 * @param int   $options['passwd_min_length']   Minimum required password length.
	 * @param int   $options['passwd_check_rules']  Password complexity rules.
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;
	}

	/**
	 * Validate password complexity.
	 *
	 * @param string $value  A password string to validate.
	 *
	 * @return bool
	 */
	public function validate($value) {
		$value = (string) $value;
		$this->setError('');

		if ($this->options['passwd_min_length'] > mb_strlen($value)) {
			$this->setError(_s('must be at least %1$d characters long', $this->options['passwd_min_length']));

			return false;
		}

		$check_case = ($this->options['passwd_check_rules'] & PASSWD_CHECK_CASE);
		if ($check_case && self::checkCase($value) === false) {
			$this->setError(_('must contain at least one lowercase and one uppercase Latin letter'));

			return false;
		}

		$check_digit = ($this->options['passwd_check_rules'] & PASSWD_CHECK_DIGITS);
		if ($check_digit && self::containsDigit($value) === false) {
			$this->setError(_('must contain at least one digit'));

			return false;
		}

		$check_special = ($this->options['passwd_check_rules'] & PASSWD_CHECK_SPECIAL);
		if ($check_special && self::containsSpecialCharacter($value) === false) {
			$this->setError(_('must contain at least one special character'));

			return false;
		}

		$check_simple = ($this->options['passwd_check_rules'] & PASSWD_CHECK_SIMPLE);
		if ($check_simple && $this->isSimple($value) === false) {
			$this->setError(_("must not contain user's name, surname or username"));

			return false;
		}

		if ($check_simple && self::checkIfPasswordIsCommonlyUsed($value) === false) {
			$this->setError(_('must not be one of common or context-specific passwords'));

			return false;
		}

		return true;
	}

	/**
	 * Set array of context specific strings forbidden to be part of validated password.
	 *
	 * @param array  $context_data  Indexed array of strings that are forbidden to be part of password.
	 */
	public function setContextData(array $context_data = []): void {
		$this->context_data = $context_data;
	}

	/**
	 * Check if string contains special character.
	 *
	 * @static
	 *
	 * @param string $password  Password to check.
	 *
	 * @return bool
	 */
	private static function containsSpecialCharacter(string $password): bool {
		$spec_chars = [
			' ', '!', '"', '#', '$', '%', '&', '\'', '(', ')', '*', '+', ',', '-', '.', '/', ':', ';', '<', '=', '>',
			'?', '@', '[', '\\', ']', '^', '_', '`', '{', '|', '}', '~'
		];

		foreach ($spec_chars as $char) {
			if (stripos($password, $char) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if string contains upper and lower case characters.
	 *
	 * @static
	 *
	 * @param string $password  Password to check.
	 *
	 * @return bool
	 */
	private static function checkCase(string $password): bool {
		return (preg_match('/^(?=.*[a-z])(?=.*[A-Z]).+$/', $password) == 1);
	}

	/**
	 * Check if string contains digit.
	 *
	 * @static
	 *
	 * @param string $password  Password to check.
	 *
	 * @return bool
	 */
	private static function containsDigit(string $password): bool {
		return (preg_match('/\d/', $password) == 1);
	}

	/**
	 * Check if string doesn't contain context specific substring.
	 *
	 * @param string $password  Password to check.
	 *
	 * @return bool
	 */
	private function isSimple(string $password): bool {
		foreach ($this->context_data as $context_string) {
			if (mb_stripos($password, $context_string) !== false) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if string doesn't contain context specific substring.
	 *
	 * @static
	 *
	 * @param string $password  Password to check.
	 *
	 * @return bool
	 */
	private static function checkIfPasswordIsCommonlyUsed(string $password): bool {
		$password = base64_encode($password);

		if (($handle = fopen(self::TOP_PASSWORDS_FILE, 'r')) !== false) {
			// Skip disclaimer.
			$prev_line = null;
			while (($line = fgets($handle, 512)) !== false) {
				if ($prev_line === '' && trim($line) === '') {
					break;
				}
				$prev_line = trim($line);
			}

			// Check passwords.
			while (($line = fgets($handle, 512)) !== false) {
				if ($password === trim($line)) {
					return false;
				}
			}
			fclose($handle);
		}

		return true;
	}
}
