<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CMacroFunction {

	/**
	 * Calculates regular expression substitution. Returns UNRESOLVED_MACRO_STRING in case of incorrect function
	 * parameters or regular expression.
	 *
	 * @param string $value        [IN] The input value.
	 * @param array  $parameters   [IN] The function parameters.
	 * @param bool   $insensitive  [IN] Case insensitive match.
	 *
	 * @return string
	 */
	private static function macrofuncRegsub(string $value, array $parameters, bool $insensitive): string {
		if (count($parameters) != 2) {
			return UNRESOLVED_MACRO_STRING;
		}

		set_error_handler(function ($errno, $errstr) {});
		$rc = preg_match('/'.$parameters[0].'/'.($insensitive ? 'i' : ''), $value, $matches);
		restore_error_handler();

		if ($rc === false) {
			return UNRESOLVED_MACRO_STRING;
		}

		$macro_values = [];
		for ($i = 0; $i <= 9; $i++) {
			$macro_values['\\'.$i] = array_key_exists($i, $matches) ? $matches[$i] : '';
		}

		return strtr($parameters[1], $macro_values);
	}

	/**
	 * Calculates number formatting macro function. Returns UNRESOLVED_MACRO_STRING in case of incorrect function
	 * parameters or value. Formatting is not applied to integer values.
	 *
	 * @param string $value        [IN] The input value.
	 * @param array  $parameters   [IN] The function parameters.
	 *
	 * @return string
	 */
	private static function macrofuncFmtnum(string $value, array $parameters): string {
		if (count($parameters) != 1 || $parameters[0] == '') {
			return UNRESOLVED_MACRO_STRING;
		}

		$parser = new CNumberParser(['with_float' => false]);

		if ($parser->parse($value) == CParser::PARSE_SUCCESS) {
			return $value;
		}

		$parser = new CNumberParser();

		if ($parser->parse($value) != CParser::PARSE_SUCCESS) {
			return UNRESOLVED_MACRO_STRING;
		}

		if (!ctype_digit($parameters[0]) || (int) $parameters[0] > 20) {
			return UNRESOLVED_MACRO_STRING;
		}

		return sprintf('%.'.$parameters[0].'f', (float) $value);
	}

	/**
	 * Calculates time formatting macro function. Returns UNRESOLVED_MACRO_STRING in case of incorrect function
	 * parameters or value.
	 *
	 * @param string $value        [IN] The input value.
	 * @param array  $parameters   [IN] The function parameters.
	 *
	 * @return string
	 */
	private static function macrofuncFmttime(string $value, array $parameters): string {
		if (count($parameters) == 0 || count($parameters) > 2) {
			return UNRESOLVED_MACRO_STRING;
		}

		$tz = new DateTimeZone(date_default_timezone_get());

		if (ctype_digit($value)) {
			$now = new DateTime('@'.$value);
		}
		elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|([+-]\d{2}(:?\d{2})?))?$/', $value)
				|| preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) && !preg_match('/^\d+$/', $value)) {
			$now = new DateTime($value);
		}
		else {
			return UNRESOLVED_MACRO_STRING;
		}

		$now->setTimezone($tz);

		if (count($parameters) == 2) {
			$relative_time_parser = new CRelativeTimeParser;

			if ($relative_time_parser->parse('now'.$parameters[1]) != CParser::PARSE_SUCCESS) {
				return UNRESOLVED_MACRO_STRING;
			}

			$now = $relative_time_parser->getDateTime(true, $tz, $now->getTimestamp());
		}

		return @strftime($parameters[0], $now->getTimestamp());
	}

	/**
	 * Calculates macro function. Returns UNRESOLVED_MACRO_STRING in case of unsupported function.
	 *
	 * @param string $value                    [IN] The input value.
	 * @param array  $macrofunc                [IN]
	 * @param string $macrofunc['function']    [IN] The function name.
	 * @param array  $macrofunc['parameters']  [IN] The function parameters.
	 *
	 * @return string
	 */
	public static function calcMacrofunc(string $value, array $macrofunc) {
		switch ($macrofunc['function']) {
			case 'regsub':
			case 'iregsub':
				return self::macrofuncRegsub($value, $macrofunc['parameters'], $macrofunc['function'] === 'iregsub');

			case 'fmtnum':
				return self::macrofuncFmtnum($value, $macrofunc['parameters']);

			case 'fmttime':
				return self::macrofuncFmttime($value, $macrofunc['parameters']);
		}

		return UNRESOLVED_MACRO_STRING;
	}
}
