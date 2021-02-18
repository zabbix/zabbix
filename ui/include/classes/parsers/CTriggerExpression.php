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


class CTriggerExpression {

	// For parsing of trigger expression.
	protected const STATE_AFTER_OPEN_BRACE = 1;
	protected const STATE_AFTER_BINARY_OPERATOR = 2;
	protected const STATE_AFTER_LOGICAL_OPERATOR = 3;
	protected const STATE_AFTER_NOT_OPERATOR = 4;
	protected const STATE_AFTER_MINUS_OPERATOR = 5;
	protected const STATE_AFTER_CLOSE_BRACE = 6;
	protected const STATE_AFTER_CONSTANT = 7;

	// Error type constants.
	public const ERROR_LEVEL = 1;
	public const ERROR_UNEXPECTED_ENDING = 2;
	public const ERROR_UNPARSED_CONTENT = 3;

	/**
	 * Shows a validity of trigger expression
	 *
	 * @var bool
	 */
	public $is_valid;

	/**
	 * An error message if trigger expression is not valid
	 *
	 * @var string
	 */
	public $error;

	/**
	 * Type of parsing error, on of self::ERROR_* constant or 0 when no errors.
	 *
	 * @var int
	 */
	public $error_type;

	/**
	 * In case of error contain failed position in expression string. Contain -1 when no errors.
	 *
	 * @var int
	 */
	public $error_pos;

	/**
	 * An array of trigger functions like last(/Zabbix server/agent.ping,0)
	 * The array isn't unique. Same functions can repeat.
	 *
	 * @deprecated  use result tokens instead
	 *
	 * @var array
	 */
	public $expressions = [];

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'lldmacros' => true             Enable low-level discovery macros usage in trigger expression.
	 *   'collapsed_expression' => true  Short trigger expression.
	 *                                       For example: {439} > {$MAX_THRESHOLD} or {439} < {$MIN_THRESHOLD}
	 *   'calculated' => false           Parse calculated item formula instead of trigger expression.
	 *   'host_macro'                    Array of macro supported as host name part in function.
	 *
	 * @var array
	 */
	public $options = [
		'lldmacros' => true,
		'collapsed_expression' => false,
		'calculated' => false,
		'host_macro' => []
	];

	/**
	 * Source string.
	 *
	 * @var
	 */
	public $expression;

	/**
	 * Object containing the results of parsing.
	 *
	 * @var CTriggerExprParserResult
	 */
	public $result;

	/**
	 * Current cursor position.
	 *
	 * @var
	 */
	protected $pos;

	/**
	 * Parser for binary operators.
	 *
	 * @var CSetParser
	 */
	protected $binary_operator_parser;

	/**
	 * Parser for logical operators.
	 *
	 * @var CSetParser
	 */
	protected $logical_operator_parser;

	/**
	 * Parser for the "not" operator.
	 *
	 * @var CSetParser
	 */
	protected $not_operator_parser;

	/**
	 * Parser for the {TRIGGER.VALUE} macro.
	 *
	 * @var CMacroParser
	 */
	protected $macro_parser;

	/**
	 * Parser for the {HOST.HOST} macro.
	 *
	 * @var CSetParser
	 */
	protected $host_macro_parser;

	/**
	 * Parser for function ID macros.
	 *
	 * @var CFunctionIdParser
	 */
	protected $functionid_parser;

	/**
	 * Parser for functions.
	 *
	 * @var CFunctionParser
	 */
	protected $function_parser;

	/**
	 * Parser for LLD macros.
	 *
	 * @var CLLDMacroParser
	 */
	protected $lld_macro_parser;

	/**
	 * Parser for LLD macros with functions.
	 *
	 * @var CLLDMacroFunctionParser
	 */
	protected $lld_macro_function_parser;

	/**
	 * Parser for user macros.
	 *
	 * @var CUserMacroParser
	 */
	protected $user_macro_parser;

	/**
	 * Parser for numbers with optional time or byte suffix.
	 *
	 * @var CNumberParser
	 */
	protected $number_parser;

	/**
	 * Chars that should be treated as spaces.
	 *
	 * @var array
	 */
	protected $space_chars = [' ' => true, "\r" => true, "\n" => true, "\t" => true];

	/**
	 * @param array $options
	 * @param bool  $options['lldmacros']
	 * @param bool  $options['collapsed_expression']
	 * @param bool  $options['calculated']
	 * @param bool  $options['host_macro']
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$this->binary_operator_parser = new CSetParser(['<', '>', '<=', '>=', '+', '-', '/', '*', '=', '<>']);
		$this->logical_operator_parser = new CSetParser(['and', 'or']);
		$this->not_operator_parser = new CSetParser(['not']);
		$this->macro_parser = new CMacroParser(['macros' => ['{TRIGGER.VALUE}']]);
		if ($this->options['collapsed_expression']) {
			$this->functionid_parser = new CFunctionIdParser();
		}
		elseif ($this->options['host_macro']) {
			$this->host_macro_parser = new CSetParser($this->options['host_macro']);
		}
		$this->function_parser = new CFunctionParser();
		$this->lld_macro_parser = new CLLDMacroParser();
		$this->lld_macro_function_parser = new CLLDMacroFunctionParser();
		$this->user_macro_parser = new CUserMacroParser();
		$this->number_parser = new CNumberParser(['with_minus' => false, 'with_suffix' => true]);
	}

	/**
	 * Parse a trigger expression and set public variables $this->is_valid, $this->error, $this->expressions,
	 *   $this->macros
	 *
	 * Examples:
	 *   expression:
	 *     last(/Zabbix server/agent.ping,0)=1 and {TRIGGER.VALUE}={$TRIGGER.VALUE}
	 *   results:
	 *     $this->is_valid : true
	 *     $this->error : ''
	 *     $this->expressions : array(
	 *       0 => array(
	 *         'expression' => 'last(/Zabbix server/agent.ping,0)',
	 *         'pos' => 0,
	 *         'host' => 'Zabbix server',
	 *         'item' => 'agent.ping',
	 *         'function' => 'last(0)',
	 *         'functionName' => 'last',
	 *         'functionParam' => '0',
	 *         'functionParamList' => array (0 => '0')
	 *       )
	 *     )
	 *
	 * @param string $expression
	 *
	 * @return CTriggerExprParserResult|bool   returns a result object if a match has been found or false otherwise
	 */
	public function parse(string $expression) {
		// initializing local variables
		$this->result = new CTriggerExprParserResult();
		$this->is_valid = true;
		$this->error = '';
		$this->error_type = 0;
		$this->error_pos = -1;
		$this->expressions = [];

		$this->pos = 0;
		$this->expression = $expression;

		if ($this->options['collapsed_expression'] && $this->options['host_macro']) {
			$this->is_valid = false;
			$this->error = 'Incompatible options.';
		}

		$state = self::STATE_AFTER_OPEN_BRACE;
		$after_space = false;
		$level = 0;

		while (isset($this->expression[$this->pos])) {
			$char = $this->expression[$this->pos];

			if (isset($this->space_chars[$char])) {
				$after_space = true;
				$this->pos++;
				continue;
			}

			switch ($state) {
				case self::STATE_AFTER_OPEN_BRACE:
					switch ($char) {
						case '-':
							$state = self::STATE_AFTER_MINUS_OPERATOR;
							$this->result->addToken(CTriggerExprParserResult::TOKEN_TYPE_OPERATOR,
								$char, $this->pos, 1
							);
							break;

						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$this->result->addToken(CTriggerExprParserResult::TOKEN_TYPE_OPEN_BRACE,
								$char, $this->pos, 1
							);
							$level++;
							break;

						default:
							if ($this->parseUsing($this->not_operator_parser,
									CTriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_NOT_OPERATOR;
							}
							elseif ($this->parseConstant()) {
								$state = self::STATE_AFTER_CONSTANT;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_BINARY_OPERATOR:
					switch ($char) {
						case '-':
							$state = self::STATE_AFTER_MINUS_OPERATOR;
							$this->result->addToken(CTriggerExprParserResult::TOKEN_TYPE_OPERATOR,
								$char, $this->pos, 1
							);
							break;

						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$this->result->addToken(CTriggerExprParserResult::TOKEN_TYPE_OPEN_BRACE,
								$char, $this->pos, 1
							);
							$level++;
							break;

						default:
							if ($this->parseConstant()) {
								$state = self::STATE_AFTER_CONSTANT;
								break;
							}

							if (!$after_space) {
								break 3;
							}

							if ($this->parseUsing($this->not_operator_parser,
									CTriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_NOT_OPERATOR;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_LOGICAL_OPERATOR:
					switch ($char) {
						case '-':
							if (!$after_space) {
								break 3;
							}
							$this->result->addToken(CTriggerExprParserResult::TOKEN_TYPE_OPERATOR,
								$char, $this->pos, 1
							);
							$state = self::STATE_AFTER_MINUS_OPERATOR;
							break;

						case '(':
							$this->result->addToken(CTriggerExprParserResult::TOKEN_TYPE_OPEN_BRACE,
								$char, $this->pos, 1
							);
							$state = self::STATE_AFTER_OPEN_BRACE;
							$level++;
							break;

						default:
							if (!$after_space) {
								break 3;
							}

							if ($this->parseUsing($this->not_operator_parser,
									CTriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_NOT_OPERATOR;
							}
							elseif ($this->parseConstant()) {
								$state = self::STATE_AFTER_CONSTANT;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_CLOSE_BRACE:
					switch ($char) {
						case ')':
							if ($level == 0) {
								break 3;
							}
							$this->result->addToken(CTriggerExprParserResult::TOKEN_TYPE_CLOSE_BRACE,
								$char, $this->pos, 1
							);
							$level--;
							break;

						default:
							if ($this->parseUsing($this->binary_operator_parser,
									CTriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_BINARY_OPERATOR;
								break;
							}

							if ($this->parseUsing($this->logical_operator_parser,
									CTriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_LOGICAL_OPERATOR;
								break;
							}
							break 3;
					}
					break;

				case self::STATE_AFTER_CONSTANT:
					switch ($char) {
						case ')':
							if ($level == 0) {
								break 3;
							}
							$this->result->addToken(CTriggerExprParserResult::TOKEN_TYPE_CLOSE_BRACE,
								$char, $this->pos, 1
							);
							$level--;
							$state = self::STATE_AFTER_CLOSE_BRACE;
							break;

						default:
							if ($this->parseUsing($this->binary_operator_parser,
									CTriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_BINARY_OPERATOR;
								break;
							}

							if (!$after_space) {
								break 3;
							}

							if ($this->parseUsing($this->logical_operator_parser,
									CTriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_LOGICAL_OPERATOR;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_NOT_OPERATOR:
					switch ($char) {
						case '-':
							if (!$after_space) {
								break 3;
							}
							$this->result->addToken(CTriggerExprParserResult::TOKEN_TYPE_OPERATOR,
								$char, $this->pos, 1
							);
							$state = self::STATE_AFTER_MINUS_OPERATOR;
							break;

						case '(':
							$this->result->addToken(CTriggerExprParserResult::TOKEN_TYPE_OPEN_BRACE,
								$char, $this->pos, 1
							);
							$state = self::STATE_AFTER_OPEN_BRACE;
							$level++;
							break;

						default:
							if (!$after_space) {
								break 3;
							}

							if ($this->parseConstant()) {
								$state = self::STATE_AFTER_CONSTANT;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_MINUS_OPERATOR:
					switch ($char) {
						case '(':
							$this->result->addToken(CTriggerExprParserResult::TOKEN_TYPE_OPEN_BRACE,
								$char, $this->pos, 1
							);
							$state = self::STATE_AFTER_OPEN_BRACE;
							$level++;
							break;

						default:
							if ($this->parseConstant()) {
								$state = self::STATE_AFTER_CONSTANT;
							}
							else {
								break 3;
							}
					}
					break;
			}

			$after_space = false;
			$this->pos++;
		}

		if ($this->pos == 0) {
			$this->error = $this->options['calculated']
				? _('incorrect calculated item formula')
				: _('Incorrect trigger expression.');
			$this->is_valid = false;
		}

		$errors = array_filter([
			($level != 0) ? self::ERROR_LEVEL : 0,
			($state != self::STATE_AFTER_CLOSE_BRACE && $state != self::STATE_AFTER_CONSTANT)
				? self::ERROR_UNEXPECTED_ENDING : 0,
			isset($this->expression[$this->pos]) ? self::ERROR_UNPARSED_CONTENT : 0
		]);
		$error = reset($errors);

		if ($error) {
			$exp_part = substr($this->expression, ($this->pos == 0) ? 0 : $this->pos - 1);
			$this->error = $this->options['calculated']
				? _s('incorrect calculated item formula starting from "%1$s"', $exp_part)
				: _('Incorrect trigger expression.').' '._s('Check expression part starting from "%1$s".', $exp_part);
			$this->error_type = $error;
			$this->error_pos = $this->pos;
			$this->is_valid = false;

			return false;
		}

		$this->result->source = $expression;
		$this->result->match = $expression;
		$this->result->pos = 0;
		$this->result->length = $this->pos;

		return $this->result;
	}

	/**
	 * Parse the string using the given parser. If a match has been found, move the cursor to the last symbol of the
	 * matched string.
	 *
	 * @param CParser $parser
	 * @param int     $token_type
	 *
	 * @return bool
	 */
	protected function parseUsing(CParser $parser, int $token_type): bool {
		if ($parser->parse($this->expression, $this->pos) == CParser::PARSE_FAIL) {
			return false;
		}

		$this->result->addToken($token_type, $parser->getMatch(), $this->pos, $parser->getLength());
		$this->pos += $parser->getLength() - 1;

		return true;
	}

	/**
	 * Parses a constant in the trigger expression and moves a current position ($this->pos) on a last symbol of the
	 * constant.
	 *
	 * The constant can be:
	 *  - function like func(<expression>)
	 *  - trigger function like func(/host/item,<params>)
	 *  - floating point number; can be with suffix [KMGTsmhdw]
	 *  - string
	 *  - macro like {TRIGGER.VALUE}
	 *  - user macro like {$MACRO}
	 *  - LLD macro like {#LLD}
	 *  - LLD macro with function like {{#LLD}.func())}
	 *
	 * @return bool  Returns true if parsed successfully, false otherwise.
	 */
	private function parseConstant(): bool {
		if ($this->parseNumber() || $this->parseString()
				|| $this->parseUsing($this->macro_parser, CTriggerExprParserResult::TOKEN_TYPE_MACRO)
				|| $this->parseUsing($this->user_macro_parser, CTriggerExprParserResult::TOKEN_TYPE_USER_MACRO)) {
			return true;
		}

		if ($this->options['collapsed_expression']
				&& $this->parseUsing($this->functionid_parser, CTriggerExprParserResult::TOKEN_TYPE_FUNCTIONID_MACRO)) {
			return true;
		}
		elseif ($this->parseFunction()) {
			return true;
		}

		// LLD macro support for trigger prototypes.
		if ($this->options['lldmacros']) {
			if ($this->parseUsing($this->lld_macro_parser, CTriggerExprParserResult::TOKEN_TYPE_LLD_MACRO)
					|| $this->parseUsing($this->lld_macro_function_parser,
							CTriggerExprParserResult::TOKEN_TYPE_LLD_MACRO)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parses a function constant in the trigger expression and moves a current position ($this->pos) on a last symbol
	 * of the function.
	 *
	 * @return bool  Returns true if parsed successfully, false otherwise.
	 */
	private function parseFunction(): bool {
		$start_pos = $this->pos;

		if ($this->function_parser->parse($this->expression, $this->pos) == CParser::PARSE_FAIL) {
			return false;
		}

		$this->pos += $this->function_parser->getLength() - 1;

		$function_param_list = [];

		for ($n = 0; $n < $this->function_parser->getParamsNum(); $n++) {
			$function_param_list[] = $this->function_parser->getParam($n);
		}

		$this->result->addFunctionToken($this->function_parser->result);

		$this->expressions[] = [
			'expression' => $this->function_parser->result->match,
			'pos' => $start_pos,
			'functionName' => $this->function_parser->getFunction(),
			'functionParam' => $this->function_parser->getParameters(),
			'functionParamList' => $function_param_list
		];

		return true;
	}

	/**
	 * Parses a number constant in the trigger expression and
	 * moves a current position ($this->pos) on a last symbol of the number
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private function parseNumber(): bool {
		if ($this->number_parser->parse($this->expression, $this->pos) == CParser::PARSE_FAIL) {
			return false;
		}

		$value = $this->number_parser->calcValue();
		if (abs($value) == INF) {
			return false;
		}

		$token_data = [
			'suffix' => $this->number_parser->getSuffix()
		];

		$this->result->addToken(
			CTriggerExprParserResult::TOKEN_TYPE_NUMBER,
			$this->number_parser->getMatch(),
			$this->pos,
			$this->number_parser->getLength(),
			$token_data
		);

		$this->pos += $this->number_parser->getLength() - 1;

		return true;
	}

	/**
	 * Parses a quoted string constant in the trigger expression and moves a current position ($this->pos) on a last
	 * symbol of the string.
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private function parseString(): bool {
		if (!preg_match('/^"([^"\\\\]|\\\\["\\\\])*"/', substr($this->expression, $this->pos), $matches)) {
			return false;
		}

		$len = strlen($matches[0]);

		$this->result->addToken(CTriggerExprParserResult::TOKEN_TYPE_STRING, $matches[0], $this->pos, $len,
			['string' => self::unquoteString($matches[0])]
		);

		$this->pos += $len - 1;

		return true;
	}

	/**
	 * Unquoting quoted string $value.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	public static function unquoteString(string $value): string {
		return strtr(substr($value, 1, -1), ['\\"' => '"', '\\\\' => '\\']);
	}

	/**
	 * Quoting $value if it contains a non numeric value.
	 *
	 * @param string $value
	 * @param bool   $allow_macros
	 * @param bool   $force
	 *
	 * @return string
	 */
	public static function quoteString(string $value, bool $allow_macros = true, bool $force = false): string {
		if (!$force) {
			$number_parser = new CNumberParser(['with_suffix' => true]);

			if ($number_parser->parse($value) == CParser::PARSE_SUCCESS) {
				return $value;
			}

			if ($allow_macros) {
				$user_macro_parser = new CUserMacroParser();
				$macro_parser = new CMacroParser(['macros' => ['{TRIGGER.VALUE}']]);
				$lld_macro_parser = new CLLDMacroParser();
				$lld_macro_function_parser = new CLLDMacroFunctionParser;

				if ($user_macro_parser->parse($value) == CParser::PARSE_SUCCESS
						|| $macro_parser->parse($value) == CParser::PARSE_SUCCESS
						|| $lld_macro_parser->parse($value) == CParser::PARSE_SUCCESS
						|| $lld_macro_function_parser->parse($value) == CParser::PARSE_SUCCESS) {
					return $value;
				}
			}
		}

		return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"']).'"';
	}
}
