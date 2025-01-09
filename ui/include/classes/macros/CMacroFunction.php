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

		if ($parameter_count == 0 || $parameter_count % 2 != 0) {
			return UNRESOLVED_MACRO_STRING;
		}

		// Maximum allowed length (64KB) in bytes.
		$max_length = 65536;

		try {
			for ($i = 0; $i < $parameter_count; $i += 2) {
				$pattern = $parameters[$i];
				$replacement = $parameters[$i + 1];

				if ($pattern === '') {
					continue;
				}

				$replacement = self::handleReplacement($replacement);

				// Escape '/' characters that are not already escaped.
				$pattern = preg_replace('/\\\?\//', '\\/', $pattern);

				// Add the 'u' modifier to treat it as UTF-8 for multibyte characters and ensure pattern ends with '/'.
				if ($pattern[0] !== '/' || substr($pattern, -1) !== '/') {
					$pattern = '/'.$pattern.'/u';
				}

				// Adjust pattern to match ASCII only if the value has multibyte characters and the pattern includes \w.
				if (!mb_detect_encoding($value, 'ASCII', true)) {
					$pattern = preg_replace('/\\\w/', '[a-zA-Z0-9_]+', $pattern);
				}

				if (@preg_match($pattern, '') === false) {
					return UNRESOLVED_MACRO_STRING;
				}

				// Get all matches of the pattern in the string
				$matches = [];
				preg_match_all($pattern, $value, $matches);

				// Estimate the new length of value after replacements, and return *UNKNOWN* if it exceeds 64KB.
				foreach ($matches[0] as $match) {
					$length_difference = strlen($replacement) - strlen($match);
					$potential_length = strlen($value) + $length_difference * count($matches[0]);

					if ($potential_length > $max_length) {
						return UNRESOLVED_MACRO_STRING;
					}
				}

				$replacement = static function(array $matches) use ($replacement) {
					$result = $replacement;

					// Replace \0 with the entire matched string (backreference to a full match).
					$result = str_replace('\\0', $matches[0], $result);

					// Replace /1 - /9 with the capturing group values.
					for ($i = 1; $i <= 9; $i++) {
						$result = str_replace('\\'.$i, $matches[$i] ?? '', $result);
					}

					return $result;
				};

				$value = preg_replace_callback($pattern, $replacement, $value);
			}
		}
		catch (Exception) {
			return UNRESOLVED_MACRO_STRING;
		}

		return $value;
	}

	/**
	 * Handle escape sequences in replacement string.
	 *
	 * @param string $replacement The replacement string to be processed.
	 *
	 * @return string
	 */
	private static function handleReplacement(string $replacement): string {
		$upd_replacement = '';
		$length = strlen($replacement);
		$i = 0;

		while ($i < $length) {
			if ($replacement[$i] === '\\') {
				if ($i + 1 < $length && $replacement[$i + 1] === '\\') {
					// Append the char as-is and skip the next character.
					$upd_replacement .= $replacement[$i];
					$i++;
				}
				else {
					$upd_replacement .= $i + 1 < $length ? '\\'.$replacement[$i + 1] : $replacement[$i];
					$i++;
				}
			}
			else {
				$upd_replacement .= $replacement[$i];
			}
			$i++;
		}

		return $upd_replacement;
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
		if (count($parameters) != 2) {
			return UNRESOLVED_MACRO_STRING;
		}

		[$searchlist, $replacementlist] = $parameters;

		// Expand the parameters, in case escape characters or ranges are used.
		$searchlist = self::expandParameters($searchlist);
		$replacementlist = self::expandParameters($replacementlist);

		if ($searchlist === '' || $replacementlist === '') {
			return $value;
		}

		// In case of incorrect parameters (e.g., invalid range) return UNKNOWN macro string.
		if ($searchlist === null || $replacementlist === null) {
			return UNRESOLVED_MACRO_STRING;
		}

		// Handle multibyte characters.
		if (!mb_check_encoding($searchlist, 'ASCII') || !mb_check_encoding($replacementlist, 'ASCII')) {
			return self::handleMultibyteCharacters($value, $searchlist, $replacementlist);
		}

		if (strlen($searchlist) > strlen($replacementlist)) {
			$replacementlist = str_pad($replacementlist, strlen($searchlist), substr($replacementlist, -1));
		}
		elseif (strlen($replacementlist) > strlen($searchlist)) {
			$replacementlist = substr($replacementlist, 0, strlen($searchlist));
		}

		return strtr($value, $searchlist, $replacementlist);
	}

	/**
	 * Handles the character transliteration when multibyte characters are used in search-list or replacement-list
	 *
	 * @param string $value            [IN] Macro value.
	 * @param string $searchlist       [IN] Search parameter.
	 * @param string $replacementlist  [IN] Replacement parameter.
	 *
	 * @return string
	 */
	private static function handleMultibyteCharacters(string $value, string $searchlist,
			string $replacementlist): string {
		$searchlist_bytes = array_values(unpack('C*', $searchlist));
		$replacementlist_bytes = array_values(unpack('C*', $replacementlist));

		// Add missing characters or truncate replacementlist based on byte length.
		if (count($searchlist_bytes) > count($replacementlist_bytes)) {
			$replacementlist_bytes = array_pad($replacementlist_bytes, count($searchlist_bytes),
				end($replacementlist_bytes)
			);
		}
		elseif (count($replacementlist_bytes) > count($searchlist_bytes)) {
			$replacementlist_bytes = array_slice($replacementlist_bytes, 0, count($searchlist_bytes));
		}

		// Build the translation map for byte-level replacement.
		$translation_map = array_combine($searchlist_bytes, $replacementlist_bytes);

		// Translate the string based on byte values.
		$value_bytes = array_values(unpack('C*', $value));
		$translated_bytes = array_map(static fn($byte) => $translation_map[$byte] ?? $byte, $value_bytes);

		// Convert bytes back to string.
		$converted_string = implode(array_map('chr', $translated_bytes));

		return mb_convert_encoding($converted_string, 'UTF-8', 'UTF-8');
	}

	/**
	 * Expands escape sequences and character ranges in the parameter string.
	 *
	 * @param string $parameter  [IN] Function parameters - an array containing search-list and replacement-list.
	 *
	 * @return string|null
	 */
	private static function expandParameters(string $parameter): ?string {
		if ($parameter === '\\') {
			return $parameter;
		}

		$expanded = '';
		$length = strlen($parameter);
		$characters = str_split($parameter);
		$i = 0;

		while ($i < $length) {
			// Handle escape sequences.
			if (array_key_exists($i, $characters) && $characters[$i] === '\\') {
				$i++;

				if ($i < $length) {
					$expanded .= match ($characters[$i]) {
						// Alert and backspace use hex codes as PHP doesn't support these escape sequences directly.
						'a' => "\x07",
						'b' => "\x08",
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
			if (array_key_exists($i, $characters) && $parameter[$i] === '-' && $i + 1 != $length) {
				// If the first element is '-', and it is part of a range, do not interpret it as a separate character.
				if ($i == 0 && $parameter[$i] === '-' && $parameter[$i + 1] === '-') {
					$i++;

					continue;
				}

				if ($i + 1 <= $length && $i - 1 >= 0) {
					$range_start = $parameter[$i - 1];
					$range_end = $parameter[$i + 1];

					// Check if range is valid.
					if (ord($range_start) > ord($range_end)) {
						return null;
					}

					for ($ascii = ord($range_start) + 1; $ascii <= ord($range_end); $ascii++) {
						$expanded .= chr($ascii);
					}

					$i += 2;
				}
				else {
					$expanded .= $parameter[$i];

					$i ++;
				}
			}

			if (array_key_exists($i, $characters)) {
				$expanded .= $parameter[$i];
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
		return self::removeDefaultParameter($parameters) == [] ? base64_encode($value) : UNRESOLVED_MACRO_STRING;
	}

	/**
	 * Converts a string to URL-encoded (percent-encoded) format.
	 *
	 * @param string $value  [IN] The input value.
	 *
	 * @return string
	 */
	private static function macrofuncUrlencode(string $value, array $parameters): string {
		return self::removeDefaultParameter($parameters) == [] ? rawurlencode($value) : UNRESOLVED_MACRO_STRING;
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

		return self::removeDefaultParameter($parameters) == []
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
		return self::removeDefaultParameter($parameters) == [] ? urldecode($value) : UNRESOLVED_MACRO_STRING;
	}

	/**
	 * Converts HTML entities into their corresponding characters.
	 *
	 * @param string $value  [IN] The input value.
	 *
	 * @return string
	 */
	private static function macrofuncHtmldecode(string $value, array $parameters): string {
		return self::removeDefaultParameter($parameters) == []
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
		return self::removeDefaultParameter($parameters) == [] ? strtolower($value) : UNRESOLVED_MACRO_STRING;
	}

	/**
	 * Converts all alphabetic characters to uppercase.
	 *
	 * @param string $value  [IN] The input value.
	 *
	 * @return string
	 */
	private static function macrofuncUppercase(string $value, array $parameters): string {
		return self::removeDefaultParameter($parameters) == [] ? strtoupper($value) : UNRESOLVED_MACRO_STRING;
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
