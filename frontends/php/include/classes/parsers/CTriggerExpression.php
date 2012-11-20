<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

class CTriggerExpression {
	// for parsing of trigger expression
	const STATE_INIT = 0;
	const STATE_AFTER_OPEN_BRACE = 1;
	const STATE_AFTER_OPERATOR = 2;
	const STATE_AFTER_MINUS = 3;
	const STATE_AFTER_CLOSE_BRACE = 4;
	const STATE_AFTER_CONSTANT = 5;

	// for parse of item key parameters
	const STATE_NEW = 0;
	const STATE_END = 1;
	const STATE_UNQUOTED = 2;
	const STATE_QUOTED = 3;

	public	$isValid = true;
	public	$error = '';
	private	$pos;
	public	$expressions = array();
	private	$expression_num = 0;
	public	$macros = array();
	public	$usermacros = array();
	private	$expression;
	public	$expressionShort = '';

	/**
	 * Parse a trigger expression and set local variables
	 *
	 * $this->error       - if error occured
	 * $this->pos         - a length of the valid expression or a pointer to a not valid element
	 * $this->expressions - a list of trigger functions, used in the expression
	 * $this->macros      - a list of macros, used in the expression
	 * $this->usermacros  - a list of user macros, used in the expression
	 *
	 * Examples:
	 *   expression:
	 *     {Zabbix server:agent.ping.lats(0)}=1 & {TRIGGER.VALUE}={$TRIGGER.VALUE}
	 *   results:
	 *     'isValid' => true
	 *     'error' => ''
	 *     'expressions' => array(
	 *       0 => array(
	 *         'index' => 0
	 *         'expression' => '{Zabbix server:agent.ping.last(0)}',
	 *         'host' => 'Zabbix server',
	 *         'item' => 'agent.ping',
	 *         'function' => 'last(0)',
	 *         'functionName' => 'last',
	 *         'functionParam' => '0',
	 *         'functionParamList' => array (0 => '0')
	 *       )
	 *     )
	 *     'macros' => array(
	 *       0 => array(
	 *         'expression' => '{TRIGGER.VALUE}'
	 *       )
	 *     )
	 *     'usermacros' => array(
	 *       0 => array(
	 *         'expression' => '{$TRIGGER.VALUE}'
	 *       )
	 *     )
	 *     'expressionShort' => {0}=1 & {TRIGGER.VALUE}={$TRIGGER.VALUE}
	 *
	 * @param string $expression
	 *
	 * @return boot
	 */
	/**
	 */
	public function parse($expression) {
		$this->expression = $expression;
		$this->pos = 0;
		$state = self::STATE_INIT;
		$level = 0;

		while (isset($this->expression[$this->pos])) {
			switch ($state) {
				case self::STATE_INIT:
				case self::STATE_AFTER_OPEN_BRACE:
				case self::STATE_AFTER_OPERATOR:
					switch ($this->expression[$this->pos]) {
						case ' ':
							$this->expressionShort .= $this->expression[$this->pos];
							break;
						case '-':
							$this->expressionShort .= $this->expression[$this->pos];
							$state = self::STATE_AFTER_MINUS;
							break;
						case '(':
							$this->expressionShort .= $this->expression[$this->pos];
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
				case self::STATE_AFTER_MINUS:
					switch ($this->expression[$this->pos]) {
						case ' ':
							$this->expressionShort .= $this->expression[$this->pos];
							break;
						case '(':
							$this->expressionShort .= $this->expression[$this->pos];
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
					switch ($this->expression[$this->pos]) {
						case ' ':
							$this->expressionShort .= $this->expression[$this->pos];
							break;
						case '=':
						case '#':
						case '<':
						case '>':
						case '&':
						case '|':
						case '+':
						case '-':
						case '/':
						case '*':
							$this->expressionShort .= $this->expression[$this->pos];
							$state = self::STATE_AFTER_OPERATOR;
							break;
						case ')':
							$this->expressionShort .= $this->expression[$this->pos];
							$state = self::STATE_AFTER_CLOSE_BRACE;
							if ($level == 0) {
								break 3;
							}
							$level--;
							break;
						default:
							break 3;
					}
					break;
			}
			$this->pos++;
		}

		if ($this->pos == 0) {
			$this->error = _('Incorrect trigger expression.');
			$this->isValid = false;
		}

		if ($level != 0 || isset($this->expression[$this->pos]) || $state == self::STATE_AFTER_OPERATOR || $state == self::STATE_AFTER_MINUS) {
			$this->error = _('Incorrect trigger expression.').' '._s('Check expression part starting from "%1$s".',
					substr($this->expression, $this->pos == 0 ? 0 : $this->pos - 1));
			$this->isValid = false;
		}

		return $this->isValid;
	}

	/**
	 * Returns a list of the unique hosts, used in a parsed trigger expression or empty array if expression is not valid
	 */
	public function getHosts() {
		if (!$this->isValid) {
			return array();
		}

		return array_unique(zbx_objectValues($this->expressions, 'host'));
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
	 * Returns true if parsed successfully, false otherwise
	 */
	private function parseConstant() {
		if ($this->parseFunctionMacro() || $this->parseNumber() || $this->parseMacro() || $this->parseUserMacro()) {
			return true;
		}

		return false;
	}

	/**
	 * Parses a trigger function macro constant in the trigger expression and
	 * moves a current position ($this->pos) on a last symbol of the macro
	 *
	 * Returns true if parsed successfully, false otherwise
	 */
	private function parseFunctionMacro() {
		$j = $this->pos;

		if (!isset($this->expression[$j]) || $this->expression[$j++] != '{' || !($host = $this->parseHost($j))) {
			return false;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j++] != ':' || !($item = $this->parseItem($j))) {
			return false;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j++] != '.'
				|| !(list($function, $functionParamList) = $this->parseFunction($j))) {
			return false;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j] != '}') {
			return false;
		}

		$this->expressions[$this->expression_num]['index'] = $this->expression_num;
		$this->expressions[$this->expression_num]['expression'] = substr($this->expression, $this->pos, $j - $this->pos + 1);
		$this->expressions[$this->expression_num]['host'] = $host;
		$this->expressions[$this->expression_num]['item'] = $item;
		$this->expressions[$this->expression_num]['function'] = $function;
		$this->expressions[$this->expression_num]['functionName'] = substr($function, 0, strpos($function, '('));
		$this->expressions[$this->expression_num]['functionParam'] = substr($function, strpos($function, '(') + 1, -1);
		$this->expressions[$this->expression_num]['functionParamList'] = $functionParamList;
		$this->expressionShort .= '{'.$this->expression_num.'}';
		$this->expression_num++;
		$this->pos = $j;
		return true;
	}

	/**
	 * Parses a host in a trigger function macro constant and moves a position ($pos) on a next symbol after the host
	 *
	 * Returns a host name if parsed successfully or null otherwise
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
	 * Returns an item name if parsed successfully or null otherwise
	 */
	private function parseItem(&$pos)
	{
		$j = $pos;

		while (isset($this->expression[$j]) && $this->isKeyChar($this->expression[$j])) {
			$j++;
		}

		// for instance, agent.ping.last(0)
		if (isset($this->expression[$j]) && $this->expression[$j] == '(') {
			for (; $j > $pos && $this->expression[$j] != '.'; $j--)
				;
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
	 * Returns true if parsed successfully, false otherwise
	 */
	private function parseNumber() {
		$j = $this->pos;

		if ($this->expression[$j] < '0' || $this->expression[$j] > '9') {
			return false;
		}

		$j++;
		while (isset($this->expression[$j]) && $this->expression[$j] >= '0' && $this->expression[$j] <= '9') {
			$j++;
		}

		if (isset($this->expression[$j]) && $this->expression[$j] == '.') {
			$j++;
			if (!isset($this->expression[$j]) || $this->expression[$j] < '0' || $this->expression[$j] > '9') {
				return false;
			}

			$j++;
			while (isset($this->expression[$j]) && $this->expression[$j] >= '0' && $this->expression[$j] <= '9') {
				$j++;
			}
		}

		// check for an optional suffix
		if (isset($this->expression[$j]) && strpos('KMGTsmhdw', $this->expression[$j]) !== false) {
			$j++;
		}

		$this->expressionShort .= substr($this->expression, $this->pos, $j - $this->pos);
		$this->pos = $j - 1;
		return true;
	}

	/**
	 * Parses a macro constant in the trigger expression and
	 * moves a current position ($this->pos) on a last symbol of the macro
	 *
	 * Returns true if parsed successfully, false otherwise
	 */
	private function parseMacro() {
		if (strcmp(substr($this->expression, $this->pos, 15), '{TRIGGER.VALUE}') != 0) {
			return false;
		}

		$macro = substr($this->expression, $this->pos, 15);
		$this->macros[]['expression'] = $macro;
		$this->expressionShort .= $macro;
		$this->pos += 14;
		return true;
	}

	/**
	 * Parses an user macro constant in the trigger expression and
	 * moves a current position ($this->pos) on a last symbol of the macro
	 *
	 * Returns true if parsed successfully, false otherwise
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

		$usermacro = substr($this->expression, $this->pos, $j - $this->pos + 1);
		$this->usermacros[]['expression'] = $usermacro;
		$this->expressionShort .= $usermacro;
		$this->pos = $j;
		return true;
	}

	/**
	 * Returns true if the char is allowed in the host name, false otherwise
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
	 */
	private function isFunctionChar($c) {
		if ($c >= 'a' && $c <= 'z') {
			return true;
		}

		return false;
	}

	/**
	 * Returns true if the char is allowed in the macro, false otherwise
	 */
	private function isMacroChar($c) {
		if (($c >= 'A' && $c <= 'Z') || $c == '.' || $c == '_' || ($c >= '0' && $c <= '9')) {
			return true;
		}

		return false;
	}
}
