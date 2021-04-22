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
class CHistFunctionParser extends CParser {

	protected const STATE_NEW = 0;
	protected const STATE_END = 1;
	protected const STATE_QUOTED = 3;
	protected const STATE_END_OF_PARAMS = 4;

	public const PARAM_TYPE_QUERY = 0;
	public const PARAM_TYPE_PERIOD = 1;
	public const PARAM_TYPE_QUOTED = 2;
	public const PARAM_TYPE_UNQUOTED = 3;

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'usermacros' => false  Enable user macros usage in function parameters.
	 *   'lldmacros' => false   Enable low-level discovery macros usage in function parameters.
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false
	];

	private $query_parser;
	private $period_parser;
	private $user_macro_parser;
	private $lld_macro_parser;
	private $lld_macro_function_parser;
	private $number_parser;

	/**
	 * Parsed function name.
	 *
	 * @var string
	 */
	private $function = '';

	/**
	 * The list of the parsed function parameters.
	 *
	 * @var array
	 */
	private $parameters = [];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$this->query_parser = new CQueryParser();
		$this->period_parser = new CPeriodParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros']
		]);
		if ($this->options['usermacros']) {
			$this->user_macro_parser = new CUserMacroParser();
		}
		if ($this->options['lldmacros']) {
			$this->lld_macro_parser = new CLLDMacroParser();
			$this->lld_macro_function_parser = new CLLDMacroFunctionParser();
		}
		$this->number_parser = new CNumberParser([
			'with_minus' => true,
			'with_suffix' => true
		]);
	}

	/**
	 * Parse a function and parameters and put them into $this->params_raw array.
	 *
	 * @param string  $source
	 * @param int     $pos
	 */
	public function parse($source, $pos = 0): int {
		$this->length = 0;
		$this->match = '';
		$this->function = '';

		$p = $pos;

		if (!preg_match('/^([a-z]+)\(/', substr($source, $p), $matches)) {
			return self::PARSE_FAIL;
		}

		$p += strlen($matches[0]);
		$p2 = $p - 1;

		$parameters = [];
		if (!$this->parseFunctionParameters($source, $p, $parameters)) {
			return self::PARSE_FAIL;
		}

		$params_raw['raw'] = substr($source, $p2, $p - $p2);

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);
		$this->function = $matches[1];
		$this->parameters = $parameters;

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
		$p = $pos;

		$_parameters = [];
		$state = self::STATE_NEW;
		$num = 0;

		// The list of parsers for unquoted parameters.
		$parsers = [$this->number_parser];
		if ($this->options['usermacros']) {
			$parsers[] = $this->user_macro_parser;
		}
		if ($this->options['lldmacros']) {
			$parsers[] = $this->lld_macro_parser;
			$parsers[] = $this->lld_macro_function_parser;
		}

		while (isset($source[$p])) {
			switch ($state) {
				// a new parameter started
				case self::STATE_NEW:
					if ($source[$p] !== ' ') {
						if ($num == 0) {
							if ($this->query_parser->parse($source, $p) != CParser::PARSE_FAIL) {
								$p += $this->query_parser->getLength() - 1;
								$_parameters[$num] = [
									'type' => self::PARAM_TYPE_QUERY,
									'pos' => $this->query_parser->result->pos,
									'match' => $this->query_parser->result->match,
									'length' => $this->query_parser->result->length
								];
								$state = self::STATE_END;
							}
							else {
								break 2;
							}
						}
						elseif ($num == 1) {
							switch ($source[$p]) {
								case ',':
									$_parameters[$num++] = [
										'type' => self::PARAM_TYPE_UNQUOTED,
										'pos' => $p,
										'match' => '',
										'length' => 0
									];
									break;

								case ')':
									$_parameters[$num] = [
										'type' => self::PARAM_TYPE_UNQUOTED,
										'pos' => $p,
										'match' => '',
										'length' => 0
									];
									$state = self::STATE_END_OF_PARAMS;
									break;

								default:
									if ($this->period_parser->parse($source, $p) != CParser::PARSE_FAIL) {
										$p += $this->period_parser->getLength() - 1;
										$_parameters[$num] = [
											'type' => self::PARAM_TYPE_PERIOD,
											'pos' => $this->period_parser->result->pos,
											'match' => $this->period_parser->result->match,
											'length' => $this->period_parser->result->length
										];
										$state = self::STATE_END;
									}
									else {
										break 3;
									}
							}
						}
						else {
							switch ($source[$p]) {
								case ',':
									$_parameters[$num++] = [
										'type' => self::PARAM_TYPE_UNQUOTED,
										'pos' => $p,
										'match' => '',
										'length' => 0
									];
									break;

								case ')':
									$_parameters[$num] = [
										'type' => self::PARAM_TYPE_UNQUOTED,
										'pos' => $p,
										'match' => '',
										'length' => 0
									];
									$state = self::STATE_END_OF_PARAMS;
									break;

								case '"':
									$_parameters[$num] = [
										'type' => self::PARAM_TYPE_QUOTED,
										'pos' => $p,
										'match' => $source[$p],
										'length' => 1
									];
									$state = self::STATE_QUOTED;
									break;

								default:
									foreach ($parsers as $parser) {
										if ($parser->parse($source, $p) != CParser::PARSE_FAIL) {
											$_parameters[$num] = [
												'type' => self::PARAM_TYPE_UNQUOTED,
												'pos' => $p,
												'match' => $parser->getMatch(),
												'length' => $parser->getLength()
											];

											$p += $parser->getLength() - 1;
											$state = self::STATE_END;
										}
									}

									if ($state != self::STATE_END) {
										break 3;
									}
							}
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

				// a quoted parameter
				case self::STATE_QUOTED:
					$_parameters[$num]['match'] .= $source[$p];
					$_parameters[$num]['length']++;

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
		return $this->function;
	}

	/**
	 * Returns the parameters of the function.
	 *
	 * @return array
	 */
	public function getParameters(): array {
		return $this->parameters;
	}

	/*
	 * Unquotes special symbols in the parameter.
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
	public function getParam(int $num): ?string {
		if (!array_key_exists($num, $this->parameters)) {
			return null;
		}

		$param = $this->parameters[$num];

		return ($param['type'] == self::PARAM_TYPE_QUOTED) ? self::unquoteParam($param['match']) : $param['match'];
	}
}
