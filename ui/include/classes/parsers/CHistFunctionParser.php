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
	public const PARAM_TYPE_EMPTY = 4;

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'usermacros' => false         Enable user macros usage in function parameters.
	 *   'lldmacros' => false          Enable low-level discovery macros usage in function parameters.
	 *   'host_macro' => false         Allow {HOST.HOST} macro as host name part in the query.
	 *   'host_macro_n' => false       Allow {HOST.HOST} and {HOST.HOST<1-9>} macros as host name part in the query.
	 *   'empty_host' => false         Allow empty hostname in the query string.
	 *   'escape_backslashes' => true  Disable backslash escaping in history function parameters prior to v7.0.
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false,
		'calculated' => false,
		'host_macro' => false,
		'host_macro_n' => false,
		'empty_host' => false,
		'escape_backslashes' => true
	];

	private $query_parser;
	private $period_parser;

	/**
	 * The list of parsers for unquoted parameters.
	 *
	 * @var array
	 */
	private $unquoted_param_parsers = [];

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

		$this->query_parser = new CQueryParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros'],
			'calculated' => $this->options['calculated'],
			'host_macro' => $this->options['host_macro'],
			'host_macro_n' => $this->options['host_macro_n'],
			'empty_host' => $this->options['empty_host']
		]);
		$this->period_parser = new CPeriodParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros']
		]);
		$this->unquoted_param_parsers[] = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true,
			'with_year' => true
		]);
		if ($this->options['usermacros']) {
			array_push($this->unquoted_param_parsers, new CUserMacroParser, new CUserMacroFunctionParser);
		}
		if ($this->options['lldmacros']) {
			array_push($this->unquoted_param_parsers, new CLLDMacroParser, new CLLDMacroFunctionParser);
		}
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

		if (!preg_match('/^([a-z_]+)\(/', substr($source, $p), $matches)) {
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

		while (isset($source[$p])) {
			switch ($state) {
				// a new parameter started
				case self::STATE_NEW:
					if ($source[$p] !== ' ') {
						if ($num == 0) {
							if ($this->query_parser->parse($source, $p) != CParser::PARSE_FAIL) {
								$_parameters[$num] = [
									'type' => self::PARAM_TYPE_QUERY,
									'pos' => $p,
									'match' => $this->query_parser->getMatch(),
									'length' => $this->query_parser->getLength(),
									'data' => [
										'host' => $this->query_parser->getHost(),
										'item' => $this->query_parser->getItem(),
										'filter' => $this->query_parser->getFilter()
									]
								];
								$p += $this->query_parser->getLength() - 1;
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
										'type' => self::PARAM_TYPE_EMPTY,
										'pos' => $p,
										'match' => '',
										'length' => 0
									];
									break;

								case ')':
									$_parameters[$num] = [
										'type' => self::PARAM_TYPE_EMPTY,
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
									if ($this->period_parser->parse($source, $p) != CParser::PARSE_FAIL) {
										$_parameters[$num] = [
											'type' => self::PARAM_TYPE_PERIOD,
											'pos' => $p,
											'match' => $this->period_parser->getMatch(),
											'length' => $this->period_parser->getLength(),
											'data' => [
												'sec_num' => $this->period_parser->getSecNum(),
												'time_shift' => $this->period_parser->getTimeshift()
											]
										];
										$p += $this->period_parser->getLength() - 1;
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
										'type' => self::PARAM_TYPE_EMPTY,
										'pos' => $p,
										'match' => '',
										'length' => 0
									];
									break;

								case ')':
									$_parameters[$num] = [
										'type' => self::PARAM_TYPE_EMPTY,
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
									foreach ($this->unquoted_param_parsers as $parser) {
										if ($parser->parse($source, $p) != CParser::PARSE_FAIL) {
											$_parameters[$num] = [
												'type' => self::PARAM_TYPE_UNQUOTED,
												'pos' => $p,
												'match' => $parser->getMatch(),
												'length' => $parser->getLength()
											];

											$p += $parser->getLength() - 1;
											$state = self::STATE_END;
											break;
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

					if (!$this->options['escape_backslashes']) {
						if ($source[$p] === '"' && $source[$p - 1] !== '\\') {
							$state = self::STATE_END;
						}

						break;
					}

					switch ($source[$p]) {
						case '\\':
							if (!isset($source[$p + 1]) || ($source[$p + 1] !== '"' && $source[$p + 1] !== '\\')) {
								break 3;
							}

							$_parameters[$num]['match'] .= $source[$p + 1];
							$_parameters[$num]['length']++;
							$p++;

							break;

						case '"':
							$state = self::STATE_END;
							break;
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

	/**
	 * Unquotes special symbols in the parameter.
	 *
	 * @param string $param
	 * @param array  $options
	 *
	 * @return string
	 */
	public static function unquoteParam(string $param, array $options = []): string {
		$options += ['unescape_backslashes' => true];
		$replace_pairs = $options['unescape_backslashes'] ? ['\\"' => '"', '\\\\' => '\\'] : ['\\"' => '"'];

		return strtr(substr($param, 1, -1), $replace_pairs);
	}

	/**
	 * @param string $param
	 * @param bool   $force
	 * @param array  $options
	 *
	 * @return string
	 */
	public static function quoteParam(string $param, bool $force = false, array $options = []): string {
		$options += ['usermacros' => false, 'lldmacros' => false, 'escape_backslashes' => true];

		if (!$force) {

			$unquoted_param_parsers = [new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true,
				'with_year' => true
			])];
			if ($options['usermacros']) {
				array_push($unquoted_param_parsers, new CUserMacroParser, new CUserMacroFunctionParser);
			}
			if ($options['lldmacros']) {
				array_push($unquoted_param_parsers, new CLLDMacroParser, new CLLDMacroFunctionParser);
			}

			foreach ($unquoted_param_parsers as $parser) {
				if ($parser->parse($param) == CParser::PARSE_SUCCESS) {
					return $param;
				}
			}
		}

		$replace_pairs = $options['escape_backslashes'] ? ['\\' => '\\\\', '"' => '\\"'] : ['"' => '\\"'];

		return '"'.strtr($param, $replace_pairs).'"';
	}

	/**
	 * Returns an unquoted parameter.
	 *
	 * @param int $num  The number of the requested parameter.
	 *
	 * @return string|null
	 */
	public function getParam(int $num): ?string {
		if (!array_key_exists($num, $this->parameters)) {
			return null;
		}

		$param = $this->parameters[$num];

		return $param['type'] == self::PARAM_TYPE_QUOTED
			? self::unquoteParam($param['match'], ['unescape_backslashes' => $this->options['escape_backslashes']])
			: $param['match'];
	}
}
