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
 * Class is used to validate and parse a function.
 */
class CFunctionParser extends CParser {

	protected const STATE_NEW = 0;
	protected const STATE_END = 1;
	protected const STATE_UNQUOTED = 2;
	protected const STATE_QUOTED = 3;
	protected const STATE_END_OF_PARAMS = 4;

	public const PARAM_ARRAY = 0;
	public const PARAM_UNQUOTED = 1;
	public const PARAM_QUOTED = 2;

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'collapsed_expression' => false  Short trigger expression.
	 *
	 * @var array
	 */
	protected $options = [
		'collapsed_expression' => false
	];

	/**
	 * Object of parsed function.
	 *
	 * @var CFunctionParserResult
	 */
	public $result;

	/**
	 * Depth of function in hierarchy of nested functions.
	 *
	 * @var int
	 */
	public $depth;

	/**
	 * @param array $options
	 * @param bool  $options['collapsed_expression']
	 */
	public function __construct(array $options = [], int $depth = 1) {
		$this->options = $options + $this->options;
		$this->depth = $depth;
	}

	/**
	 * Returns true if the char is allowed in the function name, false otherwise.
	 *
	 * @param string $c
	 *
	 * @return bool
	 */
	protected function isFunctionChar(string $c): bool {
		return ($c >= 'a' && $c <= 'z');
	}

	/**
	 * Parse a function and parameters and put them into $this->params_raw array.
	 *
	 * @param string  $source
	 * @param int     $pos
	 */
	public function parse($source, $pos = 0): int {
		$this->errorClear();

		if ($this->depth > TRIGGER_MAX_FUNCTION_DEPTH) {
			$this->errorPos($source, $pos);
			return self::PARSE_FAIL;
		}

		$this->result = new CFunctionParserResult();
		$this->length = 0;

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
		$this->result->length = $this->length;
		$this->result->match = substr($source, $pos, $this->length);
		$this->result->function = substr($source, $pos, $p2 - $pos);
		$this->result->parameters = substr($source, $p2 + 1, $p - $p2 - 2);
		$this->result->params_raw = $params_raw;
		$this->result->pos = $pos;

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * @param string $source
	 * @param int    $pos
	 * @param array  $parameters
	 *
	 * @return bool
	 */
	protected function parseFunctionParameters(string $source, int &$pos, array &$parameters): bool {
		if (!isset($source[$pos]) || $source[$pos] !== '(') {
			return false;
		}

		$p = $pos + 1;

		$_parameters = [];
		$state = self::STATE_NEW;
		$num = 0;

		$query_parser = new CQueryParser($this->options);
		$function_parser = new self($this->options, $this->depth + 1);

		if ($this->options['collapsed_expression']) {
			$functionid_parser = new CFunctionIdParser();
		}

		while (isset($source[$p])) {
			switch ($state) {
				// a new parameter started
				case self::STATE_NEW:
					switch ($source[$p]) {
						case ' ':
							break;

						case ',':
							if (array_key_exists($num, $_parameters)) {
								// Not overwrite previous empty parameter.
								$num++;
							}
							$_parameters[$num] = new CFunctionParameterResult([
								'type' => self::PARAM_UNQUOTED,
								'match' => '',
								'pos' => $p
							]);
							break;

						case ')':
							$state = self::STATE_END_OF_PARAMS;
							break;

						case '"':
							$_parameters[$num] = new CFunctionParameterResult([
								'type' => self::PARAM_QUOTED,
								'match' => $source[$p],
								'pos' => $p
							]);
							$state = self::STATE_QUOTED;
							break;

						default:
							if ($query_parser->parse($source, $p) != CParser::PARSE_FAIL) {
								$p += $query_parser->getLength();
								$_parameters[$num++] = $query_parser->result;
								$state = self::STATE_NEW;
								$p--;
							}
							elseif ($function_parser->parse($source, $p) != CParser::PARSE_FAIL) {
								$p += $function_parser->getLength();
								$_parameters[$num++] = $function_parser->result;
								$state = self::STATE_NEW;
								$p--;
							}
							elseif ($this->options['collapsed_expression']
									&& $functionid_parser->parse($source, $p) != CParser::PARSE_FAIL) {
								$p += $functionid_parser->getLength();
								$_parameters[$num++] = $functionid_parser->result;
								$state = self::STATE_NEW;
								$p--;
							}
							else {
								$error = $query_parser->getErrorDetails();

								if ($error && $error[1] > $p) {
									$this->errorPos($error[0], $error[1]);
									break 3;
								}

								if ($function_parser->getError() !== '') {
									[$source, $pos] = $function_parser->getErrorDetails();
									$this->errorPos($source, $pos);
									break 3;
								}

								if (!array_key_exists($num, $_parameters)) {
									$_parameters[$num] = new CFunctionParameterResult([
										'type' => self::PARAM_UNQUOTED,
										'match' => $source[$p],
										'pos' => $p
									]);
								}
								else {
									$_parameters[$num]->match .= $source[$p];
								}

								$state = self::STATE_UNQUOTED;
							}
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
							$_parameters[$num]->match .= $source[$p];
					}
					break;

				// a quoted parameter
				case self::STATE_QUOTED:
					$_parameters[$num]->match .= $source[$p];

					if ($source[$p] === '"' && $source[$p - 1] !== '\\') {
						$state = self::STATE_END;
					}
					break;

				// end of parameters
				case self::STATE_END_OF_PARAMS:
					break 2;
			}

			$p++;
		}

		if ($state == self::STATE_END_OF_PARAMS) {
			$parameters = $_parameters;
			$pos = $p;

			return true;
		}

		return false;
	}

	/**
	 * Returns the left part of the function without parameters.
	 *
	 * @return string
	 */
	public function getFunction(): string {
		return $this->result->function;
	}

	/**
	 * Returns the parameters of the function.
	 *
	 * @return string
	 */
	public function getParameters(): string {
		return $this->result->parameters;
	}

	/**
	 * Returns the list of the parameters.
	 *
	 * @return array
	 */
	public function getParamsRaw(): array {
		return $this->result->params_raw;
	}

	/**
	 * Returns the number of the parameters.
	 *
	 * @return int
	 */
	public function getParamsNum(): int {
		return array_key_exists('parameters', $this->result->params_raw)
			? count($this->result->params_raw['parameters'])
			: 0;
	}

	/*
	 * Unquotes special symbols in the item parameter.
	 *
	 * @param string  $param
	 *
	 * @return string
	 */
	public static function unquoteParam(string $param): string {
		$unquoted = '';

		for ($p = 1; isset($param[$p]); $p++) {
			if ($param[$p] === '\\' && $param[$p + 1] === '"') {
				continue;
			}

			$unquoted .= $param[$p];
		}

		return substr($unquoted, 0, -1);
	}

	/**
	 * Returns an unquoted parameter.
	 *
	 * @param int $n  The number of the requested parameter.
	 *
	 * @return string|null
	 */
	public function getParam(int $n): ?string {
		$num = 0;

		foreach ($this->result->params_raw['parameters'] as $param) {
			if ($num++ == $n) {
				if ($param instanceof CParserResult) {
					return $param->match;
				}
				elseif ($param->type == self::PARAM_UNQUOTED) {
					// return parameter without any changes
					return $param->match;
				}
				elseif ($param->type == self::PARAM_QUOTED) {
					return self::unquoteParam($param->match);
				}
			}
		}

		return null;
	}
}
