<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Class is used to validate and parse item keys.
 */
class CItemKey extends CParser {

	const STATE_NEW = 0;
	const STATE_END = 1;
	const STATE_UNQUOTED = 2;
	const STATE_QUOTED = 3;
	const STATE_END_OF_PARAMS = 4;

	const STATE_AFTER_OPEN_BRACE = 1;
	const STATE_AFTER_LOGICAL_OPERATOR = 3;
	const STATE_AFTER_CLOSE_BRACE = 6;
	const STATE_AFTER_CONSTANT = 7;

	const PARAM_ARRAY = 0;
	const PARAM_UNQUOTED = 1;
	const PARAM_QUOTED = 2;

	private $key = ''; // main part of the key (for 'key[1, 2, 3]' key id would be 'key')
	private $parameters = [];

	/**
	 * Array of parsed item key filter attributes.
	 *
	 * @var array $attributes
	 */
	private $attributes = [];

	/**
	 * An options array
	 *
	 * Supported options:
	 *   '18_simple_checks' => true		with support for old-style simple checks like "ftp,{$PORT}"
	 *   'with_filter'                  allow additional item key filter
	 *   'allow_wildcard'               allow * as item key
	 *
	 * @var array
	 */
	private $options = ['18_simple_checks' => false, 'with_filter' => false, 'allow_wildcard' => false];

	/**
	 * @param array $options
	 */
	public function __construct($options = []) {
		$this->options = $options + $this->options;
		$this->error_msgs['empty'] = _('key is empty');
		$this->error_msgs['unexpected_end'] = _('unexpected end of key');
	}

	/**
	 * Check if given character is a valid key id char
	 * this function is a copy of is_key_char() from /src/libs/zbxcommon/misc.c
	 * don't forget to take look in there before changing anything.
	 *
	 * @param string $char
	 * @return bool
	 */
	function isKeyChar($char) {
		return (
			($char >= 'a' && $char <= 'z')
			|| $char == '.' || $char == '_' || $char == '-'
			|| ($char >= 'A' && $char <= 'Z')
			|| ($char >= '0' && $char <= '9')
		);
	}

	/**
	 * Parse key and parameters and put them into $this->parameters array.
	 *
	 * @param string	$data
	 * @param int		$offset
	 */
	public function parse($data, $offset = 0) {
		$this->length = 0;
		$this->match = '';
		$this->key = '';
		$this->parameters = [];
		$this->errorClear();

		if ($this->options['allow_wildcard'] && $data[$offset] === '*') {
			$p = $offset + 1;
		}
		else {
			for ($p = $offset; isset($data[$p]) && $this->isKeyChar($data[$p]); $p++) {
				// Code is not missing here.
			}
		}

		// is key empty?
		if ($p == $offset) {
			$this->errorPos(substr($data, $offset), 0);

			return self::PARSE_FAIL;
		}

		$_18_simple_check = false;

		// old-style simple checks
		if ($this->options['18_simple_checks'] && isset($data[$p]) && $data[$p] === ',') {
			$p++;

			$user_macro_parser = new CUserMacroParser();

			if ($user_macro_parser->parse($data, $p) != CParser::PARSE_FAIL) {
				$p += $user_macro_parser->getLength();
			}
			// numeric parameter or empty parameter
			else {
				for (; isset($data[$p]) && $data[$p] > '0' && $data[$p] < '9'; $p++) {
					// Code is not missing here.
				}
			}

			$_18_simple_check = true;
		}

		$this->key = substr($data, $offset, $p - $offset);
		$p2 = $p;

		if (!$_18_simple_check && isset($data[$p2]) && $data[$p2] == '[') {
			$_parameters = [
				'type' => self::PARAM_ARRAY,
				'raw' => '',
				'pos' => $p2 - $offset,
				'parameters' => []
			];
			if ($this->parseKeyParameters($data, $p2, $_parameters['parameters'])) {
				$_parameters['raw'] = substr($data, $p, $p2 - $p);
				$this->parameters[] = $_parameters;
				$p = $p2;
			}
			else {
				$this->errorPos($data, $p2);
				return self::PARSE_FAIL;
			}
		}

		if ($this->options['with_filter'] && $data[$p] === '?') {
			if (substr($data, $p, 2) !== '?[') {
				$this->errorPos($data, $p);
				return self::PARSE_FAIL;
			}

			if (!$this->parseKeyAttributes($data, $p)) {
				return self::PARSE_FAIL;
			}

			$p2 = $p;
		}

		$this->length = $p - $offset;
		$this->match = substr($data, $offset, $this->length);

		if (!isset($data[$p])) {
			return self::PARSE_SUCCESS;
		}

		$this->errorPos(substr($data, $offset), $p2 - $offset);

		return self::PARSE_SUCCESS_CONT;
	}

	private function parseKeyParameters($data, &$pos, array &$parameters) {
		$state = self::STATE_NEW;
		$num = 0;

		for ($p = $pos + 1; isset($data[$p]); $p++) {
			switch ($state) {
				// a new parameter started
				case self::STATE_NEW:
					switch ($data[$p]) {
						case ' ':
							break;

						case ',':
							$parameters[$num++] = [
								'type' => self::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => $p - $pos
							];
							break;

						case '[':
							$_p = $p;
							$_parameters = [
								'type' => self::PARAM_ARRAY,
								'raw' => '',
								'pos' => $p - $pos,
								'parameters' => []
							];

							if (!$this->parseKeyParameters($data, $_p, $_parameters['parameters'])) {
								break 3;
							}

							foreach ($_parameters['parameters'] as $param) {
								if ($param['type'] == self::PARAM_ARRAY) {
									break 4;
								}
							}

							$_parameters['raw'] = substr($data, $p, $_p - $p);
							$parameters[$num] = $_parameters;

							$p = $_p - 1;
							$state = self::STATE_END;
							break;

						case ']':
							$parameters[$num] = [
								'type' => self::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => $p - $pos
							];
							$state = self::STATE_END_OF_PARAMS;
							break;

						case '"':
							$parameters[$num] = [
								'type' => self::PARAM_QUOTED,
								'raw' => $data[$p],
								'pos' => $p - $pos
							];
							$state = self::STATE_QUOTED;
							break;

						default:
							$parameters[$num] = [
								'type' => self::PARAM_UNQUOTED,
								'raw' => $data[$p],
								'pos' => $p - $pos
							];
							$state = self::STATE_UNQUOTED;
					}
					break;

				// end of parameter
				case self::STATE_END:
					switch ($data[$p]) {
						case ' ':
							break;

						case ',':
							$state = self::STATE_NEW;
							$num++;
							break;

						case ']':
							$state = self::STATE_END_OF_PARAMS;
							break;

						default:
							break 3;
					}
					break;

				// an unquoted parameter
				case self::STATE_UNQUOTED:
					switch ($data[$p]) {
						case ']':
							$state = self::STATE_END_OF_PARAMS;
							break;

						case ',':
							$state = self::STATE_NEW;
							$num++;
							break;

						default:
							$parameters[$num]['raw'] .= $data[$p];
					}
					break;

				// a quoted parameter
				case self::STATE_QUOTED:
					$parameters[$num]['raw'] .= $data[$p];

					if ($data[$p] == '"' && $data[$p - 1] != '\\') {
						$state = self::STATE_END;
					}
					break;

				// end of parameters
				case self::STATE_END_OF_PARAMS:
					break 2;
			}
		}

		$pos = $p;

		return ($state == self::STATE_END_OF_PARAMS);
	}

	/**
	 * Parse a filter attributes. Filter should starts with question mark character and should be defined
	 * in square brackets.
	 *
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parseKeyAttributes($source, &$pos) {
		$attributes = [];
		$pos += 2;
		$level = 0;
		$state = self::STATE_AFTER_LOGICAL_OPERATOR;
		$logical_parser = new CSetParser(['and', 'or']);
		$attribute_parser = new CAttributeParser();

		while (isset($source[$pos]) && $source[$pos] !== ']') {
			$last_pos = $pos;

			if ($source[$pos] === ' ') {
				$pos++;
				continue;
			}

			switch ($source[$pos]) {
				case '(':
					if ($state != self::STATE_AFTER_LOGICAL_OPERATOR && $state != self::STATE_AFTER_OPEN_BRACE) {
						$this->errorPos($source, $pos);

						return false;
					}

					$pos++;
					$level++;
					$state = self::STATE_AFTER_OPEN_BRACE;

					break;

				case ')':
					if ($level == 0 || $state != self::STATE_AFTER_CONSTANT) {
						$this->errorPos($source, $pos);

						return false;
					}

					$pos++;
					$level--;
					$state = self::STATE_AFTER_CLOSE_BRACE;

					break;

				default:
					if (($state == self::STATE_AFTER_CLOSE_BRACE || $state == self::STATE_AFTER_CONSTANT)
							&& $logical_parser->parse($source, $pos) != CParser::PARSE_FAIL) {
						$state = self::STATE_AFTER_LOGICAL_OPERATOR;
						$pos += $logical_parser->getLength();
					}
					else if (($state == self::STATE_AFTER_OPEN_BRACE || $state == self::STATE_AFTER_LOGICAL_OPERATOR)
							&& $attribute_parser->parse($source, $pos) != CParser::PARSE_FAIL) {
						$state = self::STATE_AFTER_CONSTANT;
						$pos += $attribute_parser->getLength();
						$attributes[] = $attribute_parser->getMatch();
					}
					else {
						$this->errorPos($source, $pos);

						return false;
					}

					break;
			}
		}

		if ($level > 0 || !isset($source[$pos]) || $source[$pos] !== ']') {
			$this->errorPos($source, strlen($source) + 1);

			return false;
		}
		$pos++;

		if (!$attributes) {
			$this->errorPos($source, $pos);

			return false;
		}

		if ($state != self::STATE_AFTER_CONSTANT && $state != self::STATE_AFTER_CLOSE_BRACE) {
			$this->errorPos($source, $last_pos);

			return false;
		}

		$this->attributes = $attributes;

		return true;
	}

	/**
	 * Returns the left part of key without parameters.
	 *
	 * @return string
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * Returns the list of key parameters.
	 *
	 * @return array
	 */
	public function getParamsRaw() {
		return $this->parameters;
	}

	/**
	 * Returns the number of key parameters.
	 *
	 * @return int
	 */
	public function getParamsNum() {
		$num = 0;

		foreach ($this->parameters as $parameter) {
			$num += count($parameter['parameters']);
		}

		return $num;
	}

	/*
	 * Unquotes special symbols in item key parameter
	 *
	 * @param string $param
	 *
	 * @return string
	 */
	public static function unquoteParam($param) {
		$unquoted = '';

		for ($p = 1; isset($param[$p]); $p++) {
			if ($param[$p] == '\\' && $param[$p + 1] == '"') {
				continue;
			}

			$unquoted .= $param[$p];
		}

		return substr($unquoted, 0, -1);
	}

	/*
	 * Quotes special symbols in item key parameter.
	 *
	 * @param string $param   Item key parameter.
	 * @param bool   $forced  true - enclose parameter in " even if it does not contain any special characters.
	 *                        false - do nothing if the parameter does not contain any special characters.
	 *
	 * @return string|bool  false - if parameter ends with backslash (cannot be quoted), string - otherwise.
	 */
	public static function quoteParam($param, $forced = false) {
		if (!$forced)
		{
			if ($param === '') {
				return $param;
			}

			if (strpos('" ', $param[0]) === false && strpos($param, ',') === false && strpos($param, ']') === false) {
				return $param;
			}
		}

		if ('\\' == substr($param, -1)) {
			return false;
		}

		return '"'.str_replace ('"', '\\"', $param).'"';
	}

	/**
	 * Returns an unquoted parameter.
	 *
	 * @param int $n	the number of the requested parameter
	 *
	 * @return string|null
	 */
	public function getParam($n) {
		$num = 0;

		foreach ($this->parameters as $parameter) {
			foreach ($parameter['parameters'] as $param) {
				if ($num++ == $n) {
					switch ($param['type']) {
						case self::PARAM_ARRAY:
							// return parameter without square brackets
							return substr($param['raw'], 1, strlen($param['raw']) - 2);
						case self::PARAM_UNQUOTED:
							// return parameter without any changes
							return $param['raw'];
						case self::PARAM_QUOTED:
							return $this->unquoteParam($param['raw']);
					}
				}
			}
		}

		return null;
	}
}
