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

class CTriggerExpression {
	// for parsing of trigger expression
	const STATE_AFTER_OPEN_BRACE = 1;
	const STATE_AFTER_BINARY_OPERATOR = 2;
	const STATE_AFTER_LOGICAL_OPERATOR = 3;
	const STATE_AFTER_NOT_OPERATOR = 4;
	const STATE_AFTER_MINUS_OPERATOR = 5;
	const STATE_AFTER_CLOSE_BRACE = 6;
	const STATE_AFTER_CONSTANT = 7;

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
	 * An array of trigger functions like {Zabbix server:agent.ping.last(0)}
	 * The array isn't unique. Same functions can repeat.
	 *
	 * @deprecated  use result tokens instead
	 *
	 * @var array
	 */
	public $expressions = [];

	/**
	 * An options array
	 *
	 * Supported options:
	 *   'lldmacros' => true	low-level discovery macros can contain in trigger expression
	 *
	 * @var array
	 */
	public $options = ['lldmacros' => true];

	/**
	 * Source string.
	 *
	 * @var
	 */
	public $expression;

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
	 * @var CFunctionMacroParser
	 */
	protected $function_macro_parser;

	/**
	 * Parser for trigger functions.
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
	 * Parser for user macros.
	 *
	 * @var CUserMacroParser
	 */
	protected $user_macro_parser;

	/**
	 * Chars that should be treated as spaces.
	 *
	 * @var array
	 */
	protected $spaceChars = [' ' => true, "\r" => true, "\n" => true, "\t" => true];

	/**
	 * Object containing the results of parsing.
	 *
	 * @var CTriggerExpressionParserResult
	 */
	protected $result;

	/**
	 * @param array $options
	 * @param bool $options['lldmacros']
	 */
	public function __construct($options = []) {
		if (isset($options['lldmacros'])) {
			$this->options['lldmacros'] = $options['lldmacros'];
		}

		$this->binaryOperatorParser = new CSetParser(['<', '>', '<=', '>=', '+', '-', '/', '*', '=', '<>']);
		$this->logicalOperatorParser = new CSetParser(['and', 'or']);
		$this->notOperatorParser = new CSetParser(['not']);
		$this->macro_parser = new CMacroParser(['{TRIGGER.VALUE}']);
		$this->function_macro_parser = new CFunctionMacroParser();
		$this->function_parser = new CFunctionParser();
		$this->lld_macro_parser = new CLLDMacroParser();
		$this->user_macro_parser = new CUserMacroParser();
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
	 * @return CTriggerExpressionParserResult|bool   returns a result object if a match has been found or false otherwise
	 */
	public function parse($expression) {
		// initializing local variables
		$this->result = new CTriggerExpressionParserResult();
		$this->isValid = true;
		$this->error = '';
		$this->expressions = [];

		$this->pos = 0;
		$this->expression = $expression;

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
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR,
								$char, $this->pos, 1
							);
							break;
						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
								$char, $this->pos, 1
							);
							$level++;
							break;
						default:
							if ($this->parseUsing($this->notOperatorParser,
									CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR)) {
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
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR,
								$char, $this->pos, 1
							);
							break;
						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
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
									CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR)) {

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
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR,
								$char, $this->pos, 1
							);
							$state = self::STATE_AFTER_MINUS_OPERATOR;
							break;
						case '(':
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
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
									CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR)) {

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
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_CLOSE_BRACE,
								$char, $this->pos, 1
							);
							$level--;
							break;
						default:
							if ($this->parseUsing($this->binaryOperatorParser,
									CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR)) {

								$state = self::STATE_AFTER_BINARY_OPERATOR;
								break;
							}

							if ($this->parseUsing($this->logicalOperatorParser,
									CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR)) {

								$state = self::STATE_AFTER_LOGICAL_OPERATOR;
								break;
							}

							if (!$afterSpace) {
								break 3;
							}

							if ($this->parseUsing($this->notOperatorParser,
									CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR)) {

								$state = self::STATE_AFTER_NOT_OPERATOR;
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
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_CLOSE_BRACE,
								$char, $this->pos, 1
							);
							$level--;
							$state = self::STATE_AFTER_CLOSE_BRACE;
							break;
						default:
							if ($this->parseUsing($this->binaryOperatorParser,
									CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR)) {

								$state = self::STATE_AFTER_BINARY_OPERATOR;
								break;
							}

							if (!$afterSpace) {
								break 3;
							}

							if ($this->parseUsing($this->notOperatorParser,
									CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR)) {

								$state = self::STATE_AFTER_NOT_OPERATOR;
							}
							elseif ($this->parseUsing($this->logicalOperatorParser,
									CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR)) {

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
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR,
								$char, $this->pos, 1
							);
							$state = self::STATE_AFTER_MINUS_OPERATOR;
							break;
						case '(':
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
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
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
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
			$this->error = _('Incorrect trigger expression.');
			$this->isValid = false;
		}

		if ($level != 0 || isset($this->expression[$this->pos])
				|| ($state != self::STATE_AFTER_CLOSE_BRACE && $state != self::STATE_AFTER_CONSTANT)) {

			$this->error = _('Incorrect trigger expression.').' '._s('Check expression part starting from "%1$s".',
					substr($this->expression, $this->pos == 0 ? 0 : $this->pos - 1));
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
	 * constant
	 *
	 * The constant can be:
	 *  - trigger function like {host:item[].func()}
	 *  - floating point number; can be with suffix [KMGTsmhdw]
	 *  - macro like {TRIGGER.VALUE}
	 *  - user macro like {$MACRO}
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private function parseConstant() {
		if ($this->parseFunctionMacro() || $this->parseNumber()
				|| $this->parseUsing($this->user_macro_parser, CTriggerExpressionParserResult::TOKEN_TYPE_USER_MACRO)
				|| $this->parseUsing($this->macro_parser, CTriggerExpressionParserResult::TOKEN_TYPE_MACRO)) {
			return true;
		}

		// LLD macro support for trigger prototypes
		if ($this->options['lldmacros']
				&& $this->parseUsing($this->lld_macro_parser, CTriggerExpressionParserResult::TOKEN_TYPE_LLD_MACRO)) {
			return true;
		}

		return false;
	}

	/**
	 * Parses a trigger function macro constant in the trigger expression and
	 * moves a current position ($this->pos) on a last symbol of the macro
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private function parseFunctionMacro() {
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

		$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_FUNCTION_MACRO,
			$this->function_macro_parser->getMatch(), $startPos, $this->function_macro_parser->getLength(),
			[
				'host' => $this->function_macro_parser->getHost(),
				'item' => $this->function_macro_parser->getItem(),
				'function' => $this->function_macro_parser->getFunction(),
				'functionName' => $this->function_parser->getFunction(),
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
	 * Parses a number constant in the trigger expression and
	 * moves a current position ($this->pos) on a last symbol of the number
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private function parseNumber() {
		$j = $this->pos;
		$digits = 0;
		$dots = 0;

		while (isset($this->expression[$j])) {
			if ($this->expression[$j] >= '0' && $this->expression[$j] <= '9') {
				$digits++;
				$j++;
				continue;
			}

			if ($this->expression[$j] === '.') {
				$dots++;
				$j++;
				continue;
			}

			break;
		}

		if ($digits == 0 || $dots > 1) {
			return false;
		}

		// check for an optional suffix
		$suffix = null;
		if (isset($this->expression[$j])
				&& strpos(ZBX_BYTE_SUFFIXES.ZBX_TIME_SUFFIXES, $this->expression[$j]) !== false) {
			$suffix = $this->expression[$j];
			$j++;
		}

		$numberLength = $j - $this->pos;

		$this->result->addToken(
			CTriggerExpressionParserResult::TOKEN_TYPE_NUMBER,
			substr($this->expression, $this->pos, $numberLength),
			$this->pos,
			$numberLength,
			['suffix' => $suffix]
		);

		$this->pos = $j - 1;

		return true;
	}
}
