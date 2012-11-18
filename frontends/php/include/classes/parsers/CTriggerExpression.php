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

/* for parsing of trigger expression */
define('STATE_INIT', 0);
define('STATE_AFTER_OPEN_BRACE', 1);
define('STATE_AFTER_OPERATOR', 2);
define('STATE_AFTER_MINUS', 3);
define('STATE_AFTER_CLOSE_BRACE', 4);
define('STATE_AFTER_CONSTANT', 5);

/* for parse of item key parameters */
define('ZBX_STATE_NEW', 0);
define('ZBX_STATE_END', 1);
define('ZBX_STATE_UNQUOTED', 2);
define('ZBX_STATE_QUOTED', 3);

class CTriggerExpression {
	public	$isValid;
	public	$error;
	private	$pos;
	public	$expressions;
	private	$expression_num;
	public	$macros;
	private	$macro_num;
	public	$usermacros;
	private	$usermacro_num;
	private	$expression;
	public	$expressionShort;

	/**
	 * Parse trigger expression
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
	 */
	public function __construct($expression) {
		$this->expression = $expression;
		$this->initialize();
		$this->parseExpression();
	}

	public function getHosts() {
		if (!$this->isValid) {
			return array();
		}

		return array_unique(zbx_objectValues($this->expressions, 'host'));
	}

	private function parseExpression() {
		for ($this->pos = 0, $state = STATE_INIT, $level = 0; isset($this->expression[$this->pos]); $this->pos++) {
			switch ($state) {
				case STATE_INIT:
				case STATE_AFTER_OPEN_BRACE:
				case STATE_AFTER_OPERATOR:
					switch ($this->expression[$this->pos]) {
						case ' ':
							$this->expressionShort .= $this->expression[$this->pos];
							break;
						case '-':
							$this->expressionShort .= $this->expression[$this->pos];
							$state = STATE_AFTER_MINUS;
							break;
						case '(':
							$this->expressionShort .= $this->expression[$this->pos];
							$state = STATE_AFTER_OPEN_BRACE;
							$level++;
							break;
						default:
							if (!$this->parseConstant()) {
								break 3;
							}
							$state = STATE_AFTER_CONSTANT;
					}
					break;
				case STATE_AFTER_MINUS:
					switch ($this->expression[$this->pos]) {
						case ' ':
							$this->expressionShort .= $this->expression[$this->pos];
							break;
						case '(':
							$this->expressionShort .= $this->expression[$this->pos];
							$state = STATE_AFTER_OPEN_BRACE;
							$level++;
							break;
						default:
							if (!$this->parseConstant()) {
								break 3;
							}
							$state = STATE_AFTER_CONSTANT;
					}
					break;
				case STATE_AFTER_CLOSE_BRACE:
				case STATE_AFTER_CONSTANT:
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
							$state = STATE_AFTER_OPERATOR;
							break;
						case ')':
							$this->expressionShort .= $this->expression[$this->pos];
							$state = STATE_AFTER_CLOSE_BRACE;
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
		}

		if ($this->pos == 0) {
			$this->error = _('Incorrect trigger expression.');
			$this->isValid = false;
		}

		if ($level != 0 || isset($this->expression[$this->pos]) || $state == STATE_AFTER_OPERATOR || $state == STATE_AFTER_MINUS) {
			$this->error = _('Incorrect trigger expression.').' '._s('Check expression part starting from "%1$s".',
					zbx_substr($this->expression, $this->pos == 0 ? 0 : $this->pos - 1));
			$this->isValid = false;
		}
	}

	private function parseConstant() {
		if ($this->parseFunctionMacro() || $this->parseNumber() || $this->parseMacro() || $this->parseUserMacro()) {
			return true;
		}

		return false;
	}

	private function parseFunctionMacro() {
		$j = $this->pos;

		$host = '';
		if (!isset($this->expression[$j]) || $this->expression[$j++] != '{' || !$this->parseHost($j, $host)) {
			return false;
		}

		$item = '';
		if (!isset($this->expression[$j]) || $this->expression[$j++] != ':' || !$this->parseItem($j, $item)) {
			return false;
		}

		$function = '';
		$parameters = array();
		if (!isset($this->expression[$j]) || $this->expression[$j++] != '.'
				|| !$this->parseFunction($j, $function, $parameters)) {
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
		$this->expressions[$this->expression_num]['functionParamList'] = $parameters;
		$this->expressionShort .= '{'.$this->expression_num.'}';
		$this->expression_num++;
		$this->pos = $j;
		return true;
	}

	private function parseHost(&$pos, &$host)
	{
		$j = $pos;

		for (; isset($this->expression[$j]) && $this->isHostChar($this->expression[$j]); $j++)
			;

		if ($pos == $j) {	/* is host empty? */
			return false;
		}

		$host = substr($this->expression, $pos, $j - $pos);
		$pos = $j;
		return true;
	}

	private function parseItem(&$pos, &$item)
	{
		$j = $pos;

		for (; isset($this->expression[$j]) && $this->isKeyChar($this->expression[$j]); $j++)
			;

		if (isset($this->expression[$j]) && $this->expression[$j] == '(') { /* for instance, agent.ping.last(0) */
			for (; $j > $pos && $this->expression[$j] != '.'; $j--)
				;
		}
		elseif (isset($this->expression[$j]) && $this->expression[$j] == '[') { /* for instance, net.tcp.port[,80] */
			$level = 0;
			$state = ZBX_STATE_END;

			for (; isset($this->expression[$j]); $j++) {
				if ($level == 0) {
					/* first square bracket + Zapcat compatibility */
					if ($state == ZBX_STATE_END && $this->expression[$j] == '[') {
						$state = ZBX_STATE_NEW;
					}
					else {
						break;
					}
				}

				switch ($state) {
					case ZBX_STATE_NEW: /* a new parameter started */
						switch ($this->expression[$j]) {
							case ' ':
							case ',':
								break;
							case '[':
								$level++;
								break;
							case ']':
								$level--;
								$state = ZBX_STATE_END;
								break;
							case '"':
								$state = ZBX_STATE_QUOTED;
								break;
							default:
								$state = ZBX_STATE_UNQUOTED;
						}
						break;
					case ZBX_STATE_END: /* end of parameter */
						switch ($this->expression[$j]) {
							case ' ':
								break;
							case ',':
								$state = ZBX_STATE_NEW;
								break;
							case ']':
								$level--;
								break;
							default:
								return false;
						}
						break;
					case ZBX_STATE_UNQUOTED: /* an unquoted parameter */
						switch ($this->expression[$j]) {
							case ']':
								$level--;
								$state = ZBX_STATE_END;
								break;
							case ',':
								$state = ZBX_STATE_NEW;
								break;
						}
						break;
					case ZBX_STATE_QUOTED: /* a quoted parameter */
						switch ($this->expression[$j]) {
							case '"':
								if ($this->expression[$j - 1] != '\\') {
									$state = ZBX_STATE_END;
								}
								break;
						}
						break;
				}
			}

			if ($level != 0) {
				return false;
			}
		}

		if ($pos == $j) { /* is key empty? */
			return false;
		}

		$item = substr($this->expression, $pos, $j - $pos);
		$pos = $j;
		return true;
	}

	private function parseFunction(&$pos, &$function, array &$parameters)
	{
		$j = $pos;

		for (; isset($this->expression[$j]) && $this->isFunctionChar($this->expression[$j]); $j++)
			;

		if ($pos == $j) { /* is function empty? */
			return false;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j++] != '(') {
			return false;
		}

		$state = ZBX_STATE_NEW;
		$num = 0;
		$parameters[$num] = '';

		for (; isset($this->expression[$j]); $j++) {
			switch ($state) {
				case ZBX_STATE_NEW: /* a new parameter started */
					switch ($this->expression[$j]) {
						case ' ':
							break;
						case ',':
							$parameters[++$num] = '';
							break;
						case ')':
							break 3; /* end of parameters */
						case '"':
							$state = ZBX_STATE_QUOTED;
							break;
						default:
							$parameters[$num] .= $this->expression[$j];
							$state = ZBX_STATE_UNQUOTED;
					}
					break;
				case ZBX_STATE_END: /* end of parameter */
					switch ($this->expression[$j]) {
						case ' ':
							break;
						case ',':
							$parameters[++$num] = '';
							$state = ZBX_STATE_NEW;
							break;
						case ')':
							break 3; /* end of parameters */
						default:
							return false;
					}
					break;
				case ZBX_STATE_UNQUOTED: /* an unquoted parameter */
					switch ($this->expression[$j]) {
						case ')':
							break 3; /* end of parameters */
						case ',':
							$parameters[++$num] = '';
							$state = ZBX_STATE_NEW;
							break;
						default:
							$parameters[$num] .= $this->expression[$j];
					}
					break;
				case ZBX_STATE_QUOTED: /* a quoted parameter */
					switch ($this->expression[$j]) {
						case '"':
							$state = ZBX_STATE_END;
							break;
						case '\\':
							if (isset($this->expression[$j + 1]) && $this->expression[$j + 1] == '"')
								$j++;
							/* break; is not missing here */
						default:
							$parameters[$num] .= $this->expression[$j];
							break;
					}
					break;
			}
		}

		if (!isset($this->expression[$j]) || $this->expression[$j++] != ')') {
			return false;
		}

		$function = substr($this->expression, $pos, $j - $pos);
		$pos = $j;
		return true;
	}

	private function parseNumber() {
		$j = $this->pos;

		if ($this->expression[$j] < '0' || $this->expression[$j] > '9') {
			return false;
		}

		for ($j++; isset($this->expression[$j]) && $this->expression[$j] >= '0' && $this->expression[$j] <= '9'; $j++)
			;

		if (isset($this->expression[$j]) && $this->expression[$j] == '.') {
			$j++;
			if (!isset($this->expression[$j]) || $this->expression[$j] < '0' || $this->expression[$j] > '9') {
				return false;
			}

			for ($j++; isset($this->expression[$j]) && $this->expression[$j] >= '0' && $this->expression[$j] <= '9'; $j++)
				;
		}

		/* check for an optional suffix */
		if (isset($this->expression[$j]) && false !== strpos('KMGTsmhdw', $this->expression[$j])) {
			$j++;
		}

		$this->expressionShort .= substr($this->expression, $this->pos, $j - $this->pos);
		$this->pos = $j - 1;
		return true;
	}

	private function parseMacro() {
		if (0 != strcmp(substr($this->expression, $this->pos, 15), '{TRIGGER.VALUE}')) {
			return false;
		}

		$macro = substr($this->expression, $this->pos, 15);
		$this->macros[$this->macro_num++]['expression'] = $macro;
		$this->expressionShort .= $macro;
		$this->pos += 14;
		return true;
	}

	private function parseUserMacro() {
		$j = $this->pos;

		if ($this->expression[$j++] != '{') {
			return false;
		}

		if (!isset($this->expression[$j]) || $this->expression[$j++] != '$') {
			return false;
		}

		if (!isset($this->expression[$j]) || !$this->isMacroChar($this->expression[$j])) {
			return false;
		}

		for (; isset($this->expression[$j]) && $this->isMacroChar($this->expression[$j]); $j++)
			;

		if (!isset($this->expression[$j]) || $this->expression[$j] != '}') {
			return false;
		}

		$usermacro = substr($this->expression, $this->pos, $j - $this->pos + 1);
		$this->usermacros[$this->usermacro_num++]['expression'] = $usermacro;
		$this->expressionShort .= $usermacro;
		$this->pos = $j;
		return true;
	}

	private function isHostChar($c) {
		if (($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || ($c >= '0' && $c <= '9')
				|| $c == '.' || $c == ' ' || $c == '_' || $c == '-') {
			return true;
		}

		return false;
	}

	private function isKeyChar($c) {
		if (($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || ($c >= '0' && $c <= '9')
				|| $c == '.' || $c == '_' || $c == '-') {
			return true;
		}

		return false;
	}

	private function isFunctionChar($c) {
		if ($c >= 'a' && $c <= 'z') {
			return true;
		}

		return false;
	}

	private function isMacroChar($c) {
		if (($c >= 'A' && $c <= 'Z') || $c == '.' || $c == '_' || ($c >= '0' && $c <= '9')) {
			return true;
		}

		return false;
	}

	private function initialize() {
		$this->isValid = true;
		$this->error = '';

		$this->pos = 0;

		$this->expressions = array();
		$this->expression_num = 0;

		$this->macros = array();
		$this->macro_num = 0;

		$this->usermacros = array();
		$this->usermacro_num = 0;

		$this->expressionShort = '';
	}
}
