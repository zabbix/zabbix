<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

	// for parsing of item key parameters
	const STATE_NEW = 0;
	const STATE_END = 1;
	const STATE_UNQUOTED = 2;
	const STATE_QUOTED = 3;

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
	 * The array isn't unique. Same functions can repeats.
	 *
	 * Example:
	 *   'expressions' => array(
	 *     0 => array(
	 *       'expression' => '{Zabbix server:agent.ping.last(0)}',
	 *       'pos' => 0,
	 *       'host' => 'Zabbix server',
	 *       'item' => 'agent.ping',
	 *       'function' => 'last(0)',
	 *       'functionName' => 'last',
	 *       'functionParam' => '0',
	 *       'functionParamList' => array (0 => '0')
	 *     )
	 *   )
	 *
	 * @deprecated  use result tokens instead
	 *
	 * @var array
	 */
	public $expressions = array();

	/**
	 * An options array
	 *
	 * Supported otions:
	 *   'lldmacros' => true	low-level discovery macros can contain in trigger expression
	 *
	 * @var array
	 */
	public $options = array('lldmacros' => true);

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
	 * @var CSetParser
	 */
	protected $macroParser;

	/**
	 * Chars that should be treated as spaces.
	 *
	 * @var array
	 */
	protected $spaceChars = array(' ' => true, "\r" => true, "\n" => true, "\t" => true);

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
	public function __construct($options = array()) {
		if (isset($options['lldmacros'])) {
			$this->options['lldmacros'] = $options['lldmacros'];
		}

		$this->binaryOperatorParser = new CSetParser(array('<', '>', '<=', '>=', '+', '-', '/', '*', '=', '<>'));
		$this->logicalOperatorParser = new CSetParser(array('and', 'or'));
		$this->notOperatorParser = new CSetParser(array('not'));
		$this->macroParser = new CSetParser(array('{TRIGGER.VALUE}'));
	}

	/**
	 * Parse a trigger expression and set public variables $this->isValid, $this->error, $this->expressions,
	 *   $this->macros, $this->usermacros and $this->lldmacros
	 *
	 * Examples:
	 *   expression:
	 *     {Zabbix server:agent.ping.lats(0)}=1 & {TRIGGER.VALUE}={$TRIGGER.VALUE}
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
	 *     $this->macros : array(
	 *       0 => array(
	 *         'expression' => '{TRIGGER.VALUE}'
	 *       )
	 *     )
	 *     $this->usermacros : array(
	 *       0 => array(
	 *         'expression' => '{$TRIGGER.VALUE}'
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
		$this->expressions = array();

		$this->pos = 0;
		$this->expression = $expression;

		$state = self::STATE_AFTER_OPEN_BRACE;
		$afterSpace = false;
		$level = 0;

		while (isset($this->expression[$this->pos])) {
			if (isset($this->spaceChars[$this->expression[$this->pos]])) {
				$afterSpace = true;
				$this->pos++;
				continue;
			}

			switch ($state) {
				case self::STATE_AFTER_OPEN_BRACE:
					switch ($this->expression[$this->pos]) {
						case '-':
							$state = self::STATE_AFTER_MINUS_OPERATOR;
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR,
								'-', $this->pos, 1
							);
							break;
						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
								'(', $this->pos, 1
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
					switch ($this->expression[$this->pos]) {
						case '-':
							$state = self::STATE_AFTER_MINUS_OPERATOR;
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR,
								'-', $this->pos, 1
							);
							break;
						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
								'(', $this->pos, 1
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
					switch ($this->expression[$this->pos]) {
						case '-':
							if (!$afterSpace) {
								break 3;
							}
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR,
								'-', $this->pos, 1
							);
							$state = self::STATE_AFTER_MINUS_OPERATOR;
							break;
						case '(':
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
								'(', $this->pos, 1
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
					switch ($this->expression[$this->pos]) {
						case ')':
							if ($level == 0) {
								break 3;
							}
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_CLOSE_BRACE,
								'(', $this->pos, 1
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
					switch ($this->expression[$this->pos]) {
						case ')':
							if ($level == 0) {
								break 3;
							}
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_CLOSE_BRACE,
								')', $this->pos, 1
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
					switch ($this->expression[$this->pos]) {
						case '-':
							if (!$afterSpace) {
								break 3;
							}
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR,
								'-', $this->pos, 1
							);
							$state = self::STATE_AFTER_MINUS_OPERATOR;
							break;
						case '(':
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
								'(', $this->pos, 1
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
					switch ($this->expression[$this->pos]) {
						case '(':
							$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
								'(', $this->pos, 1
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
			return array();
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
	 * @return CParserResult|bool		CParserResult object if a match has been found, false otherwise
	 */
	protected function parseUsing(CParser $parser, $tokenType) {
		$j = $this->pos;

		$result = $parser->parse($this->expression, $j);

		if (!$result) {
			return false;
		}

		$this->pos += $result->length - 1;

		$this->result->addToken($tokenType, $result->match, $result->pos, $result->length);

		return $result;
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
				|| $this->parseUsing($this->macroParser, CTriggerExpressionParserResult::TOKEN_TYPE_MACRO)
				|| $this->parseUserMacro() || $this->parseLLDMacro()) {

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
		$j = $this->pos;

		if (!isset($this->expression[$j]) || $this->expression[$j++] != '{' || ($host = $this->parseHost($j)) === null) {
			return false;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j++] != ':' || ($item = $this->parseItem($j)) === null) {
			return false;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j++] != '.'
				|| !(list($function, $functionParamList) = $this->parseFunction($j))) {
			return false;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j] != '}') {
			return false;
		}

		$expressionLength = $j - $this->pos + 1;
		$expression = substr($this->expression, $this->pos, $expressionLength);
		$functionName = substr($function, 0, strpos($function, '('));

		$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_FUNCTION_MACRO, $expression,
			$this->pos, $expressionLength,
			array(
				'host' => $host,
				'item' => $item,
				'function' => $function,
				'functionName' => $functionName,
				'functionParams' => $functionParamList
			)
		);

		$this->expressions[] = array(
			'expression' => $expression,
			'pos' => $this->pos,
			'host' => $host,
			'item' => $item,
			'function' => $function,
			'functionName' => $functionName,
			'functionParam' => substr($function, strpos($function, '(') + 1, -1),
			'functionParamList' => $functionParamList
		);
		$this->pos = $j;
		return true;
	}

	/**
	 * Parses a host in a trigger function macro constant and moves a position ($pos) on a next symbol after the host
	 *
	 * @return string returns a host name if parsed successfully or null otherwise
	 */
	private function parseHost(&$pos)
	{
		$j = $pos;

		while (isset($this->expression[$j]) && $this->isHostChar($this->expression[$j])) {
			$j++;
		}

		// is host empty?
		if ($pos == $j) {
			return null;
		}

		$host = substr($this->expression, $pos, $j - $pos);
		$pos = $j;
		return $host;
	}

	/**
	 * Parses an item in a trigger function macro constant and moves a position ($pos) on a next symbol after the item
	 *
	 * @return string returns an item name if parsed successfully or null otherwise
	 */
	private function parseItem(&$pos)
	{
		$j = $pos;

		while (isset($this->expression[$j]) && $this->isKeyChar($this->expression[$j])) {
			$j++;
		}

		// for instance, agent.ping.last(0)
		if (isset($this->expression[$j]) && $this->expression[$j] == '(') {
			while ($j > $pos && $this->expression[$j] != '.') {
				$j--;
			}
		}
		// for instance, net.tcp.port[,80]
		elseif (isset($this->expression[$j]) && $this->expression[$j] == '[') {
			$level = 0;
			$state = self::STATE_END;

			while (isset($this->expression[$j])) {
				if ($level == 0) {
					// first square bracket + Zapcat compatibility
					if ($state == self::STATE_END && $this->expression[$j] == '[') {
						$state = self::STATE_NEW;
					}
					else {
						break;
					}
				}

				switch ($state) {
					// a new parameter started
					case self::STATE_NEW:
						switch ($this->expression[$j]) {
							case ' ':
							case ',':
								break;
							case '[':
								$level++;
								break;
							case ']':
								$level--;
								$state = self::STATE_END;
								break;
							case '"':
								$state = self::STATE_QUOTED;
								break;
							default:
								$state = self::STATE_UNQUOTED;
						}
						break;
					// end of parameter
					case self::STATE_END:
						switch ($this->expression[$j]) {
							case ' ':
								break;
							case ',':
								$state = self::STATE_NEW;
								break;
							case ']':
								$level--;
								break;
							default:
								return null;
						}
						break;
					// an unquoted parameter
					case self::STATE_UNQUOTED:
						switch ($this->expression[$j]) {
							case ']':
								$level--;
								$state = self::STATE_END;
								break;
							case ',':
								$state = self::STATE_NEW;
								break;
						}
						break;
					// a quoted parameter
					case self::STATE_QUOTED:
						switch ($this->expression[$j]) {
							case '"':
								if ($this->expression[$j - 1] != '\\') {
									$state = self::STATE_END;
								}
								break;
						}
						break;
				}
				$j++;
			}

			if ($level != 0) {
				return null;
			}
		}

		// is key empty?
		if ($pos == $j) {
			return null;
		}

		$item = substr($this->expression, $pos, $j - $pos);
		$pos = $j;
		return $item;
	}

	/**
	 * Parses an function in a trigger function macro constant and moves a position ($pos) on a next symbol after the function
	 *
	 * Returns an array if parsed successfully or null otherwise
	 * Returned array contains two elements:
	 *   0 => function name like "last(0)"
	 *   1 => array of parsed function parameters
	 *
	 * @return array
	 */
	private function parseFunction(&$pos)
	{
		$j = $pos;

		while (isset($this->expression[$j]) && $this->isFunctionChar($this->expression[$j])) {
			$j++;
		}

		// is function empty?
		if ($pos == $j) {
			return null;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j++] != '(') {
			return null;
		}

		$state = self::STATE_NEW;
		$num = 0;
		$functionParamList = array();
		$functionParamList[$num] = '';

		while (isset($this->expression[$j])) {
			switch ($state) {
				// a new parameter started
				case self::STATE_NEW:
					switch ($this->expression[$j]) {
						case ' ':
							break;
						case ',':
							$functionParamList[++$num] = '';
							break;
						case ')':
							// end of parameters
							break 3;
						case '"':
							$state = self::STATE_QUOTED;
							break;
						default:
							$functionParamList[$num] .= $this->expression[$j];
							$state = self::STATE_UNQUOTED;
					}
					break;
				// end of parameter
				case self::STATE_END:
					switch ($this->expression[$j]) {
						case ' ':
							break;
						case ',':
							$functionParamList[++$num] = '';
							$state = self::STATE_NEW;
							break;
						case ')':
							// end of parameters
							break 3;
						default:
							return null;
					}
					break;
				// an unquoted parameter
				case self::STATE_UNQUOTED:
					switch ($this->expression[$j]) {
						case ')':
							// end of parameters
							break 3;
						case ',':
							$functionParamList[++$num] = '';
							$state = self::STATE_NEW;
							break;
						default:
							$functionParamList[$num] .= $this->expression[$j];
					}
					break;
				// a quoted parameter
				case self::STATE_QUOTED:
					switch ($this->expression[$j]) {
						case '"':
							$state = self::STATE_END;
							break;
						case '\\':
							if (isset($this->expression[$j + 1]) && $this->expression[$j + 1] == '"') {
								$j++;
							}
							// break; is not missing here
						default:
							$functionParamList[$num] .= $this->expression[$j];
							break;
					}
					break;
			}
			$j++;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j++] != ')') {
			return null;
		}

		$function = substr($this->expression, $pos, $j - $pos);
		$pos = $j;
		return array($function, $functionParamList);
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
			array(
				'suffix' => $suffix
			)
		);

		$this->pos = $j - 1;

		return true;
	}

	/**
	 * Parses an user macro constant in the trigger expression and
	 * moves a current position ($this->pos) on a last symbol of the macro
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private function parseUserMacro() {
		$j = $this->pos;

		if ($this->expression[$j++] != '{') {
			return false;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j++] != '$') {
			return false;
		}

		if (!isset($this->expression[$j]) || !$this->isMacroChar($this->expression[$j++])) {
			return false;
		}

		while (isset($this->expression[$j]) && $this->isMacroChar($this->expression[$j])) {
			$j++;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j] != '}') {
			return false;
		}

		$macroLength = $j - $this->pos + 1;
		$usermacro = substr($this->expression, $this->pos, $macroLength);
		$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_USER_MACRO, $usermacro,
			$this->pos, $macroLength
		);

		$this->pos = $j;

		return true;
	}

	/**
	 * Parses a low-level discovery macro constant in the trigger expression and
	 * moves a current position ($this->pos) on a last symbol of the macro
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private function parseLLDMacro() {
		if (!$this->options['lldmacros']) {
			return false;
		}

		$j = $this->pos;

		if ($this->expression[$j++] != '{') {
			return false;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j++] != '#') {
			return false;
		}

		if (!isset($this->expression[$j]) || !$this->isMacroChar($this->expression[$j++])) {
			return false;
		}

		while (isset($this->expression[$j]) && $this->isMacroChar($this->expression[$j])) {
			$j++;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j] != '}') {
			return false;
		}

		$macroLength = $j - $this->pos + 1;
		$lldmacro = substr($this->expression, $this->pos, $j - $this->pos + 1);
		$this->result->addToken(CTriggerExpressionParserResult::TOKEN_TYPE_LLD_MACRO, $lldmacro,
			$this->pos, $macroLength
		);

		$this->pos = $j;

		return true;
	}

	/**
	 * Returns true if the char is allowed in the host name, false otherwise
	 *
	 * @return bool
	 */
	private function isHostChar($c) {
		if (($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || ($c >= '0' && $c <= '9')
				|| $c == '.' || $c == ' ' || $c == '_' || $c == '-') {
			return true;
		}

		return false;
	}

	/**
	 * Returns true if the char is allowed in the item key, false otherwise
	 *
	 * @return bool
	 */
	private function isKeyChar($c) {
		if (($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || ($c >= '0' && $c <= '9')
				|| $c == '.' || $c == '_' || $c == '-') {
			return true;
		}

		return false;
	}

	/**
	 * Returns true if the char is allowed in the function name, false otherwise
	 *
	 * @return bool
	 */
	private function isFunctionChar($c) {
		if ($c >= 'a' && $c <= 'z') {
			return true;
		}

		return false;
	}

	/**
	 * Returns true if the char is allowed in the macro, false otherwise
	 *
	 * @return bool
	 */
	private function isMacroChar($c) {
		if (($c >= 'A' && $c <= 'Z') || $c == '.' || $c == '_' || ($c >= '0' && $c <= '9')) {
			return true;
		}

		return false;
	}
}
