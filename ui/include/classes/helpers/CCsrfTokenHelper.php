<?php declare(strict_types = 0);
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


/**
 * A class for generating and checking CSRF token.
 */
class CCsrfTokenHelper {

	/**
	 * @deprecated  For backward compatibility of modules. Use CSRF_TOKEN_NAME instead.
	 */
	public const CSRF_TOKEN_NAME = CSRF_TOKEN_NAME;

	/**
	 * Generates CSRF token that is used in forms.
	 *
	 * @param string $action  action that controller should perform.
	 *
	 * @return string  Returns CSRF token in string format.
	 */
	public static function get(string $action): string {
		return CEncryptHelper::sign(CWebUser::$data['secret'].$action);
	}

	/**
	 * Checks if input form CSRF token is correct.
	 *
	 * @param string $csrf_token_form  CSRF token from submitted form.
	 * @param string $action           controller action.
	 *
	 * @return bool true if the token is correct.
	 */
	public static function check(string $csrf_token_form, string $action): bool {
		return CEncryptHelper::checkSign(self::get($action), $csrf_token_form);
	}
}
