<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
** Class for wrapping JSON encoding/decoding functionality.
**
** @ MOD from package Solar_Json <solarphp.com>
**
** @author Michal Migurski <mike-json@teczno.com>
** @author Matt Knapp <mdknapp[at]gmail[dot]com>
** @author Brett Stimmerman <brettstimmerman[at]gmail[dot]com>
** @author Clay Loveless <clay@killersoft.com>
** @modified by Artem Suharev <aly@zabbix.com>
**
** @license http://opensource.org/licenses/bsd-license.php BSD
**/
class CJson {

	/**
	 *
	 * User-defined configuration, primarily of use in unit testing.
	 *
	 * Keys are ...
	 *
	 * `bypass_ext`
	 * : (bool) Flag to instruct Solar_Json to bypass
	 *   native json extension, ifinstalled.
	 *
	 * `bypass_mb`
	 * : (bool) Flag to instruct Solar_Json to bypass
	 *   native mb_convert_encoding() function, if
	 *   installed.
	 *
	 * `noerror`
	 * : (bool) Flag to instruct Solar_Json to return null
	 *   for values it cannot encode rather than throwing
	 *   an exceptions (PHP-only encoding) or PHP warnings
	 *   (native json_encode() function).
	 *
	 * @var array
	 *
	 */
	protected $_config = [
		'bypass_ext' => false,
		'bypass_mb' => false,
		'noerror' => false
	];

	/**
	 *
	 * Marker constants for use in _json_decode()
	 *
	 * @constant
	 *
	 */
	const SLICE  = 1;
	const IN_STR = 2;
	const IN_ARR = 3;
	const IN_OBJ = 4;
	const IN_CMT = 5;

	/**
	 *
	 * Nest level counter for determining correct behavior of decoding string
	 * representations of numbers and boolean values.
	 *
	 * @var int
	 */
	protected $_level;

	/**
	 * Last error of $this->decode() method.
	 *
	 * @var int
	 */
	protected $last_error;

	/**
	 *
	 * Constructor.
	 *
	 * If the $config param is an array, it is merged with the class
	 * config array and any values from the Solar.config.php file.
	 *
	 * The Solar.config.php values are inherited along class parent
	 * lines; for example, all classes descending from Solar_Base use the
	 * Solar_Base config file values until overridden.
	 *
	 * @param mixed $config User-defined configuration values.
	 *
	 */
	public function __construct($config = null) {
		$this->_mapAscii();
		$this->_setStateTransitionTable();

		$this->last_error = JSON_ERROR_NONE;
	}

	/**
	 * Default destructor; does nothing other than provide a safe fallback
	 * for calls to parent::__destruct().
	 */
	public function __destruct() {
	}

	/**
	 * Used for fallback _json_encode().
	 * If true then non-associative array is encoded as object.
	 *
	 * @var bool
	 */
	private $force_object = false;

	/**
	 * Used for fallback _json_encode().
	 * If true then forward slashes are escaped.
	 *
	 * @var bool
	 */
	private $escape_slashes = true;

	/**
	 * Encodes the mixed $valueToEncode into JSON format.
	 *
	 * @param mixed  $valueToEncode    Value to be encoded into JSON format.
	 * @param array  $deQuote          Array of keys whose values should **not** be quoted in encoded string.
	 * @param bool   $force_object     Force all arrays to objects.
	 * @param bool   $escape_slashes
	 *
	 * @return string JSON encoded value
	 */
	public function encode($valueToEncode, $deQuote = [], $force_object = false, $escape_slashes = true) {
		if (!$this->_config['bypass_ext'] && function_exists('json_encode') && defined('JSON_FORCE_OBJECT')
				&& defined('JSON_UNESCAPED_SLASHES')) {
			if ($this->_config['noerror']) {
				$old_errlevel = error_reporting(E_ERROR ^ E_WARNING);
			}

			$encoded = json_encode($valueToEncode,
				($escape_slashes ? 0 : JSON_UNESCAPED_SLASHES) | ($force_object ? JSON_FORCE_OBJECT : 0)
			);

			if ($this->_config['noerror']) {
				error_reporting($old_errlevel);
			}
		}
		else {
			// Fall back to php-only method.

			$this->force_object = $force_object;
			$this->escape_slashes = $escape_slashes;
			$encoded = $this->_json_encode($valueToEncode);
		}

		// sometimes you just don't want some values quoted
		if (!empty($deQuote)) {
			$encoded = $this->_deQuote($encoded, $deQuote);
		}

		return $encoded;
	}

	/**
	 *
	 * Accepts a JSON-encoded string, and removes quotes around values of
	 * keys specified in the $keys array.
	 *
	 * Sometimes, such as when constructing behaviors on the fly for "onSuccess"
	 * handlers to an Ajax request, the value needs to **not** have quotes around
	 * it. This method will remove those quotes and perform stripslashes on any
	 * escaped quotes within the quoted value.
	 *
	 * @param string $encoded JSON-encoded string
	 *
	 * @param array $keys Array of keys whose values should be de-quoted
	 *
	 * @return string $encoded Cleaned string
	 *
	 */
	protected function _deQuote($encoded, $keys) {
		foreach ($keys as $key) {
			$encoded = preg_replace_callback("/(\"".$key."\"\:)(\".*(?:[^\\\]\"))/U",
					[$this, '_stripvalueslashes'], $encoded);
		}
		return $encoded;
	}

	/**
	 *
	 * Method for use with preg_replace_callback in the _deQuote() method.
	 *
	 * Returns \["keymatch":\]\[value\] where value has had its leading and
	 * trailing double-quotes removed, and stripslashes() run on the rest of
	 * the value.
	 *
	 * @param array $matches Regexp matches
	 *
	 * @return string replacement string
	 *
	 */
	protected function _stripvalueslashes($matches) {
		return $matches[1].stripslashes(substr($matches[2], 1, -1));
	}

	/**
	 *
	 * Decodes the $encodedValue string which is encoded in the JSON format.
	 *
	 * For compatibility with the native json_decode() function, this static
	 * method accepts the $encodedValue string and an optional boolean value
	 * $asArray which indicates whether or not the decoded value should be
	 * returned as an array. The default is false, meaning the default return
	 * from this method is an object.
	 *
	 * For compliance with the [JSON specification][], no attempt is made to
	 * decode strings that are obviously not an encoded arrays or objects.
	 *
	 * [JSON specification]: http://www.ietf.org/rfc/rfc4627.txt
	 *
	 * @param string $encodedValue String encoded in JSON format
	 *
	 * @param bool $asArray Optional argument to decode as an array.
	 * Default false.
	 *
	 * @return mixed decoded value
	 *
	 */
	public function decode($encodedValue, $asArray = false) {
		if (!$this->_config['bypass_ext'] && function_exists('json_decode') && function_exists('json_last_error')) {
			$result = json_decode($encodedValue, $asArray);
			$this->last_error = json_last_error();

			return $result;
		}

		$first_char = substr(ltrim($encodedValue), 0, 1);

		if ($first_char != '{' && $first_char != '[') {
			$result = null;
		}
		else {
			ini_set('pcre.backtrack_limit', '10000000');

			$this->_level = 0;

			$result = $this->isValid($encodedValue) ? $this->_json_decode($encodedValue, $asArray) : null;
		}

		$this->last_error = ($result === null) ? JSON_ERROR_SYNTAX : JSON_ERROR_NONE;

		return $result;
	}

	/**
	 * Returns true if last $this->decode call was with error.
	 *
	 * @return bool
	 */
	public function hasError() {
		return ($this->last_error != JSON_ERROR_NONE);
	}

	/**
	 *
	 * Encodes the mixed $valueToEncode into the JSON format, without use of
	 * native PHP json extension.
	 *
	 * @param mixed $var Any number, boolean, string, array, or object
	 * to be encoded. Strings are expected to be in ASCII or UTF-8 format.
	 *
	 * @return mixed JSON string representation of input value
	 *
	 */
	protected function _json_encode($var) {
		switch (gettype($var)) {
			case 'boolean':
				return $var ? 'true' : 'false';
			case 'NULL':
				return 'null';
			case 'integer':
				// BREAK WITH Services_JSON:
				// disabled for compatibility with ext/json. ext/json returns
				// a string for integers, so we will to.
				return (string) $var;
			case 'double':
			case 'float':
				// BREAK WITH Services_JSON:
				// disabled for compatibility with ext/json. ext/json returns
				// a string for floats and doubles, so we will to.
				return (string) $var;
			case 'string':
				// STRINGS ARE EXPECTED TO BE IN ASCII OR UTF-8 FORMAT
				$ascii = '';
				$strlen_var = strlen($var);

				/*
				 * Iterate over every character in the string,
				 * escaping with a slash or encoding to UTF-8 where necessary
				 */
				for ($c = 0; $c < $strlen_var; ++$c) {
					$ord_var_c = ord($var{$c});
					switch (true) {
						case $ord_var_c == 0x08:
							$ascii .= '\b';
							break;
						case $ord_var_c == 0x09:
							$ascii .= '\t';
							break;
						case $ord_var_c == 0x0A:
							$ascii .= '\n';
							break;
						case $ord_var_c == 0x0C:
							$ascii .= '\f';
							break;
						case $ord_var_c == 0x0D:
							$ascii .= '\r';
							break;
						case $ord_var_c == 0x22:
						case ($ord_var_c == 0x2F && $this->escape_slashes):
						case $ord_var_c == 0x5C:
							// double quote, slash, slosh
							$ascii .= '\\'.$var{$c};
							break;
						case ($ord_var_c >= 0x20 && $ord_var_c <= 0x7F):
							// characters U-00000000 - U-0000007F (same as ASCII)
							$ascii .= $var{$c};
							break;
						case (($ord_var_c & 0xE0) == 0xC0):
							// characters U-00000080 - U-000007FF, mask 110XXXXX
							// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
							$char = pack('C*', $ord_var_c, ord($var{$c + 1}));
							$c += 1;
							$utf16 = $this->_utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;
						case (($ord_var_c & 0xF0) == 0xE0):
							// characters U-00000800 - U-0000FFFF, mask 1110XXXX
							// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
							$char = pack('C*', $ord_var_c, ord($var{$c + 1}), ord($var{$c + 2}));
							$c += 2;
							$utf16 = $this->_utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;
						case (($ord_var_c & 0xF8) == 0xF0):
							// characters U-00010000 - U-001FFFFF, mask 11110XXX
							// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
							$char = pack('C*', $ord_var_c, ord($var{$c + 1}), ord($var{$c + 2}), ord($var{$c + 3}));
							$c += 3;
							$utf16 = $this->_utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;
						case (($ord_var_c & 0xFC) == 0xF8):
							// characters U-00200000 - U-03FFFFFF, mask 111110XX
							// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
							$char = pack('C*', $ord_var_c,
										ord($var{$c + 1}),
										ord($var{$c + 2}),
										ord($var{$c + 3}),
										ord($var{$c + 4}));
							$c += 4;
							$utf16 = $this->_utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;
						case (($ord_var_c & 0xFE) == 0xFC):
							// characters U-04000000 - U-7FFFFFFF, mask 1111110X
							// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
							$char = pack('C*', $ord_var_c,
										ord($var{$c + 1}),
										ord($var{$c + 2}),
										ord($var{$c + 3}),
										ord($var{$c + 4}),
										ord($var{$c + 5}));
							$c += 5;
							$utf16 = $this->_utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;
					}
				}
				return '"'.$ascii.'"';
			case 'array':
				/*
				 * As per JSON spec if any array key is not an integer
				 * we must treat the whole array as an object. We
				 * also try to catch a sparsely populated associative
				 * array with numeric keys here because some JS engines
				 * will create an array with empty indexes up to
				 * max_index which can cause memory issues and because
				 * the keys, which may be relevant, will be remapped
				 * otherwise.
				 *
				 * As per the ECMA and JSON specification an object may
				 * have any string as a property. Unfortunately due to
				 * a hole in the ECMA specification if the key is an
				 * ECMA reserved word or starts with a digit the
				 * parameter is only accessible using ECMAScript's
				 * bracket notation.
				 */

				// treat as a JSON object
				if ($this->force_object || is_array($var) && count($var)
						&& array_keys($var) !== range(0, sizeof($var) - 1)) {
					$properties = array_map([$this, '_name_value'], array_keys($var), array_values($var));
					return '{' . join(',', $properties) . '}';
				}

				// treat it like a regular array
				$elements = array_map([$this, '_json_encode'], $var);
				return '[' . join(',', $elements) . ']';
			case 'object':
				$vars = get_object_vars($var);
				$properties = array_map([$this, '_name_value'], array_keys($vars), array_values($vars));
				return '{' . join(',', $properties) . '}';
			default:
				if ($this->_config['noerror']) {
					return 'null';
				}
				throw Solar::exception(
					'Solar_Json',
					'ERR_CANNOT_ENCODE',
					gettype($var).' cannot be encoded as a JSON string',
					['var' => $var]
				);
		}
	}

	/**
	 * Decodes a JSON string into appropriate variable.
	 *
	 * Note: several changes were made in translating this method from
	 * Services_JSON, particularly related to how strings are handled. According
	 * to JSON_checker test suite from <http://www.json.org/JSON_checker/>,
	 * a JSON payload should be an object or an array, not a string.
	 *
	 * Therefore, returning bool(true) for 'true' is invalid JSON decoding
	 * behavior, unless nested inside of an array or object.
	 *
	 * Similarly, a string of '1' should return null, not int(1), unless
	 * nested inside of an array or object.
	 *
	 * @param string $str String encoded in JSON format
	 * @param bool $asArray Optional argument to decode as an array.
	 * @return mixed decoded value
	 * @todo Rewrite this based off of method used in Solar_Json_Checker
	 */
	protected function _json_decode($str, $asArray = false) {
		$str = $this->_reduce_string($str);

		switch (strtolower($str)) {
			case 'true':
				// JSON_checker test suite claims
				// "A JSON payload should be an object or array, not a string."
				// Thus, returning bool(true) is invalid parsing, unless
				// we're nested inside an array or object.
				if (in_array($this->_level, [self::IN_ARR, self::IN_OBJ])) {
					return true;
				}
				else {
					return null;
				}
				break;
			case 'false':
				// JSON_checker test suite claims
				// "A JSON payload should be an object or array, not a string."
				// Thus, returning bool(false) is invalid parsing, unless
				// we're nested inside an array or object.
				if (in_array($this->_level, [self::IN_ARR, self::IN_OBJ])) {
					return false;
				}
				else {
					return null;
				}
				break;
			case 'null':
				return null;
			default:
				$m = [];

				if (is_numeric($str) || ctype_digit($str) || ctype_xdigit($str)) {
					// return float or int, or null as appropriate
					if (in_array($this->_level, [self::IN_ARR, self::IN_OBJ])) {
						return ((float) $str == (integer) $str) ? (integer) $str : (float) $str;
					}
					else {
						return null;
					}
					break;
				}
				elseif (preg_match('/^("|\').*(\1)$/s', $str, $m) && $m[1] == $m[2]) {
					// strings returned in UTF-8 format
					$delim = substr($str, 0, 1);
					$chrs = substr($str, 1, -1);
					$utf8 = '';
					$strlen_chrs = strlen($chrs);
					for ($c = 0; $c < $strlen_chrs; ++$c) {
						$substr_chrs_c_2 = substr($chrs, $c, 2);
						$ord_chrs_c = ord($chrs{$c});
						switch (true) {
							case $substr_chrs_c_2 == '\b':
								$utf8 .= chr(0x08);
								++$c;
								break;
							case $substr_chrs_c_2 == '\t':
								$utf8 .= chr(0x09);
								++$c;
								break;
							case $substr_chrs_c_2 == '\n':
								$utf8 .= chr(0x0A);
								++$c;
								break;
							case $substr_chrs_c_2 == '\f':
								$utf8 .= chr(0x0C);
								++$c;
								break;
							case $substr_chrs_c_2 == '\r':
								$utf8 .= chr(0x0D);
								++$c;
								break;
							case $substr_chrs_c_2 == '\\"':
							case $substr_chrs_c_2 == '\\\'':
							case $substr_chrs_c_2 == '\\\\':
							case $substr_chrs_c_2 == '\\/':
								if ($delim == '"' && $substr_chrs_c_2 != '\\\'' || $delim == "'"
										&& $substr_chrs_c_2 != '\\"') {
									$utf8 .= $chrs{++$c};
								}
								break;
							case preg_match('/\\\u[0-9A-F]{4}/i', substr($chrs, $c, 6)):
								// single, escaped unicode character
								$utf16 = chr(hexdec(substr($chrs, $c + 2, 2))).chr(hexdec(substr($chrs, $c + 4, 2)));
								$utf8 .= $this->_utf162utf8($utf16);
								$c += 5;
								break;
							case $ord_chrs_c >= 0x20 && $ord_chrs_c <= 0x7F:
								$utf8 .= $chrs{$c};
								break;
							case ($ord_chrs_c & 0xE0) == 0xC0:
								// characters U-00000080 - U-000007FF, mask 110XXXXX
								// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
								$utf8 .= substr($chrs, $c, 2);
								++$c;
								break;
							case ($ord_chrs_c & 0xF0) == 0xE0:
								// characters U-00000800 - U-0000FFFF, mask 1110XXXX
								// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
								$utf8 .= substr($chrs, $c, 3);
								$c += 2;
								break;
							case ($ord_chrs_c & 0xF8) == 0xF0:
								// characters U-00010000 - U-001FFFFF, mask 11110XXX
								// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
								$utf8 .= substr($chrs, $c, 4);
								$c += 3;
								break;
							case ($ord_chrs_c & 0xFC) == 0xF8:
								// characters U-00200000 - U-03FFFFFF, mask 111110XX
								// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
								$utf8 .= substr($chrs, $c, 5);
								$c += 4;
								break;
							case ($ord_chrs_c & 0xFE) == 0xFC:
								// characters U-04000000 - U-7FFFFFFF, mask 1111110X
								// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
								$utf8 .= substr($chrs, $c, 6);
								$c += 5;
								break;
						}
					}

					if (in_array($this->_level, [self::IN_ARR, self::IN_OBJ])) {
						return $utf8;
					}
					else {
						return null;
					}
				}
				elseif (preg_match('/^\[.*\]$/s', $str) || preg_match('/^\{.*\}$/s', $str)) {
					// array, or object notation
					if ($str{0} == '[') {
						$stk = [self::IN_ARR];
						$this->_level = self::IN_ARR;
						$arr = [];
					}
					else {
						if ($asArray) {
							$stk = [self::IN_OBJ];
							$obj = [];
						}
						else {
							$stk = [self::IN_OBJ];
							$obj = new stdClass();
						}
						$this->_level = self::IN_OBJ;
					}
					array_push($stk, ['what' => self::SLICE, 'where' => 0, 'delim' => false]);

					$chrs = substr($str, 1, -1);
					$chrs = $this->_reduce_string($chrs);

					if ($chrs == '') {
						if (reset($stk) == self::IN_ARR) {
							return $arr;
						}
						else {
							return $obj;
						}
					}

					$strlen_chrs = strlen($chrs);
					for ($c = 0; $c <= $strlen_chrs; ++$c) {
						$top = end($stk);
						$substr_chrs_c_2 = substr($chrs, $c, 2);

						if ($c == $strlen_chrs || ($chrs{$c} == ',' && $top['what'] == self::SLICE)) {
							// found a comma that is not inside a string, array, etc.,
							// OR we've reached the end of the character list
							$slice = substr($chrs, $top['where'], $c - $top['where']);
							array_push($stk, ['what' => self::SLICE, 'where' => $c + 1, 'delim' => false]);

							if (reset($stk) == self::IN_ARR) {
								$this->_level = self::IN_ARR;
								// we are in an array, so just push an element onto the stack
								array_push($arr, $this->_json_decode($slice, $asArray));
							}
							elseif (reset($stk) == self::IN_OBJ) {
								$this->_level = self::IN_OBJ;
								// we are in an object, so figure
								// out the property name and set an
								// element in an associative array,
								// for now
								$parts = [];

								if (preg_match('/^\s*(["\'].*[^\\\]["\'])\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
									// "name":value pair
									$key = $this->_json_decode($parts[1], $asArray);
									$val = $this->_json_decode($parts[2], $asArray);

									if ($asArray) {
										$obj[$key] = $val;
									}
									else {
										$obj->$key = $val;
									}
								}
								elseif (preg_match('/^\s*(\w+)\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
									// name:value pair, where name is unquoted
									$key = $parts[1];
									$val = $this->_json_decode($parts[2], $asArray);

									if ($asArray) {
										$obj[$key] = $val;
									}
									else {
										$obj->$key = $val;
									}
								}
								elseif (preg_match('/^\s*(["\']["\'])\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
									// "":value pair
									//$key = $this->_json_decode($parts[1]);
									// use string that matches ext/json
									$key = '_empty_';
									$val = $this->_json_decode($parts[2], $asArray);

									if ($asArray) {
										$obj[$key] = $val;
									}
									else {
										$obj->$key = $val;
									}
								}
							}
						}
						elseif (($chrs{$c} == '"' || $chrs{$c} == "'") && $top['what'] != self::IN_STR) {
							// found a quote, and we are not inside a string
							array_push($stk, ['what' => self::IN_STR, 'where' => $c, 'delim' => $chrs{$c}]);
						}
						elseif (((strlen(substr($chrs, 0, $c)) - strlen(rtrim(substr($chrs, 0, $c), '\\'))) % 2 != 1)
								&& $chrs{$c} == $top['delim'] && $top['what'] == self::IN_STR) {
							// found a quote, we're in a string, and it's not escaped
							// we know that it's not escaped because there is _not_ an
							// odd number of backslashes at the end of the string so far
							array_pop($stk);
						}
						elseif ($chrs{$c} == '['
								&& in_array($top['what'], [self::SLICE, self::IN_ARR, self::IN_OBJ])) {
							// found a left-bracket, and we are in an array, object, or slice
							array_push($stk, ['what' => self::IN_ARR, 'where' => $c, 'delim' => false]);
						}
						elseif ($chrs{$c} == ']' && $top['what'] == self::IN_ARR) {
							// found a right-bracket, and we're in an array
							$this->_level = null;
							array_pop($stk);
						}
						elseif ($chrs{$c} == '{'
								&& in_array($top['what'], [self::SLICE, self::IN_ARR, self::IN_OBJ])) {
							// found a left-brace, and we are in an array, object, or slice
							array_push($stk, ['what' => self::IN_OBJ, 'where' => $c, 'delim' => false]);
						}
						elseif ($chrs{$c} == '}' && $top['what'] == self::IN_OBJ) {
							// found a right-brace, and we're in an object
							$this->_level = null;
							array_pop($stk);
						}
						elseif ($substr_chrs_c_2 == '/*'
								&& in_array($top['what'], [self::SLICE, self::IN_ARR, self::IN_OBJ])) {
							// found a comment start, and we are in an array, object, or slice
							array_push($stk, ['what' => self::IN_CMT, 'where' => $c, 'delim' => false]);
							$c++;
						}
						elseif ($substr_chrs_c_2 == '*/' && ($top['what'] == self::IN_CMT)) {
							// found a comment end, and we're in one now
							array_pop($stk);
							$c++;
							for ($i = $top['where']; $i <= $c; ++$i) {
								$chrs = substr_replace($chrs, ' ', $i, 1);
							}
						}
					}

					if (reset($stk) == self::IN_ARR) {
						return $arr;
					}
					elseif (reset($stk) == self::IN_OBJ) {
						return $obj;
					}
				}
		}
	}

	/**
	 * Array-walking method for use in generating JSON-formatted name-value
	 * pairs in the form of '"name":value'.
	 *
	 * @param string $name name of key to use
	 * @param mixed $value element to be encoded
	 * @return string JSON-formatted name-value pair
	 */
	protected function _name_value($name, $value) {
		$encoded_value = $this->_json_encode($value);
		return $this->_json_encode(strval($name)) . ':' . $encoded_value;
	}

	/**
	 * Convert a string from one UTF-16 char to one UTF-8 char.
	 *
	 * Normally should be handled by mb_convert_encoding, but
	 * provides a slower PHP-only method for installations
	 * that lack the multibye string extension.
	 *
	 * @param string $utf16 UTF-16 character
	 * @return string UTF-8 character
	 */
	protected function _utf162utf8($utf16) {
		// oh please oh please oh please oh please oh please
		if (!$this->_config['bypass_mb'] && function_exists('mb_convert_encoding')) {
			return mb_convert_encoding($utf16, 'UTF-8', 'UTF-16');
		}
		$bytes = (ord($utf16{0}) << 8) | ord($utf16{1});

		switch (true) {
			case ((0x7F & $bytes) == $bytes):
				// this case should never be reached, because we are in ASCII range
				// see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				return chr(0x7F & $bytes);
			case (0x07FF & $bytes) == $bytes:
				// return a 2-byte UTF-8 character
				// see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				return chr(0xC0 | (($bytes >> 6) & 0x1F)).chr(0x80 | ($bytes & 0x3F));
			case (0xFFFF & $bytes) == $bytes:
				// return a 3-byte UTF-8 character
				// see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				return chr(0xE0 | (($bytes >> 12) & 0x0F)).chr(0x80 | (($bytes >> 6) & 0x3F)).chr(0x80 | ($bytes & 0x3F));
		}
		// ignoring UTF-32 for now, sorry
		return '';
	}

	/**
	 * Convert a string from one UTF-8 char to one UTF-16 char.
	 *
	 * Normally should be handled by mb_convert_encoding, but
	 * provides a slower PHP-only method for installations
	 * that lack the multibye string extension.
	 *
	 * @param string $utf8 UTF-8 character
	 * @return string UTF-16 character
	 */
	protected function _utf82utf16($utf8) {
		// oh please oh please oh please oh please oh please
		if (!$this->_config['bypass_mb'] && function_exists('mb_convert_encoding')) {
			return mb_convert_encoding($utf8, 'UTF-16', 'UTF-8');
		}

		switch (strlen($utf8)) {
			case 1:
				// this case should never be reached, because we are in ASCII range
				// see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				return $utf8;
			case 2:
				// return a UTF-16 character from a 2-byte UTF-8 char
				// see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				return chr(0x07 & (ord($utf8{0}) >> 2)).chr((0xC0 & (ord($utf8{0}) << 6)) | (0x3F & ord($utf8{1})));
			case 3:
				// return a UTF-16 character from a 3-byte UTF-8 char
				// see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				return chr((0xF0 & (ord($utf8{0}) << 4)) | (0x0F & (ord($utf8{1}) >> 2))).
						chr((0xC0 & (ord($utf8{1}) << 6)) | (0x7F & ord($utf8{2})));
		}
		// ignoring UTF-32 for now, sorry
		return '';
	}

	/**
	 * Reduce a string by removing leading and trailing comments and whitespace.
	 *
	 * @param string $str string value to strip of comments and whitespace
	 * @return string string value stripped of comments and whitespace
	 */
	protected function _reduce_string($str) {
		$str = preg_replace([
			// eliminate single line comments in '// ...' form
			'#^\s*//(.+)$#m',

			// eliminate multi-line comments in '/* ... */' form, at start of string
			'#^\s*/\*(.+)\*/#Us',

			// eliminate multi-line comments in '/* ... */' form, at end of string
			'#/\*(.+)\*/\s*$#Us'

		], '', $str);
		// eliminate extraneous space
		return trim($str);
	}

	//***************************************************************************
	// 								CHECK JSON									*
	//***************************************************************************
	const S_ERR = -1;	// Error
	const S_SPA = 0;	// Space
	const S_WSP = 1;	// Other whitespace
	const S_LBE = 2;	// {
	const S_RBE = 3;	// }
	const S_LBT = 4;	// [
	const S_RBT = 5;	// ]
	const S_COL = 6;	// :
	const S_COM = 7;	// ,
	const S_QUO = 8;	// "
	const S_BAC = 9;	// \
	const S_SLA = 10;	// /
	const S_PLU = 11;	// +
	const S_MIN = 12;	// -
	const S_DOT = 13;	// .
	const S_ZER = 14;	// 0
	const S_DIG = 15;	// 123456789
	const S__A_ = 16;	// a
	const S__B_ = 17;	// b
	const S__C_ = 18;	// c
	const S__D_ = 19;	// d
	const S__E_ = 20;	// e
	const S__F_ = 21;	// f
	const S__L_ = 22;	// l
	const S__N_ = 23;	// n
	const S__R_ = 24;	// r
	const S__S_ = 25;	// s
	const S__T_ = 26;	// t
	const S__U_ = 27;	// u
	const S_A_F = 28;	// ABCDF
	const S_E = 29;		// E
	const S_ETC = 30;	// Everything else

	/**
	 * Map of 128 ASCII characters into the 32 character classes.
	 * The remaining Unicode characters should be mapped to S_ETC.
	 *
	 * @var array
	 */
	protected $_ascii_class = [];

	/**
	 * State transition table.
	 * @var array
	 */
	protected $_state_transition_table = [];

	/**
	 * These modes can be pushed on the "pushdown automata" (PDA) stack.
	 * @constant
	 */
	const MODE_DONE		= 1;
	const MODE_KEY		= 2;
	const MODE_OBJECT	= 3;
	const MODE_ARRAY	= 4;

	/**
	 * Max depth allowed for nested structures.
	 * @constant
	 */
	const MAX_DEPTH = 20;

	/**
	 * The stack to maintain the state of nested structures.
	 * @var array
	 */
	protected $_the_stack = [];

	/**
	 * Pointer for the top of the stack.
	 * @var int
	 */
	protected $_the_top;

	/**
	 * The isValid method takes a UTF-16 encoded string and determines if it is
	 * a syntactically correct JSON text.
	 *
	 * It is implemented as a Pushdown Automaton; that means it is a finite
	 * state machine with a stack.
	 *
	 * @param string $str The JSON text to validate
	 * @return bool
	 */
	public function isValid($str) {
		$len = strlen($str);
		$_the_state = 0;
		$this->_the_top = -1;
		$this->_push(self::MODE_DONE);

		for ($_the_index = 0; $_the_index < $len; $_the_index++) {
			$b = $str{$_the_index};
			if (chr(ord($b) & 127) == $b) {
				$c = $this->_ascii_class[ord($b)];
				if ($c <= self::S_ERR) {
					return false;
				}
			}
			else {
				$c = self::S_ETC;
			}

			// get the next state from the transition table
			$s = $this->_state_transition_table[$_the_state][$c];

			if ($s < 0) {
				// perform one of the predefined actions
				switch ($s) {
					// empty }
					case -9:
						if (!$this->_pop(self::MODE_KEY)) {
							return false;
						}
						$_the_state = 9;
						break;
					// {
					case -8:
						if (!$this->_push(self::MODE_KEY)) {
							return false;
						}
						$_the_state = 1;
						break;
					// }
					case -7:
						if (!$this->_pop(self::MODE_OBJECT)) {
							return false;
						}
						$_the_state = 9;
						break;
					// [
					case -6:
						if (!$this->_push(self::MODE_ARRAY)) {
							return false;
						}
						$_the_state = 2;
						break;
					// ]
					case -5:
						if (!$this->_pop(self::MODE_ARRAY)) {
							return false;
						}
						$_the_state = 9;
						break;
					// "
					case -4:
						switch ($this->_the_stack[$this->_the_top]) {
							case self::MODE_KEY:
								$_the_state = 27;
								break;
							case self::MODE_ARRAY:
							case self::MODE_OBJECT:
								$_the_state = 9;
								break;
							default:
								return false;
						}
						break;
					// '
					case -3:
						switch ($this->_the_stack[$this->_the_top]) {
							case self::MODE_OBJECT:
								if ($this->_pop(self::MODE_OBJECT) && $this->_push(self::MODE_KEY)) {
									$_the_state = 29;
								}
								break;
							case self::MODE_ARRAY:
								$_the_state = 28;
								break;
							default:
								return false;
						}
						break;
					// :
					case -2:
						if ($this->_pop(self::MODE_KEY) && $this->_push(self::MODE_OBJECT)) {
							$_the_state = 28;
							break;
						}
					// syntax error
					case -1:
						return false;
				}
			}
			else {
				// change the state and iterate
				$_the_state = $s;
			}
		}
		if ($_the_state == 9 && $this->_pop(self::MODE_DONE)) {
			return true;
		}
		return false;
	}

	/**
	 * Map the 128 ASCII characters into the 32 character classes.
	 * The remaining Unicode characters should be mapped to S_ETC.
	 */
	protected function _mapAscii() {
		$this->_ascii_class = [
			self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR,
			self::S_ERR, self::S_WSP, self::S_WSP, self::S_ERR, self::S_ERR, self::S_WSP, self::S_ERR, self::S_ERR,
			self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR,
			self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR, self::S_ERR,

			self::S_SPA, self::S_ETC, self::S_QUO, self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC,
			self::S_ETC, self::S_ETC, self::S_ETC, self::S_PLU, self::S_COM, self::S_MIN, self::S_DOT, self::S_SLA,
			self::S_ZER, self::S_DIG, self::S_DIG, self::S_DIG, self::S_DIG, self::S_DIG, self::S_DIG, self::S_DIG,
			self::S_DIG, self::S_DIG, self::S_COL, self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC,

			self::S_ETC, self::S_A_F, self::S_A_F, self::S_A_F, self::S_A_F, self::S_E  , self::S_A_F, self::S_ETC,
			self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC,
			self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC,
			self::S_ETC, self::S_ETC, self::S_ETC, self::S_LBT, self::S_BAC, self::S_RBT, self::S_ETC, self::S_ETC,

			self::S_ETC, self::S__A_, self::S__B_, self::S__C_, self::S__D_, self::S__E_, self::S__F_, self::S_ETC,
			self::S_ETC, self::S_ETC, self::S_ETC, self::S_ETC, self::S__L_, self::S_ETC, self::S__N_, self::S_ETC,
			self::S_ETC, self::S_ETC, self::S__R_, self::S__S_, self::S__T_, self::S__U_, self::S_ETC, self::S_ETC,
			self::S_ETC, self::S_ETC, self::S_ETC, self::S_LBE, self::S_ETC, self::S_RBE, self::S_ETC, self::S_ETC
		];
	}

	/**
	 * The state transition table takes the current state and the current symbol,
	 * and returns either a new state or an action. A new state is a number between
	 * 0 and 29. An action is a negative number between -1 and -9. A JSON text is
	 * accepted if the end of the text is in state 9 and mode is MODE_DONE.
	 */
	protected function _setStateTransitionTable() {
		$this->_state_transition_table = [
			[ 0, 0,-8,-1,-6,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1],
			[ 1, 1,-1,-9,-1,-1,-1,-1, 3,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1],
			[ 2, 2,-8,-1,-6,-5,-1,-1, 3,-1,-1,-1,20,-1,21,22,-1,-1,-1,-1,-1,13,-1,17,-1,-1,10,-1,-1,-1,-1],
			[ 3,-1, 3, 3, 3, 3, 3, 3,-4, 4, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3, 3],
			[-1,-1,-1,-1,-1,-1,-1,-1, 3, 3, 3,-1,-1,-1,-1,-1,-1, 3,-1,-1,-1, 3,-1, 3, 3,-1, 3, 5,-1,-1,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1, 6, 6, 6, 6, 6, 6, 6, 6,-1,-1,-1,-1,-1,-1, 6, 6,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1, 7, 7, 7, 7, 7, 7, 7, 7,-1,-1,-1,-1,-1,-1, 7, 7,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1, 8, 8, 8, 8, 8, 8, 8, 8,-1,-1,-1,-1,-1,-1, 8, 8,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1, 3, 3, 3, 3, 3, 3, 3, 3,-1,-1,-1,-1,-1,-1, 3, 3,-1],
			[ 9, 9,-1,-7,-1,-5,-1,-3,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,11,-1,-1,-1,-1,-1,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,12,-1,-1,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1, 9,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,14,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,15,-1,-1,-1,-1,-1,-1,-1,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,16,-1,-1,-1,-1,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1, 9,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,18,-1,-1,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,19,-1,-1,-1,-1,-1,-1,-1,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1, 9,-1,-1,-1,-1,-1,-1,-1,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,21,22,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1],
			[ 9, 9,-1,-7,-1,-5,-1,-3,-1,-1,-1,-1,-1,23,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1],
			[ 9, 9,-1,-7,-1,-5,-1,-3,-1,-1,-1,-1,-1,23,22,22,-1,-1,-1,-1,24,-1,-1,-1,-1,-1,-1,-1,-1,24,-1],
			[ 9, 9,-1,-7,-1,-5,-1,-3,-1,-1,-1,-1,-1,-1,23,23,-1,-1,-1,-1,24,-1,-1,-1,-1,-1,-1,-1,-1,24,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,25,25,-1,26,26,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1],
			[-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,26,26,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1],
			[ 9, 9,-1,-7,-1,-5,-1,-3,-1,-1,-1,-1,-1,-1,26,26,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1],
			[27,27,-1,-1,-1,-1,-2,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1],
			[28,28,-8,-1,-6,-1,-1,-1, 3,-1,-1,-1,20,-1,21,22,-1,-1,-1,-1,-1,13,-1,17,-1,-1,10,-1,-1,-1,-1],
			[29,29,-1,-1,-1,-1,-1,-1, 3,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1]
		];
	}

	/**
	 * Push a mode onto the stack. Return false if there is overflow.
	 *
	 * @param int $mode Mode to push onto the stack
	 * @return bool Success/failure of stack push
	 */
	protected function _push($mode) {
		$this->_the_top++;
		if ($this->_the_top >= self::MAX_DEPTH) {
			return false;
		}
		$this->_the_stack[$this->_the_top] = $mode;
		return true;
	}

	/**
	 * Pop the stack, assuring that the current mode matches the expectation.
	 * Return false if there is underflow or if the modes mismatch.
	 *
	 * @param int $mode Mode to pop from the stack
	 * @return bool Success/failure of stack pop
	 */
	protected function _pop($mode) {
		if ($this->_the_top < 0 || $this->_the_stack[$this->_the_top] != $mode) {
			return false;
		}
		$this->_the_stack[$this->_the_top] = 0;
		$this->_the_top--;
		return true;
	}
}
