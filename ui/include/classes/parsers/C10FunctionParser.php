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
 * Class is used to validate and parse a trigger function.
 */
class C10FunctionParser extends CParser {

	const STATE_NEW = 0;
	const STATE_END = 1;
	const STATE_UNQUOTED = 2;
	const STATE_QUOTED = 3;
	const STATE_END_OF_PARAMS = 4;

	const PARAM_ARRAY = 0;
	const PARAM_UNQUOTED = 1;
	const PARAM_QUOTED = 2;

	private $function = '';
	private $parameters = '';
	private $params_raw = [];

	/**
	 * Returns true if the char is allowed in the function name, false otherwise.
	 *
	 * @param string $c
	 *
	 * @return bool
	 */
	protected function isFunctionChar($c) {
		return ($c >= 'a' && $c <= 'z');
	}

	/**
	 * Parse a trigger function and parameters and put them into $this->params_raw array.
	 *
	 * @param string	$source
	 * @param int		$pos
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->function = '';
		$this->parameters = '';
		$this->params_raw = [];

		for ($p = $pos; isset($source[$p]) && $this->isFunctionChar($source[$p]); $p++) {
		}

		if ($p == $pos) {
			return self::PARSE_FAIL;
		}

		$p2 = $p;

		$params_raw = [
			'type' => self::PARAM_ARRAY,
			'raw' => '',
			'pos' => $p - $pos,
			'parameters' => []
		];
		if (!$this->parseFunctionParameters($source, $p, $params_raw['parameters'])) {
			return self::PARSE_FAIL;
		}

		$params_raw['raw'] = substr($source, $p2, $p - $p2);

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);
		$this->function = substr($source, $pos, $p2 - $pos);
		$this->parameters = substr($source, $p2 + 1, $p - $p2 - 2);
		$this->params_raw = $params_raw;

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	private function parseFunctionParameters($source, &$pos, array &$parameters) {
		if (!isset($source[$pos]) || $source[$pos] != '(') {
			return false;
		}

		$_parameters = [];
		$state = self::STATE_NEW;
		$num = 0;

		for ($p = $pos + 1; isset($source[$p]); $p++) {
			switch ($state) {
				// a new parameter started
				case self::STATE_NEW:
					switch ($source[$p]) {
						case ' ':
							break;

						case ',':
							$_parameters[$num++] = [
								'type' => self::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => $p - $pos
							];
							break;

						case ')':
							$_parameters[$num] = [
								'type' => self::PARAM_UNQUOTED,
								'raw' => '',
								'pos' => $p - $pos
							];
							$state = self::STATE_END_OF_PARAMS;
							break;

						case '"':
							$_parameters[$num] = [
								'type' => self::PARAM_QUOTED,
								'raw' => $source[$p],
								'pos' => $p - $pos
							];
							$state = self::STATE_QUOTED;
							break;

						default:
							$_parameters[$num] = [
								'type' => self::PARAM_UNQUOTED,
								'raw' => $source[$p],
								'pos' => $p - $pos
							];
							$state = self::STATE_UNQUOTED;
					}
					break;

				// end of parameter
				case self::STATE_END:
					switch ($source[$p]) {
						case ' ':
							break;

						case ',':
							$state = self::STATE_NEW;
							$num++;
							break;

						case ')':
							$state = self::STATE_END_OF_PARAMS;
							break;

						default:
							break 3;
					}
					break;

				// an unquoted parameter
				case self::STATE_UNQUOTED:
					switch ($source[$p]) {
						case ')':
							$state = self::STATE_END_OF_PARAMS;
							break;

						case ',':
							$state = self::STATE_NEW;
							$num++;
							break;

						default:
							$_parameters[$num]['raw'] .= $source[$p];
					}
					break;

				// a quoted parameter
				case self::STATE_QUOTED:
					$_parameters[$num]['raw'] .= $source[$p];

					if ($source[$p] == '"' && $source[$p - 1] != '\\') {
						$state = self::STATE_END;
					}
					break;

				// end of parameters
				case self::STATE_END_OF_PARAMS:
					break 2;
			}
		}

		if ($state == self::STATE_END_OF_PARAMS) {
			$parameters = $_parameters;
			$pos = $p;

			return true;
		}

		return false;
	}

	/**
	 * Returns the left part of the trigger function without parameters.
	 *
	 * @return string
	 */
	public function getFunction() {
		return $this->function;
	}

	/**
	 * Returns the parameters of the function.
	 *
	 * @return string
	 */
	public function getParameters() {
		return $this->parameters;
	}

	/**
	 * Returns the list of the parameters.
	 *
	 * @return array
	 */
	public function getParamsRaw() {
		return $this->params_raw;
	}

	/**
	 * Returns the number of the parameters.
	 *
	 * @return int
	 */
	public function getParamsNum() {
		return array_key_exists('parameters', $this->params_raw) ? count($this->params_raw['parameters']) : 0;
	}

	/*
	 * Unquotes special symbols in item the parameter
	 *
	 * @param string $param
	 *
	 * @return string
	 */
	private static function unquoteParam($param) {
		$unquoted = '';

		for ($p = 1; isset($param[$p]); $p++) {
			if ($param[$p] == '\\' && $param[$p + 1] == '"') {
				continue;
			}

			$unquoted .= $param[$p];
		}

		return substr($unquoted, 0, -1);
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

		foreach ($this->params_raw['parameters'] as $param) {
			if ($num++ == $n) {
				switch ($param['type']) {
					case self::PARAM_UNQUOTED:
						// return parameter without any changes
						return $param['raw'];
					case self::PARAM_QUOTED:
						return $this->unquoteParam($param['raw']);
				}
			}
		}

		return null;
	}

	/**
	 * Returns unquoted parameters.
	 *
	 * @return array
	 */
	public function getParams(): array {
		if (!array_key_exists('parameters', $this->params_raw)) {
			return [];
		}

		$parameters = [];

		foreach ($this->params_raw['parameters'] as $param) {
			$parameters[] = $param['type'] == self::PARAM_QUOTED ? $this->unquoteParam($param['raw']) : $param['raw'];
		}

		return $parameters;
	}
}
