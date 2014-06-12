<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

class CRegexValidator extends CValidator
{
	/**
	 * Check if regular expression is valid
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		// validate for modifiers
		if (preg_match('/^\//', $value) ||
			// yes, four backslashes represent one backslash in PHP regex, no error here
			preg_match('/[^\\\\]\/[a-z]*/i', $value)
		) {
			$this->setError(_('Regular expression should not contain delimiters of modifiers (you should escape "/" with "\/").'));
			return false;
		}

		// validate through preg_match
		$error = false;

		set_error_handler(function ($errno, $errstr) use (&$error) {
			if ($errstr != '') {
				$error = $errstr;
			}
		});

		preg_match('/'.$value.'/', null);

		restore_error_handler();

		if ($error) {
			$this->setError(_s('Incorrect regular expression: "%1$s".', str_replace('preg_match(): ', '', $error)));
			return false;
		}

		return true;
	}
}
