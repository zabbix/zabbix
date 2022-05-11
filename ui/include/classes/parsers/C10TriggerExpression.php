<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Trigger expressions parser.
 */
class C10TriggerExpression {
	// For parsing of trigger expression.
	const STATE_AFTER_OPEN_BRACE = 1;
	const STATE_AFTER_BINARY_OPERATOR = 2;
	const STATE_AFTER_LOGICAL_OPERATOR = 3;
	const STATE_AFTER_NOT_OPERATOR = 4;
	const STATE_AFTER_MINUS_OPERATOR = 5;
	const STATE_AFTER_CLOSE_BRACE = 6;
	const STATE_AFTER_CONSTANT = 7;

	// Error type constants.
	const ERROR_LEVEL = 1;
	const ERROR_UNEXPECTED_ENDING = 2;
	const ERROR_UNPARSED_CONTENT = 3;

	/**
	 * Shows a validity of trigger expression
	 *
	 * @var bool
	 */
	public $isValid;

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
	 * An array of trigger functions like {Zabbix server:agent.ping.last(0)}
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
	 *   'allow_func_only' => true       Allow trigger expression without host:key pair, i.e. {func(param)}.
	 *   'collapsed_expression' => true  Short trigger expression.
	 *                                       For example: {439} > {$MAX_THRESHOLD} or {439} < {$MIN_THRESHOLD}
	 *   'calculated' => false           Parse calculated item formula instead of trigger expression.
	 *   'host_macro'                    Array of macro supported as host name part in function.
	 *
	 * @var array
	 */
	public $options = [
		'lldmacros' => true,
		'allow_func_only' => false,
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
	 * @var C10TriggerExprParserResult
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
	protected $binaryOperatorParser;

	/**
	 * Parser for logical operators.
	 *
	 * @var CSetParser
	 */
	protected $logicalOperatorParser;

	/**
	 * Parser for the "not" operator.
	 *
	 * @var CSetParser
	 */
	protected $notOperatorParser;

	/**
	 * Parser for the {TRIGGER.VALUE} macro.
	 *
	 * @var CMacroParser
	 */
	protected $macro_parser;

	/**
	 * Parser for function macros.
	 *
	 * @var C10FunctionMacroParser
	 */
	protected $function_macro_parser;

	/**
	 * Parser for function id macros.
	 *
	 * @var CFunctionIdParser
	 */
	protected $functionid_parser;

	/**
	 * Parser for trigger functions.
	 *
	 * @var C10FunctionParser
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
	protected $spaceChars = [' ' => true, "\r" => true, "\n" => true, "\t" => true];

	/**
	 * @param array $options
	 * @param bool  $options['lldmacros']
	 * @param bool  $options['allow_func_only']
	 * @param bool  $options['collapsed_expression']
	 * @param bool  $options['calculated']
	 * @param bool  $options['host_macro']
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$this->binaryOperatorParser = new CSetParser(['<', '>', '<=', '>=', '+', '-', '/', '*', '=', '<>']);
		$this->logicalOperatorParser = new CSetParser(['and', 'or']);
		$this->notOperatorParser = new CSetParser(['not']);
		$this->macro_parser = new CMacroParser(['macros' => ['{TRIGGER.VALUE}']]);
		if ($this->options['collapsed_expression']) {
			$this->functionid_parser = new CFunctionIdParser();
		}
		else {
			$this->function_macro_parser = new C10FunctionMacroParser(['host_macro' => $this->options['host_macro']]);
		}
		$this->function_parser = new C10FunctionParser();
		$this->lld_macro_parser = new CLLDMacroParser();
		$this->lld_macro_function_parser = new CLLDMacroFunctionParser;
		$this->user_macro_parser = new CUserMacroParser();
		$this->number_parser = new CNumberParser([
			'with_minus' => false,
			'with_size_suffix' => true,
			'with_time_suffix' => true
		]);
	}

	/**
	 * Parse a trigger expression and set public variables $this->isValid, $this->error, $this->expressions,
	 *   $this->macros
	 *
	 * Examples:
	 *   expression:
	 *     {Zabbix server:agent.ping.last(0)}=1 and {TRIGGER.VALUE}={$TRIGGER.VALUE}
	 *   results:
	 *     $this->isValid : true
	 *     $this->error : ''
	 *     $this->expressions : array(
	 *       0 => array(
	 *         'expression' => '{Zabbix server:agent.ping.last(0)}',
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
	 * @return C10TriggerExprParserResult|bool   returns a result object if a match has been found or false otherwise
	 */
	public function parse($expression) {
		// initializing local variables
		$this->result = new C10TriggerExprParserResult();
		$this->isValid = true;
		$this->error = '';
		$this->error_type = 0;
		$this->error_pos = -1;
		$this->expressions = [];

		$this->pos = 0;
		$this->expression = $expression;

		if ($this->options['collapsed_expression'] && $this->options['allow_func_only']) {
			$this->isValid = false;
			$this->error = 'Incompatible options.';
		}

		$state = self::STATE_AFTER_OPEN_BRACE;
		$afterSpace = false;
		$level = 0;

		while (isset($this->expression[$this->pos])) {
			$char = $this->expression[$this->pos];

			if (isset($this->spaceChars[$char])) {
				$afterSpace = true;
				$this->pos++;
				continue;
			}

			switch ($state) {
				case self::STATE_AFTER_OPEN_BRACE:
					switch ($char) {
						case '-':
							$state = self::STATE_AFTER_MINUS_OPERATOR;
							$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_OPERATOR,
								$char, $this->pos, 1
							);
							break;

						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_OPEN_BRACE,
								$char, $this->pos, 1
							);
							$level++;
							break;

						default:
							if ($this->parseUsing($this->notOperatorParser,
									C10TriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
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
							$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_OPERATOR,
								$char, $this->pos, 1
							);
							break;

						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_OPEN_BRACE,
								$char, $this->pos, 1
							);
							$level++;
							break;

						default:
							if ($this->parseConstant()) {
								$state = self::STATE_AFTER_CONSTANT;
								break;
							}

							if (!$afterSpace) {
								break 3;
							}

							if ($this->parseUsing($this->notOperatorParser,
									C10TriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
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
							if (!$afterSpace) {
								break 3;
							}
							$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_OPERATOR,
								$char, $this->pos, 1
							);
							$state = self::STATE_AFTER_MINUS_OPERATOR;
							break;

						case '(':
							$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_OPEN_BRACE,
								$char, $this->pos, 1
							);
							$state = self::STATE_AFTER_OPEN_BRACE;
							$level++;
							break;

						default:
							if (!$afterSpace) {
								break 3;
							}

							if ($this->parseUsing($this->notOperatorParser,
									C10TriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
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
							$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_CLOSE_BRACE,
								$char, $this->pos, 1
							);
							$level--;
							break;

						default:
							if ($this->parseUsing($this->binaryOperatorParser,
									C10TriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_BINARY_OPERATOR;
								break;
							}

							if ($this->parseUsing($this->logicalOperatorParser,
									C10TriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_LOGICAL_OPERATOR;
								break;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_CONSTANT:
					switch ($char) {
						case ')':
							if ($level == 0) {
								break 3;
							}
							$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_CLOSE_BRACE,
								$char, $this->pos, 1
							);
							$level--;
							$state = self::STATE_AFTER_CLOSE_BRACE;
							break;

						default:
							if ($this->parseUsing($this->binaryOperatorParser,
									C10TriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_BINARY_OPERATOR;
								break;
							}

							if (!$afterSpace) {
								break 3;
							}

							if ($this->parseUsing($this->logicalOperatorParser,
									C10TriggerExprParserResult::TOKEN_TYPE_OPERATOR)) {
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
							if (!$afterSpace) {
								break 3;
							}
							$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_OPERATOR,
								$char, $this->pos, 1
							);
							$state = self::STATE_AFTER_MINUS_OPERATOR;
							break;

						case '(':
							$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_OPEN_BRACE,
								$char, $this->pos, 1
							);
							$state = self::STATE_AFTER_OPEN_BRACE;
							$level++;
							break;

						default:
							if (!$afterSpace) {
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
							$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_OPEN_BRACE,
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

			$afterSpace = false;
			$this->pos++;
		}

		if ($this->pos == 0) {
			$this->error = $this->options['calculated']
				? _('incorrect calculated item formula')
				: _('Incorrect trigger expression.');
			$this->isValid = false;
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
			$this->isValid = false;

			return false;
		}

		$this->result->source = $expression;
		$this->result->match = $expression;
		$this->result->pos = 0;
		$this->result->length = $this->pos;

		return $this->result;
	}

	/**
	 * Returns a list of the unique hosts, used in a parsed trigger expression or empty array if expression is not valid
	 *
	 * @return array
	 */
	public function getHosts() {
		if (!$this->isValid) {
			return [];
		}

		return array_unique(zbx_objectValues($this->expressions, 'host'));
	}

	/**
	 * Parse the string using the given parser. If a match has been found, move the cursor to the last symbol of the
	 * matched string.
	 *
	 * @param CParser   $parser
	 * @param int       $tokenType
	 *
	 * @return bool
	 */
	protected function parseUsing(CParser $parser, $tokenType) {
		if ($parser->parse($this->expression, $this->pos) == CParser::PARSE_FAIL) {
			return false;
		}

		$this->result->addToken($tokenType, $parser->getMatch(), $this->pos, $parser->getLength());
		$this->pos += $parser->getLength() - 1;

		return true;
	}

	/**
	 * Parses a constant in the trigger expression and moves a current position ($this->pos) on a last symbol of the
	 * constant.
	 *
	 * The constant can be:
	 *  - trigger function like {host:item[].func()}; can be without host:item pair
	 *  - floating point number; can be with suffix [KMGTsmhdw]
	 *  - macro like {TRIGGER.VALUE}
	 *  - user macro like {$MACRO}
	 *  - LLD macro like {#LLD}
	 *  - LLD macro with function like {{#LLD}.func())}
	 *
	 * @return bool  Returns true if parsed successfully, false otherwise.
	 */
	private function parseConstant() {
		if ($this->parseNumber() || $this->parseString()
				|| $this->parseUsing($this->user_macro_parser, C10TriggerExprParserResult::TOKEN_TYPE_USER_MACRO)) {
			return true;
		}

		if ($this->options['calculated']) {
			if ($this->parseFunction()) {
				return true;
			}
		}
		elseif ($this->parseFunctionMacro()
				|| $this->parseUsing($this->macro_parser, C10TriggerExprParserResult::TOKEN_TYPE_MACRO)) {
			return true;
		}

		// LLD macro support for trigger prototypes.
		if ($this->options['lldmacros']) {
			if ($this->parseUsing($this->lld_macro_parser, C10TriggerExprParserResult::TOKEN_TYPE_LLD_MACRO)
					|| $this->parseUsing($this->lld_macro_function_parser,
							C10TriggerExprParserResult::TOKEN_TYPE_LLD_MACRO)) {
				return true;
			}
		}

		return ($this->options['allow_func_only'] && $this->parseFunctionOnly());
	}

	/**
	 * Parses a trigger function in the trigger expression and moves a current position ($this->pos) on a last symbol of
	 * the trigger function.
	 *
	 * @return bool  Returns true if parsed successfully, false otherwise.
	 */
	private function parseFunctionOnly() {
		$pos = $this->pos;

		if ($this->expression[$pos] !== '{') {
			return false;
		}

		$pos++;

		if ($this->function_parser->parse($this->expression, $pos) == CParser::PARSE_FAIL) {
			return false;
		}

		$pos += $this->function_parser->getLength();

		if (isset($this->expression[$pos]) && $this->expression[$pos] !== '}') {
			return false;
		}

		$function_param_list = [];

		for ($n = 0; $n < $this->function_parser->getParamsNum(); $n++) {
			$function_param_list[] = $this->function_parser->getParam($n);
		}

		$expression = substr($this->expression, $this->pos, $pos + 1 - $this->pos);

		$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_FUNCTION_MACRO,
			$expression, $this->pos, $this->function_parser->getLength() + 2,
			[
				'host' => '',
				'item' => '',
				'function' => $this->function_parser->getMatch(),
				'functionName' => $this->function_parser->getFunction(),
				'functionParamsRaw' => $this->function_parser->getParamsRaw(),
				'functionParams' => $function_param_list
			]
		);

		$this->expressions[] = [
			'expression' => $expression,
			'pos' => $this->pos,
			'host' => '',
			'item' => '',
			'function' => $this->function_parser->getMatch(),
			'functionName' => $this->function_parser->getFunction(),
			'functionParam' => $this->function_parser->getParameters(),
			'functionParamList' => $function_param_list
		];

		$this->pos = $pos;

		return true;
	}

	/**
	 * Parses a trigger function macro constant in the trigger expression and
	 * moves a current position ($this->pos) on a last symbol of the macro
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private function parseFunctionMacro() {
		if ($this->options['collapsed_expression']) {
			return $this->parseUsing($this->functionid_parser, C10TriggerExprParserResult::TOKEN_TYPE_FUNCTIONID_MACRO);
		}
		else {
			return $this->parseSimpleMacro();
		}
	}

	/**
	 * Parses a simple macro constant {host:key.func()} in the trigger expression and
	 * moves a current position ($this->pos) on a last symbol of the macro
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private function parseSimpleMacro() {
		$startPos = $this->pos;

		if ($this->function_macro_parser->parse($this->expression, $this->pos) == CParser::PARSE_FAIL) {
			return false;
		}

		if ($this->function_parser->parse($this->function_macro_parser->getFunction()) == CParser::PARSE_FAIL) {
			return false;
		}

		$this->pos += $this->function_macro_parser->getLength() - 1;

		$function_param_list = [];

		for ($n = 0; $n < $this->function_parser->getParamsNum(); $n++) {
			$function_param_list[] = $this->function_parser->getParam($n);
		}

		$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_FUNCTION_MACRO,
			$this->function_macro_parser->getMatch(), $startPos, $this->function_macro_parser->getLength(),
			[
				'host' => $this->function_macro_parser->getHost(),
				'item' => $this->function_macro_parser->getItem(),
				'function' => $this->function_macro_parser->getFunction(),
				'functionName' => $this->function_parser->getFunction(),
				'functionParamsRaw' => $this->function_parser->getParamsRaw(),
				'functionParams' => $function_param_list
			]
		);

		$this->expressions[] = [
			'expression' => $this->function_macro_parser->getMatch(),
			'pos' => $startPos,
			'host' => $this->function_macro_parser->getHost(),
			'item' => $this->function_macro_parser->getItem(),
			'function' => $this->function_macro_parser->getFunction(),
			'functionName' => $this->function_parser->getFunction(),
			'functionParam' => $this->function_parser->getParameters(),
			'functionParamList' => $function_param_list
		];

		return true;
	}

	/**
	 * Parses a function in the calculated item formula and moves
	 * a current position ($this->pos) on a last symbol of the macro.
	 *
	 * @return bool  Returns true if parsed successfully, false otherwise.
	 */
	private function parseFunction() {
		$startPos = $this->pos;

		if ($this->function_parser->parse($this->expression, $this->pos) == CParser::PARSE_FAIL) {
			return false;
		}

		$this->pos += $this->function_parser->getLength() - 1;

		$function_param_list = [];

		for ($n = 0; $n < $this->function_parser->getParamsNum(); $n++) {
			$function_param_list[] = $this->function_parser->getParam($n);
		}

		$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_FUNCTION,
			$this->function_parser->getMatch(), $startPos, $this->function_parser->getLength(),
			[
				'functionName' => $this->function_parser->getFunction(),
				'functionParamsRaw' => $this->function_parser->getParamsRaw(),
				'functionParams' => $function_param_list
			]
		);

		$this->expressions[] = [
			'expression' => $this->function_parser->getMatch(),
			'pos' => $startPos,
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
	private function parseNumber() {
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
			C10TriggerExprParserResult::TOKEN_TYPE_NUMBER,
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
	private function parseString() {
		if (!preg_match('/^"([^"\\\\]|\\\\["\\\\])*"/', substr($this->expression, $this->pos), $matches)) {
			return false;
		}

		$len = strlen($matches[0]);

		$this->result->addToken(C10TriggerExprParserResult::TOKEN_TYPE_STRING, $matches[0], $this->pos, $len,
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
			$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

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
