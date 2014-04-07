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

class CTriggerExpression extends CParser {
	// for parsing of trigger expression
	const STATE_INIT = 0;
	const STATE_AFTER_OPEN_BRACE = 1;
	const STATE_AFTER_BINARY_OPERATOR = 2;
	const STATE_AFTER_UNARY_OPERATOR = 3;
	const STATE_AFTER_CLOSE_BRACE = 4;
	const STATE_AFTER_CONSTANT = 5;

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
	 * @var array
	 */
	public $expressions = array();

	/**
	 * An array of macros like {TRIGGER.VALUE}
	 * The array isn't unique. Same macros can repeats.
	 *
	 * Example:
	 *   'expressions' => array(
	 *     0 => array(
	 *       'expression' => '{TRIGGER.VALUE}'
	 *     )
	 *   )
	 *
	 * @var array
	 */
	public $macros = array();

	/**
	 * An array of user macros like {$MACRO}
	 * The array isn't unique. Same macros can repeats.
	 *
	 * Example:
	 *    array(
	 *     0 => array(
	 *       'expression' => '{$MACRO}'
	 *     ),
	 *     1 => array(
	 *       'expression' => '{$MACRO2}'
	 *     ),
	 *     2 => array(
	 *       'expression' => '{$MACRO}'
	 *     )
	 *   )
	 *
	 * @var array
	 */
	public $usermacros = array();

	/**
	 * An array of low-level discovery macros like {#MACRO}
	 * The array isn't unique. Same macros can repeats.
	 *
	 * Example:
	 *    array(
	 *     0 => array(
	 *       'expression' => '{#MACRO}'
	 *     ),
	 *     1 => array(
	 *       'expression' => '{#MACRO2}'
	 *     ),
	 *     2 => array(
	 *       'expression' => '{#MACRO}'
	 *     )
	 *   )
	 *
	 * @var array
	 */
	public $lldmacros = array();

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
	 * Supported binary operators.
	 *
	 * @var CParserTokens
	 */
	protected $binaryOperatorTokens;

	/**
	 * Supported unary operators.
	 *
	 * @var CParserTokens
	 */
	protected $unaryOperatorTokens;

	/**
	 * @param array $options
	 * @param bool $options['lldmacros']
	 */
	public function __construct($options = array()) {
		if (isset($options['lldmacros'])) {
			$this->options['lldmacros'] = $options['lldmacros'];
		}

		$this->binaryOperatorTokens = new CParserTokens(array(
			'<', '>', '<=', '>=', '+', '-', '/', '*', 'and', 'or', '=', '<>'
		));

		$this->unaryOperatorTokens = new CParserTokens(array(
			'-', 'not'
		));
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
	 * @return bool returns true if expression is valid, false otherwise
	 */
	public function parse($expression) {
		// initializing local variables
		$this->isValid = true;
		$this->error = '';
		$this->expressions = array();
		$this->macros = array();
		$this->usermacros = array();
		$this->lldmacros = array();

		$this->pos = 0;
		$this->source = $expression;

		$state = self::STATE_INIT;
		$level = 0;

		while (isset($this->source[$this->pos])) {
			switch ($state) {
				case self::STATE_INIT:
				case self::STATE_AFTER_OPEN_BRACE:
				case self::STATE_AFTER_BINARY_OPERATOR:
					switch ($this->source[$this->pos]) {
						case ' ':
						case "\r":
						case "\n":
						case "\t":
							break;
						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$level++;
							break;
						default:
							// try to parse the next token as a unary operator
							$pos = $this->pos;
							if ($this->parseUnaryOperator()) {
								$state = self::STATE_AFTER_UNARY_OPERATOR;
								break;
							}
							// if this is not an operator - return to the start position and try to parse it as a
							// constant
							$this->pos = $pos;
							if (!$this->parseConstant()) {
								break 3;
							}
							$state = self::STATE_AFTER_CONSTANT;
					}
					break;
				case self::STATE_AFTER_UNARY_OPERATOR:
					switch ($this->source[$this->pos]) {
						case ' ':
						case "\r":
						case "\n":
						case "\t":
							break;
						case '(':
							$state = self::STATE_AFTER_OPEN_BRACE;
							$level++;
							break;
						default:
							if (!$this->parseConstant()) {
								break 3;
							}
							$state = self::STATE_AFTER_CONSTANT;
					}
					break;
				case self::STATE_AFTER_CLOSE_BRACE:
				case self::STATE_AFTER_CONSTANT:
					switch ($this->source[$this->pos]) {
						case ' ':
						case "\r":
						case "\n":
						case "\t":
							break;
						case ')':
							$state = self::STATE_AFTER_CLOSE_BRACE;
							if ($level == 0) {
								break 3;
							}
							$level--;
							break;
						default:
							if (!$this->parseBinaryOperator()) {
								break 3;
							}
							$state = self::STATE_AFTER_BINARY_OPERATOR;
					}
					break;
			}
			$this->pos++;
		}

		if ($this->pos == 0) {
			$this->error = _('Incorrect trigger expression.');
			$this->isValid = false;
		}

		if ($level != 0 || isset($this->source[$this->pos]) || $state == self::STATE_AFTER_BINARY_OPERATOR || $state == self::STATE_AFTER_UNARY_OPERATOR) {
			$this->error = _('Incorrect trigger expression.').' '._s('Check expression part starting from "%1$s".',
					substr($this->source, $this->pos == 0 ? 0 : $this->pos - 1));
			$this->isValid = false;
		}

		return $this->isValid;
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
	 * Parses a binary operator and moves the current position to the last symbol of the operator.
	 *
	 * @return null|string
	 */
	protected function parseBinaryOperator() {
		return $this->parseToken($this->binaryOperatorTokens);
	}

	/**
	 * Parses a unary operator and moves the current position to the last symbol of the operator.
	 *
	 * @return null|string
	 */
	protected function parseUnaryOperator() {
		return $this->parseToken($this->unaryOperatorTokens);
	}

	/**
	 * Parses a constant in the trigger expression and moves a current position ($this->pos) on a last symbol of the constant
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
		if ($this->parseFunctionMacro() || $this->parseNumber() || $this->parseMacro() || $this->parseUserMacro()
				|| $this->parseLLDMacro()) {
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

		if (!isset($this->source[$j]) || $this->source[$j++] != '{' || ($host = $this->parseHost($j)) === null) {
			return false;
		}

		if (!isset($this->source[$j]) || $this->source[$j++] != ':' || ($item = $this->parseItem($j)) === null) {
			return false;
		}

		if (!isset($this->source[$j]) || $this->source[$j++] != '.'
				|| !(list($function, $functionParamList) = $this->parseFunction($j))) {
			return false;
		}

		if (!isset($this->source[$j]) || $this->source[$j] != '}') {
			return false;
		}

		$this->expressions[] = array(
			'expression' => substr($this->source, $this->pos, $j - $this->pos + 1),
			'pos' => $this->pos,
			'host' => $host,
			'item' => $item,
			'function' => $function,
			'functionName' => substr($function, 0, strpos($function, '(')),
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

		while (isset($this->source[$j]) && $this->isHostChar($this->source[$j])) {
			$j++;
		}

		// is host empty?
		if ($pos == $j) {
			return null;
		}

		$host = substr($this->source, $pos, $j - $pos);
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

		while (isset($this->source[$j]) && $this->isKeyChar($this->source[$j])) {
			$j++;
		}

		// for instance, agent.ping.last(0)
		if (isset($this->source[$j]) && $this->source[$j] == '(') {
			while ($j > $pos && $this->source[$j] != '.') {
				$j--;
			}
		}
		// for instance, net.tcp.port[,80]
		elseif (isset($this->source[$j]) && $this->source[$j] == '[') {
			$level = 0;
			$state = self::STATE_END;

			while (isset($this->source[$j])) {
				if ($level == 0) {
					// first square bracket + Zapcat compatibility
					if ($state == self::STATE_END && $this->source[$j] == '[') {
						$state = self::STATE_NEW;
					}
					else {
						break;
					}
				}

				switch ($state) {
					// a new parameter started
					case self::STATE_NEW:
						switch ($this->source[$j]) {
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
						switch ($this->source[$j]) {
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
						switch ($this->source[$j]) {
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
						switch ($this->source[$j]) {
							case '"':
								if ($this->source[$j - 1] != '\\') {
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

		$item = substr($this->source, $pos, $j - $pos);
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

		while (isset($this->source[$j]) && $this->isFunctionChar($this->source[$j])) {
			$j++;
		}

		// is function empty?
		if ($pos == $j) {
			return null;
		}

		if (!isset($this->source[$j]) || $this->source[$j++] != '(') {
			return null;
		}

		$state = self::STATE_NEW;
		$num = 0;
		$functionParamList = array();
		$functionParamList[$num] = '';

		while (isset($this->source[$j])) {
			switch ($state) {
				// a new parameter started
				case self::STATE_NEW:
					switch ($this->source[$j]) {
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
							$functionParamList[$num] .= $this->source[$j];
							$state = self::STATE_UNQUOTED;
					}
					break;
				// end of parameter
				case self::STATE_END:
					switch ($this->source[$j]) {
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
					switch ($this->source[$j]) {
						case ')':
							// end of parameters
							break 3;
						case ',':
							$functionParamList[++$num] = '';
							$state = self::STATE_NEW;
							break;
						default:
							$functionParamList[$num] .= $this->source[$j];
					}
					break;
				// a quoted parameter
				case self::STATE_QUOTED:
					switch ($this->source[$j]) {
						case '"':
							$state = self::STATE_END;
							break;
						case '\\':
							if (isset($this->source[$j + 1]) && $this->source[$j + 1] == '"') {
								$j++;
							}
							// break; is not missing here
						default:
							$functionParamList[$num] .= $this->source[$j];
							break;
					}
					break;
			}
			$j++;
		}

		if (!isset($this->source[$j]) || $this->source[$j++] != ')') {
			return null;
		}

		$function = substr($this->source, $pos, $j - $pos);
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

		if ($this->source[$j] < '0' || $this->source[$j] > '9') {
			return false;
		}

		$j++;
		while (isset($this->source[$j]) && $this->source[$j] >= '0' && $this->source[$j] <= '9') {
			$j++;
		}

		if (isset($this->source[$j]) && $this->source[$j] == '.') {
			$j++;
			if (!isset($this->source[$j]) || $this->source[$j] < '0' || $this->source[$j] > '9') {
				return false;
			}

			$j++;
			while (isset($this->source[$j]) && $this->source[$j] >= '0' && $this->source[$j] <= '9') {
				$j++;
			}
		}

		// check for an optional suffix
		if (isset($this->source[$j]) && strpos(ZBX_BYTE_SUFFIXES.ZBX_TIME_SUFFIXES, $this->source[$j]) !== false) {
			$j++;
		}

		$this->pos = $j - 1;
		return true;
	}

	/**
	 * Parses a macro constant in the trigger expression and
	 * moves a current position ($this->pos) on a last symbol of the macro
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private function parseMacro() {
		$macros = array('{TRIGGER.VALUE}');

		foreach ($macros as $macro) {
			$len = strlen($macro);

			if (substr($this->source, $this->pos, $len) != $macro) {
				continue;
			}

			$this->macros[] = array('expression' => $macro);
			$this->pos += $len - 1;
			return true;
		}
		return false;
	}

	/**
	 * Parses an user macro constant in the trigger expression and
	 * moves a current position ($this->pos) on a last symbol of the macro
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private function parseUserMacro() {
		$j = $this->pos;

		if ($this->source[$j++] != '{') {
			return false;
		}

		if (!isset($this->source[$j]) || $this->source[$j++] != '$') {
			return false;
		}

		if (!isset($this->source[$j]) || !$this->isMacroChar($this->source[$j++])) {
			return false;
		}

		while (isset($this->source[$j]) && $this->isMacroChar($this->source[$j])) {
			$j++;
		}

		if (!isset($this->source[$j]) || $this->source[$j] != '}') {
			return false;
		}

		$usermacro = substr($this->source, $this->pos, $j - $this->pos + 1);
		$this->usermacros[] = array('expression' => $usermacro);
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

		if ($this->source[$j++] != '{') {
			return false;
		}

		if (!isset($this->source[$j]) || $this->source[$j++] != '#') {
			return false;
		}

		if (!isset($this->source[$j]) || !$this->isMacroChar($this->source[$j++])) {
			return false;
		}

		while (isset($this->source[$j]) && $this->isMacroChar($this->source[$j])) {
			$j++;
		}

		if (!isset($this->source[$j]) || $this->source[$j] != '}') {
			return false;
		}

		$lldmacro = substr($this->source, $this->pos, $j - $this->pos + 1);
		$this->lldmacros[] = array('expression' => $lldmacro);
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
