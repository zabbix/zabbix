<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

class CRegexValidator extends CValidator
{

	/**
	 * Error message if the is not a string.
	 *
	 * @var string
	 */
	public $messageInvalid;

	/**
	 * Error message if the value is invalid
	 *
	 * @var string
	 */
	public $messageRegex;

	/**
	 * Check if regular expression is valid
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value): bool {
		if (!is_string($value) && !is_numeric($value)) {
			$error = $this->messageInvalid;

			$this->error($error);
		}
		else {
			self::isValidExpression((string) $value, $error);

			if ($error !== '') {
				$this->error($this->messageRegex, $value, str_replace('preg_match(): ', '', $error));
			}
		}

		return $error === '';
	}

	public static function isValidExpression(string $expression, ?string &$error = null): bool {
		$error = '';

		set_error_handler(static function (int $foo, string $errstr) use (&$error) {
			$error = $errstr;
		});

		CRegexHelper::test($expression, '');

		restore_error_handler();

		return $error === '';
	}
}
