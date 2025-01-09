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


/**
 * Class is used to validate and parse item keys.
 */
class CItemKey extends CParser {

	const STATE_NEW = 0;
	const STATE_END = 1;
	const STATE_UNQUOTED = 2;
	const STATE_QUOTED = 3;
	const STATE_END_OF_PARAMS = 4;

	const PARAM_ARRAY = 0;
	const PARAM_UNQUOTED = 1;
	const PARAM_QUOTED = 2;

	private $key = ''; // main part of the key (for 'key[1, 2, 3]' key id would be 'key')
	private $parameters = [];

	/**
	 * An options array
	 *
	 * Supported options:
	 *   '18_simple_checks' => true		with support for old-style simple checks like "ftp,{$PORT}"
	 *
	 * @var array
	 */
	private $options = ['18_simple_checks' => false];

	/**
	 * @param array $options
	 */
	public function __construct($options = []) {
		$this->error_msgs['empty'] = _('key is empty');
		$this->error_msgs['unexpected_end'] = _('unexpected end of key');

		if (array_key_exists('18_simple_checks', $options)) {
			$this->options['18_simple_checks'] = $options['18_simple_checks'];
		}
	}

	/**
	 * Check if given character is a valid key id char
	 * this function is a copy of zbx_is_key_char() from src/libs/zbxexpr/expr.c
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

		for ($p = $offset; isset($data[$p]) && $this->isKeyChar($data[$p]); $p++) {
			// Code is not missing here.
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
