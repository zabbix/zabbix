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


/**
 *  A parser for function macros like "{host.item.func()}".
 */
class CFunctionMacroParser extends CParser {

	const STATE_NEW = 0;
	const STATE_END = 1;
	const STATE_UNQUOTED = 2;
	const STATE_QUOTED = 3;

	/**
	 * The string being parsed
	 *
	 * @var string
	 */
	protected $source;

	/**
	 * @param string    $source
	 * @param int       $startPos
	 *
	 * @return bool|CFunctionMacroParserResult
	 */
	public function parse($source, $startPos = 0) {
		$this->source = $source;
		$this->pos = $startPos;

		if (!isset($this->source[$this->pos]) || $this->source[$this->pos++] != '{'
				|| ($host = $this->parseHost()) === null) {

			return false;
		}

		if (!isset($this->source[$this->pos]) || $this->source[$this->pos++] != ':'
				|| ($item = $this->parseItem()) === null) {

			$this->pos--;
			return false;
		}

		if (!isset($this->source[$this->pos]) || $this->source[$this->pos++] != '.'
				|| !(list($function, $functionParamList) = $this->parseFunction())) {

			$this->pos--;
			return false;
		}

		if (!isset($this->source[$this->pos]) || $this->source[$this->pos] != '}') {
			return false;
		}

		$expressionLength = $this->pos - $startPos + 1;
		$expression = substr($this->source, $startPos, $expressionLength);
		$functionName = substr($function, 0, strpos($function, '('));

		$result = new CFunctionMacroParserResult();
		$result->source = $this->source;
		$result->match = $expression;
		$result->pos = $startPos;
		$result->length = $expressionLength;

		$result->expression = array(
			'expression' => $expression,
			'pos' => $startPos,
			'host' => $host,
			'item' => $item,
			'function' => $function,
			'functionName' => $functionName,
			'functionParam' => substr($function, strpos($function, '(') + 1, -1),
			'functionParamList' => $functionParamList
		);

		return $result;
	}

	/**
	 * Parses a host in a trigger function macro constant and moves a position ($pos) on a next symbol after the host
	 *
	 * @return string returns a host name if parsed successfully or null otherwise
	 */
	protected function parseHost() {
		$startPos = $this->pos;

		while (isset($this->source[$this->pos]) && $this->isHostChar($this->source[$this->pos])) {
			$this->pos++;
		}

		// is host empty?
		if ($this->pos == $startPos) {
			return null;
		}

		$host = substr($this->source, $startPos, $this->pos - $startPos);

		return $host;
	}

	/**
	 * Parses an item in a trigger function macro constant and moves a position ($pos) on a next symbol after the item
	 *
	 * @return string returns an item name if parsed successfully or null otherwise
	 */
	protected function parseItem() {
		$startPos = $this->pos;

		while (isset($this->source[$this->pos]) && $this->isKeyChar($this->source[$this->pos])) {
			$this->pos++;
		}

		// for instance, agent.ping.last(0)
		if (isset($this->source[$this->pos]) && $this->source[$this->pos] == '(') {
			while ($this->pos > $startPos && $this->source[$this->pos] != '.') {
				$this->pos--;
			}
		}
		// for instance, net.tcp.port[,80]
		elseif (isset($this->source[$this->pos]) && $this->source[$this->pos] == '[') {
			$level = 0;
			$state = self::STATE_END;

			while (isset($this->source[$this->pos])) {
				if ($level == 0) {
					// first square bracket + Zapcat compatibility
					if ($state == self::STATE_END && $this->source[$this->pos] == '[') {
						$state = self::STATE_NEW;
					}
					else {
						break;
					}
				}

				switch ($state) {
					// a new parameter started
					case self::STATE_NEW:
						switch ($this->source[$this->pos]) {
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
						switch ($this->source[$this->pos]) {
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
						switch ($this->source[$this->pos]) {
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
						switch ($this->source[$this->pos]) {
							case '"':
								if ($this->source[$this->pos - 1] != '\\') {
									$state = self::STATE_END;
								}
								break;
						}
						break;
				}
				$this->pos++;
			}

			if ($level != 0) {
				return null;
			}
		}

		// is key empty?
		if ($startPos == $this->pos) {
			return null;
		}

		$item = substr($this->source, $startPos, $this->pos - $startPos);

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
	protected function parseFunction()
	{
		$startPos = $this->pos;

		while (isset($this->source[$this->pos]) && $this->isFunctionChar($this->source[$this->pos])) {
			$this->pos++;
		}

		// is function empty?
		if ($startPos == $this->pos) {
			return null;
		}

		if (!isset($this->source[$this->pos]) || $this->source[$this->pos++] != '(') {
			return null;
		}

		$state = self::STATE_NEW;
		$num = 0;
		$functionParamList = array();
		$functionParamList[$num] = '';

		while (isset($this->source[$this->pos])) {
			switch ($state) {
				// a new parameter started
				case self::STATE_NEW:
					switch ($this->source[$this->pos]) {
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
							$functionParamList[$num] .= $this->source[$this->pos];
							$state = self::STATE_UNQUOTED;
					}
					break;
				// end of parameter
				case self::STATE_END:
					switch ($this->source[$this->pos]) {
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
					switch ($this->source[$this->pos]) {
						case ')':
							// end of parameters
							break 3;
						case ',':
							$functionParamList[++$num] = '';
							$state = self::STATE_NEW;
							break;
						default:
							$functionParamList[$num] .= $this->source[$this->pos];
					}
					break;
				// a quoted parameter
				case self::STATE_QUOTED:
					switch ($this->source[$this->pos]) {
						case '"':
							$state = self::STATE_END;
							break;
						case '\\':
							if (isset($this->source[$this->pos + 1]) && $this->source[$this->pos + 1] == '"') {
								$this->pos++;
							}
						// break; is not missing here
						default:
							$functionParamList[$num] .= $this->source[$this->pos];
							break;
					}
					break;
			}
			$this->pos++;
		}

		if (!isset($this->source[$this->pos]) || $this->source[$this->pos++] != ')') {
			return null;
		}

		$function = substr($this->source, $startPos, $this->pos - $startPos);

		return array($function, $functionParamList);
	}

	/**
	 * Returns true if the char is allowed in the host name, false otherwise.
	 *
	 * @param string $c
	 *
	 * @return bool
	 */
	protected function isHostChar($c) {
		if (($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || ($c >= '0' && $c <= '9')
			|| $c == '.' || $c == ' ' || $c == '_' || $c == '-') {
			return true;
		}

		return false;
	}

	/**
	 * Returns true if the char is allowed in the item key, false otherwise.
	 *
	 * @param string $c
	 *
	 * @return bool
	 */
	protected function isKeyChar($c) {
		if (($c >= 'a' && $c <= 'z') || ($c >= 'A' && $c <= 'Z') || ($c >= '0' && $c <= '9')
			|| $c == '.' || $c == '_' || $c == '-') {
			return true;
		}

		return false;
	}

	/**
	 * Returns true if the char is allowed in the function name, false otherwise.
	 *
	 * @param string $c
	 *
	 * @return bool
	 */
	protected function isFunctionChar($c) {
		if ($c >= 'a' && $c <= 'z') {
			return true;
		}

		return false;
	}
}
