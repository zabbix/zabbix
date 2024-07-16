<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
	 * @param string $value       [IN] The input value.
	 * @param array  $parameters  [IN] The function parameters.
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
	 * Replaces all string occurrences based on pattern-replacement pairs.
	 *
	 * @param string $value       [IN] The input value.
	 * @param array  $parameters  [IN] Function parameters - an array of pattern-replacement pairs.
	 *
	 * @return string
	 */
	private static function macrofuncRegrepl(string $value, array $parameters): string {
		$parameter_count = count($parameters);

		if ($parameter_count === 0 || $parameter_count % 2 !== 0) {
			return UNRESOLVED_MACRO_STRING;
		}

		try {
			for ($i = 0; $i < $parameter_count; $i += 2) {
				$pattern = $parameters[$i];
				$replacement = $parameters[$i + 1];

				if (!is_string($replacement)) {
					return UNRESOLVED_MACRO_STRING;
				}

				$value = preg_replace('/' . $pattern . '/i', $replacement, $value, -1);

				if ($value === null || preg_last_error() !== PREG_NO_ERROR) {
					return UNRESOLVED_MACRO_STRING;
				}
			}
		}
		catch (Exception $e) {
			return UNRESOLVED_MACRO_STRING;
		}

		return $value;
	}

	/**
	 * Handles string transliteration - replaces all characters in the input string based on a search list and a
	 * replacement list.
	 *
	 * @param string $value       [IN] The input value.
	 * @param array  $parameters  [IN] Function parameters - an array containing search list and replacement list.
	 *
	 * @return string
	 */
	private static function macrofuncTr(string $value, array $parameters): string {
		if (count($parameters) !== 2) {
			return UNRESOLVED_MACRO_STRING;
		}

		[$searchlist, $replacementlist] = $parameters;

		if ($searchlist === '' || $replacementlist === '') {
			return $value;
		}

		// Expand the parameters, in case escape characters or ranges are used.
		$searchlist = self::expandParameters($searchlist);
		$replacementlist = self::expandParameters($replacementlist);

		// In case of incorrect parameters (e.g., invalid range) return UNKNOWN macro string.
		if ($searchlist === UNRESOLVED_MACRO_STRING || $replacementlist === UNRESOLVED_MACRO_STRING) {
			return UNRESOLVED_MACRO_STRING;
		}

		// Add missing characters if length of searchlist and replacementlist is not identical.
		if (strlen($searchlist) > strlen($replacementlist)) {
			$replacementlist = str_pad($replacementlist, strlen($searchlist), substr($replacementlist, -1));
		}
		elseif (strlen($replacementlist) > strlen($searchlist)) {
			$replacementlist = substr($replacementlist, 0, strlen($searchlist));
		}

		return strtr($value, $searchlist, $replacementlist);
	}

	/**
	 * Expands escape sequences and character ranges in the parameter string.
	 *
	 * @param string $parameter  [IN] Function parameters - an array containing search-list and replacement-list.
	 *
	 * @return string
	 */
	private static function expandParameters(string $parameter): string {
		$expanded = '';
		$length = strlen($parameter);
		$i = 0;
		$characters = str_split($parameter);

		while ($i < $length) {
			// Handle escape sequences.
			if (array_key_exists($i, $characters) && $characters[$i] === '\\') {
				$i++;

				if ($i < $length) {
					$expanded .= match ($characters[$i]) {
						'a' => "\a",
						'b' => "\b",
						'f' => "\f",
						'n' => "\n",
						'r' => "\r",
						't' => "\t",
						'v' => "\v",
						default => $characters[$i],
					};

					unset($characters[$i]);
				}
			}

			// Handle ranges.
			if (array_key_exists($i, $characters) && $parameter[$i] === '-') {
				// If the first element is '-', and it is part of a range, do not interpret it as a separate character.
				if ($i === 0 && $parameter[$i] === '-' && $parameter[$i + 1] === '-') {
					$i++;

					continue;
				}

				if ($i + 1 <= $length && $i - 1 >= 0) {
					$range_start = $parameter[$i - 1];
					$range_end = $parameter[$i + 1];

					// Check if range is valid.
					if (ord($range_start) > ord($range_end)) {
						return UNRESOLVED_MACRO_STRING;
					}

					for ($ascii = ord($range_start) + 1; $ascii <= ord($range_end); $ascii++) {
						$expanded .= chr($ascii);
					}

					unset($characters[$i - 1], $characters[$i], $characters[$i + 1]);

					$i += 2;
				}
				else {
					$expanded .= $parameter[$i];

					unset($characters[$i]);
					$i ++;
				}
			}

			if (array_key_exists($i, $characters)) {
				$expanded .= $parameter[$i];

				unset($characters[$i]);
			}

			$i++;
		}

		return $expanded;
	}

	/**
	 * Encodes a string to base64 format.
	 *
	 * @param string $value  [IN] The input value.
	 *
	 * @return string
	 */
	private static function macrofuncBtoa(string $value, array $parameters): string {
		return self::removeDefaultParameter($parameters) === [] ? base64_encode($value) : UNRESOLVED_MACRO_STRING;
	}

	/**
	 * Converts a string to URL-encoded (percent-encoded) format.
	 *
	 * @param string $value  [IN] The input value.
	 *
	 * @return string
	 */
	private static function macrofuncUrlencode(string $value, array $parameters): string {
		return self::removeDefaultParameter($parameters) === [] ? urlencode($value) : UNRESOLVED_MACRO_STRING;
	}

	/**
	 * Converts special characters to HTML entities.
	 *
	 * @param string $value  [IN] The input value.
	 *
	 * @return string
	 */
	private static function macrofuncHtmlencode(string $value, array $parameters): string {
		$encoded = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

		// Replace encoded '&#039;' with '&#39;' to align with server-side encoding.
		$encoded = str_replace('&#039;', '&#39;', $encoded);

		return self::removeDefaultParameter($parameters) === []
			? $encoded
			: UNRESOLVED_MACRO_STRING;
	}

	/**
	 * Converts URL-encoded sequence to string.
	 *
	 * @param string $value  [IN] The input value.
	 *
	 * @return string
	 */
	private static function macrofuncUrldecode(string $value, array $parameters): string {
		return self::removeDefaultParameter($parameters) === [] ? urldecode($value) : UNRESOLVED_MACRO_STRING;
	}

	/**
	 * Converts HTML entities into their corresponding characters.
	 *
	 * @param string $value  [IN] The input value.
	 *
	 * @return string
	 */
	private static function macrofuncHtmldecode(string $value, array $parameters): string {
		return self::removeDefaultParameter($parameters) === []
			? html_entity_decode($value, ENT_QUOTES, 'UTF-8')
			: UNRESOLVED_MACRO_STRING;
	}

	/**
	 * Converts all alphabetic characters to lowercase.
	 *
	 * @param string $value  [IN] The input value.
	 *
	 * @return string
	 */
	private static function macrofuncLowercase(string $value, array $parameters): string {
		return self::removeDefaultParameter($parameters) === [] ? strtolower($value) : UNRESOLVED_MACRO_STRING;
	}

	/**
	 * Converts all alphabetic characters to uppercase.
	 *
	 * @param string $value  [IN] The input value.
	 *
	 * @return string
	 */
	private static function macrofuncUppercase(string $value, array $parameters): string {
		return self::removeDefaultParameter($parameters) === [] ? strtoupper($value) : UNRESOLVED_MACRO_STRING;
	}

	/**
	 * Removes default empty parameter.
	 *
	 * @param array $parameters  [IN] The input value.
	 *
	 * @return array
	 */
	private static function removeDefaultParameter(array $parameters): array {
		return count($parameters) == 1 && $parameters[0] === '' ? [] : $parameters;
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

			case 'regrepl':
				return self::macrofuncRegrepl($value, $macrofunc['parameters']);

			case 'tr':
				return self::macrofuncTr($value, $macrofunc['parameters']);

			case 'btoa':
				return self::macrofuncBtoa($value, $macrofunc['parameters']);

			case 'urlencode':
				return self::macrofuncUrlencode($value, $macrofunc['parameters']);

			case 'htmlencode':
				return self::macrofuncHtmlencode($value, $macrofunc['parameters']);

			case 'urldecode':
				return self::macrofuncUrldecode($value, $macrofunc['parameters']);

			case 'htmldecode':
				return self::macrofuncHtmldecode($value, $macrofunc['parameters']);

			case 'lowercase':
				return self::macrofuncLowercase($value, $macrofunc['parameters']);

			case 'uppercase':
				return self::macrofuncUppercase($value, $macrofunc['parameters']);
		}

		return UNRESOLVED_MACRO_STRING;
	}
}
