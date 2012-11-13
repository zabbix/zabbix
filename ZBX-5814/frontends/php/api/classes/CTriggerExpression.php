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
	public $errors;
	public $data;
	public $expressions;
	private $symbols;
	private $previous;
	private $currExpr;
	private $newExpr;
	private $allowed;

	public function __construct($trigger) {
		$this->initializeVars();
		$this->checkExpression($trigger['expression']);
	}

	public function checkExpression($expression) {
		$symbolNum = 0;
		try {
			if (zbx_empty(trim($expression))) {
				throw new Exception(_('Empty expression.'));
			}

			// check expr start symbol
			$startSymbol = zbx_substr(trim($expression), 0, 1);
			if ($startSymbol != '(' && $startSymbol != '{' && $startSymbol != '-' && !zbx_ctype_digit($startSymbol)) {
				throw new Exception(_('Incorrect trigger expression.'));
			}

			$length = zbx_strlen($expression);
			for ($symbolNum = 0; $symbolNum < $length; $symbolNum++) {
				$symbol = zbx_substr($expression, $symbolNum, 1);
				$this->parseOpenParts($this->previous['last']);
				$this->parseCloseParts($symbol);

				if ($this->inParameter($symbol)) {
					$this->setPreviousSymbol($symbol);
					continue;
				}
				$this->checkSymbolSequence($symbol);
				$this->setPreviousSymbol($symbol);
			}
			$symbolNum = 0;
			$simpleExpression = $expression;
			$this->checkBraces();
			$this->checkParts($simpleExpression);
			$this->checkSimpleExpression($simpleExpression);
		}
		catch (Exception $e) {
			if ($symbolNum > 0) {
				$symbolNum--;
			}
			else if (str_replace('\n','',$expression)) {
				$symbolNum = strpos($expression, "\n");
			}
			else {
				$symbolNum = 0;
			}
			$symbolNum = ($symbolNum > 0) ? --$symbolNum : $symbolNum;
			$this->errors[] = $e->getMessage();
			$this->errors[] = _s('Check expression part starting from "%1$s".', zbx_substr($expression, $symbolNum));
		}
	}

	public function checkMacro($macro) {
		if (!preg_match('/^'.ZBX_PREG_EXPRESSION_SIMPLE_MACROS.'$/', $macro)) {
			throw new Exception(_s('Incorrect macro "%1$s" is used in expression.', $macro));
		}
	}

	public function checkUserMacro($usermacro) {
		return preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/', $usermacro);
	}

	public function checkHost($host) {
		if (zbx_empty($host)) {
			throw new Exception(_('Empty host name provided in expression.'));
		}

		if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $host)) {
			throw new Exception(_s('Incorrect host name "%1$s" provided in expression.', $host));
		}
	}

	public function checkItem($item) {
		if (zbx_empty($item)) {
			throw new Exception(_s('Empty item key "%1$s" is used in expression.', $item));
		}

		$itemKey = new CItemKey($item);
		if (!$itemKey->isValid()) {
			throw new Exception(_s('Incorrect item key "%1$s" is used in expression.', $item).SPACE.$itemKey->getError());
		}
	}

	public function checkFunction($expression) {
		try {
			if (!isset($this->allowed['functions'][$expression['functionName']])) {
				throw new Exception(_('Unknown function.'));
			}

			if (!preg_match('/^'.ZBX_PREG_FUNCTION_FORMAT.'$/u', $expression['function'])) {
				throw new Exception(_('Incorrect function format.'));
			}

			if (is_null($this->allowed['functions'][$expression['functionName']]['args'])) {
				if (!zbx_empty($expression['functionParamList'][0])) {
					throw new Exception(_('Function does not expect parameters.'));
				}
				else {
					return true;
				}
			}

			if (count($this->allowed['functions'][$expression['functionName']]['args']) < count($expression['functionParamList'])) {
				throw new Exception(_s('Function supports "%1$s" parameters.', count($this->allowed['functions'][$expression['functionName']]['args'])));
			}

			foreach ($this->allowed['functions'][$expression['functionName']]['args'] as $anum => $arg) {
				// mandatory check
				if (isset($arg['mandat']) && $arg['mandat'] && (!isset($expression['functionParamList'][$anum]))) {
					throw new Exception(_('Mandatory parameter is missing.'));
				}

				// type check
				if (isset($arg['type']) && isset($expression['functionParamList'][$anum])) {
					$userMacro = preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/', $expression['functionParamList'][$anum]);

					if ($arg['type'] == 'str' && !is_string($expression['functionParamList'][$anum]) && !$userMacro) {
						throw new Exception(_s('Parameter of type string or user macro expected, "%1$s" given.', $expression['functionParamList'][$anum]));
					}

					if ($arg['type'] == 'sec' && (validate_sec($expression['functionParamList'][$anum]) != 0) && !$userMacro) {
						throw new Exception(_s('Parameter sec or user macro expected, "%1$s" given.', $expression['functionParamList'][$anum]));
					}

					if ($arg['type'] == 'sec_num' && (validate_secnum($expression['functionParamList'][$anum]) != 0) && !$userMacro) {
						throw new Exception(_s('Parameter sec or #num or user macro expected, "%1$s" given.', $expression['functionParamList'][$anum]));
					}

					if ($arg['type'] == 'num' && !is_numeric($expression['functionParamList'][$anum]) && !$userMacro) {
						throw new Exception(_s('Parameter num or user macro expected, "%1$s" given.', $expression['functionParamList'][$anum]));
					}
				}
			}
		}
		catch (Exception $e) {
			throw new Exception(_s('Incorrect trigger function "%1$s" provided in expression.', $expression['function']).SPACE.$e->getMessage());
		}
	}

	// <expression> = <expression> [=#<>|&+-/*] <expression>
	// <expression> = (<expression>)
	// <expression> =  - <space>(0,N) <constant> | <constant>
	// <constant> = <number> | function | macro | user macro
	// <number> = <integer> | <integer><suffix>| <integer>.<integer> | <integer>.<integer><suffix>
	// <suffix> = [KMGTsmhdw]
	public function checkSimpleExpression(&$expression) {
		$expression = preg_replace("/(\d+(\.\d+)?[YZEPKMGTsmhdw]?)/u", '{constant}', $expression);
		$simpleExpr = str_replace(' ', '', $expression);

		// constant => expression
		$start = '';
		while ($start != $simpleExpr) {
			$start = $simpleExpr;
			$simpleExpr = str_replace('({constant})', '{expression}', $simpleExpr);
			$simpleExpr = str_replace('(-{constant})', '{expression}', $simpleExpr);
			$simpleExpr = preg_replace("/([\(\=\#\<\>\|\&\+\-\/\*])\-\{constant\}/u", '$1{expression}', $simpleExpr);
		}
		$simpleExpr = preg_replace('/^\-\{constant\}(.*)$/u', '{constant}$1', $simpleExpr);
		$simpleExpr = str_replace('{constant}', '{expression}', $simpleExpr);

		// expression => expression
		$start = '';
		while ($start != $simpleExpr) {
			$start = $simpleExpr;
			$simpleExpr = str_replace('({expression})', '{expression}', $simpleExpr);
			$simpleExpr = preg_replace("/\{expression\}([\=\#\<\>\|\&\+\-\/\*])\{expression\}/u", '{expression}', $simpleExpr);
		}
		$simpleExpr = str_replace('{expression}', '1', $simpleExpr);
		$this->simpleExpr = $simpleExpr;

		if (strpos($simpleExpr, '()') !== false) {
			throw new Exception(_s('Incorrect trigger expression format "%1$s".', $expression));
		}
		if (strpos($simpleExpr, '11') !== false) {
			throw new Exception(_s('Incorrect trigger expression format "%1$s".', $expression));
		}

		$linkageCount = 0;
		$linkageExpr = '';
		foreach ($this->symbols['linkage'] as $symb => $count) {
			if ($symb == ' ') {
				continue;
			}
			$linkageCount += substr_count($simpleExpr, $symb);
			$linkageExpr .= '\\'.$symb;

			if (strpos($simpleExpr, $symb.')') !== false || strpos($simpleExpr, '('.$symb.'1') !== false) {
				throw new Exception(_s('Incorrect trigger expression format "%1$s".', $expression));
			}
		}

		if (!preg_match('/^([\d'.$linkageExpr.']+)$/ui', $simpleExpr)) {
			throw new Exception(_s('Incorrect trigger expression format "%1$s".', $expression));
		}
		$exprCount = substr_count($simpleExpr, '1');

		if ($linkageCount != $exprCount - 1) {
			throw new Exception(_('Incorrect usage of expression logic linking symbols.'));
		}
	}

	private function checkBraces() {
		if ($this->symbols['params']['"'] % 2 != 0) {
			throw new Exception(_('Incorrect count of quotes in expression.'));
		}

		if ($this->symbols['open']['('] != $this->symbols['close'][')']) {
			throw new Exception(_('Incorrect parenthesis count in expression.'));
		}

		if ($this->symbols['open']['{'] != $this->symbols['close']['}']) {
			throw new Exception(_('Incorrect curly braces count in expression.'));
		}
	}

	private function checkParts(&$expression) {
		foreach ($this->expressions as $enum => $expr) {
			if (!zbx_empty($expr['macro'])) {
				$this->checkMacro($expr['macro']);
				$this->data['macros'][] = $expr['macro'];
			}
			elseif (!zbx_empty($expr['usermacro'])) {
				if (!$this->checkUserMacro($expr['usermacro'])) {
					throw new Exception(_s('Incorrect user macro "%1$s" format is used in expression.', $expr['usermacro']));
				}
				$this->data['usermacros'][] = $expr['usermacro'];
			}
			else {
				$this->checkHost($expr['host']);
				$this->checkItem($expr['item']);
				$this->checkFunction($expr);

				$this->data['hosts'][] = $expr['host'];
				$this->data['items'][] = $expr['item'];
				$this->data['itemParams'][] = $expr['itemParam'];
				$this->data['functions'][] = $expr['functionName'];
				$this->data['functionParams'][] = $expr['functionParam'];

				// ading user macros from item & trigger params
				foreach ($expr['itemParamList'] as $itemParam) {
					if ($this->checkUserMacro($itemParam)) {
						$this->data['usermacros'][] = $itemParam;
					}
				}

				foreach ($expr['functionParamList'] as $funcParam) {
					if ($this->checkUserMacro($funcParam)) {
						$this->data['usermacros'][] = $funcParam;
					}
				}

				$this->expressions[$enum]['itemParam'] = $this->expressions[$enum]['itemParamReal'];
				unset($this->expressions[$enum]['itemParamReal']);

				$this->expressions[$enum]['functionParam'] = $this->expressions[$enum]['functionParamReal'];
				unset($this->expressions[$enum]['functionParamReal']);
			}
			$expression = str_replace_first($expr['expression'], '{constant}', $expression);
		}

		if (empty($this->data['hosts']) || empty($this->data['items'])) {
			throw new Exception(_('Trigger expression must contain at least one host:key reference.'));
		}
	}

	private function isSlashed($pre = false) {
		if ($pre) {
			return $this->previous['prelast'] == '\\';
		}
		else {
			return $this->previous['last'] == '\\';
		}
	}

	private function inQuotes($symbol = '') {
		if ($symbol == '"' || $symbol == '\\') {
			return false;
		}
		return (bool)($this->symbols['params']['"'] % 2);
	}

	private function inParameter($symbol = '') {
		if ($this->inQuotes($symbol)) {
			return true;
		}

		if ($this->currExpr['part']['itemParam']) {
			if ($symbol == '\\' && $this->inQuotes()) {
				return false;
			}
			if (!isset($this->currExpr['params']['item'][$this->currExpr['params']['count']])) {
				return false;
			}
			return true;
		}

		if ($this->currExpr['part']['functionParam']) {
			if ($symbol == '\\' && $this->inQuotes()) {
				return false;
			}
			if (!isset($this->currExpr['params']['function'][$this->currExpr['params']['count']])) {
				return false;
			}
			return true;
		}
		return false;
	}

	private function emptyParameter() {
		if ($this->currExpr['part']['itemParam']) {
			if (!isset($this->currExpr['params']['item'][$this->currExpr['params']['count']])) {
				return true;
			}
			if (zbx_empty($this->currExpr['params']['item'][$this->currExpr['params']['count']])) {
				return true;
			}
		}

		if ($this->currExpr['part']['functionParam']) {
			if (!isset($this->currExpr['params']['function'][$this->currExpr['params']['count']])) {
				return true;
			}
			if (zbx_empty($this->currExpr['params']['function'][$this->currExpr['params']['count']])) {
				return true;
			}
		}
		return false;
	}

	private function parseOpenParts($symbol) {
		if (!$this->inQuotes($symbol)) {
			if (!$this->currExpr['part']['item'] && !$this->currExpr['part']['functionParam']) {
				$this->parseExpression($symbol);
			}
			if (!$this->currExpr['part']['usermacro']
					&& !$this->currExpr['part']['itemParam']
					&& !$this->currExpr['part']['functionParam']) {
				$this->parseItem($symbol);
				$this->parseFunction($symbol);
			}
		}
		if (!$this->currExpr['part']['usermacro']) {
			$this->parseParam($symbol);
		}
	}

	private function parseExpression($symbol) {
		// start expression
		if ($symbol == '{') {
			$this->currExpr = $this->newExpr;
			$this->currExpr['part']['expression'] = true;
			$this->currExpr['part']['host'] = true;
		}

		// start usermacro
		if ($symbol == '$') {
			if ($this->previous['prelast'] == '{') {
				$this->currExpr['part']['usermacro'] = true;
				$this->currExpr['part']['host'] = false;
				$this->currExpr['object']['host'] = '';
				$this->currExpr['object']['usermacro'] = $this->currExpr['object']['expression'];
			}
		}
	}

	private function parseMacro($symbol) {
		if ($symbol == '}' && isset($this->allowed['macros'][$this->currExpr['object']['expression']])) {
			$this->currExpr['object']['macro'] = '{'.$this->currExpr['object']['host'].'}';
			$this->currExpr['object']['host'] = '';
		}
	}

	private function parseItem($symbol) {
		// start item
		if ($symbol == ':') {
			$this->currExpr['part']['host'] = false;
			$this->currExpr['part']['item'] = true;
		}

		if ($symbol == ']') {
			if (!$this->inParameter() && !$this->currExpr['part']['item']) {
				throw new Exception(_('Unexpected Square Bracket symbol in trigger expression.'));
			}
		}

	}

	private function parseFunction($symbol) {
		// start function
		if ($symbol == '(') {
			if (!$this->currExpr['part']['item']) {
				return;
			}
			$this->currExpr['part']['item'] = false;
			$this->currExpr['part']['itemParam'] = false;
			$this->currExpr['part']['function'] = true;

			$lastDot = strrpos($this->currExpr['object']['item'], '.');

			$this->currExpr['object']['functionName'] = substr($this->currExpr['object']['item'], $lastDot+1);
			$this->currExpr['object']['function'] = substr($this->currExpr['object']['item'], $lastDot+1);
			$this->currExpr['object']['item'] = substr($this->currExpr['object']['item'], 0, $lastDot);
		}
	}

	private function parseParam($symbol) {
		if ($symbol == ' ') {
			return;
		}

		// start params
		if ($this->currExpr['part']['itemParam'] || $this->currExpr['part']['functionParam']) {
			if ($this->inParameter() || $symbol == '"') {
				if ($this->inQuotes()) {
					if (($symbol == '"') && !$this->isSlashed(true)) {
						$this->symbols['params'][$symbol]++;
						$this->currExpr['params']['quoteClose'] = true;
					}
					$this->writeParams($symbol);
				}
				else {
					if ($symbol == ']' && $this->currExpr['part']['itemParam']) {
						$this->symbols['params'][$symbol]++;
					}
					elseif ($symbol == ')' && $this->currExpr['part']['functionParam']) {
						$this->symbols['close'][$symbol]++;
						$this->symbols['params'][$symbol]++;
						$this->currExpr['params']['count']++;
					}
					elseif ($symbol == ',') {
						$this->currExpr['params']['count']++;
						$this->currExpr['params']['comma']++;
						$this->currExpr['params']['quoteClose'] = false;
					}
					else {
						if ($symbol == '"' && $this->emptyParameter()) {
							$this->symbols['params'][$symbol]++;
						}
						if ($this->currExpr['params']['quoteClose']) {
							throw new Exception(_('Incorrect quote usage in trigger expression.'));
						}
						$this->writeParams($symbol);
					}
				}
			}
			else {
				if (isset($this->symbols['params'][$symbol]) && !($this->currExpr['part']['itemParam'] && $symbol != ',' && $symbol != ']')) {
					$this->symbols['params'][$symbol]++;
				}

				if ($symbol == ',') {
					$this->writeParams();
					$this->currExpr['params']['count']++;
					$this->currExpr['params']['comma']++;
				}
				elseif ($this->currExpr['params']['count'] > 0) {
					$this->writeParams($symbol);
				}
			}
		}

		if (!$this->inParameter()) {
			if ($this->currExpr['params']['count'] == 0) {
				if ($symbol == '[' && $this->currExpr['part']['item']) {
					$this->symbols['params'][$symbol]++;

					$this->currExpr['part']['itemParam'] = true;
					$this->writeParams();
				}

				if ($symbol == '(' && $this->currExpr['part']['function']) {
					$this->symbols['params'][$symbol]++;

					$this->currExpr['part']['functionParam'] = true;
					$this->writeParams();
				}
			}
		}
	}

	private function parseCloseParts($symbol) {
		if (!$this->inQuotes($symbol)) {
			if (!$this->inParameter()) {
				// close symbols
				$this->parseExpressionClose($symbol);

				if ($symbol == '}') {
					$this->expressions[] = $this->currExpr['object'];
					return ;
				}
			}
			if (!$this->currExpr['part']['usermacro']) {
				$this->parseParamClose($symbol);
			}
		}
		$this->writeParts($symbol);
	}

	private function parseExpressionClose($symbol) {
		// end expression
		if ($symbol == '}') {
			$this->currExpr['part']['expression'] = false;
			$this->currExpr['part']['usermacro'] = false;
			$this->currExpr['part']['host'] = false;
			$this->currExpr['part']['item'] = false;
			$this->currExpr['part']['itemParam'] = false;
			$this->currExpr['part']['function'] = false;
			$this->currExpr['part']['functionParam'] = false;

			$this->currExpr['object']['expression'] = '{'.$this->currExpr['object']['expression'].'}';
			$this->currExpr['object']['host'] = rtrim($this->currExpr['object']['host'], ':');
			$this->currExpr['object']['itemParamList'] = $this->currExpr['params']['item'];
			$this->currExpr['object']['functionName'] = rtrim($this->currExpr['object']['functionName'], '(');
			$this->currExpr['object']['functionParamList'] = $this->currExpr['params']['function'];
		}

		if ($symbol == '}' && isset($this->allowed['macros'][$this->currExpr['object']['expression']])) {
			$this->currExpr['object']['macro'] = '{'.$this->currExpr['object']['host'].'}';
			$this->currExpr['object']['host'] = '';
		}

		if ($symbol == '}' && !zbx_empty($this->currExpr['object']['usermacro'])) {
			$this->currExpr['object']['usermacro'] = '{'.$this->currExpr['object']['usermacro'].'}';
		}

		if ($this->currExpr['part']['function'] && !$this->currExpr['part']['functionParam']) {
			throw new Exception(_s('Unexpected symbol "%1$s" in trigger function.', $symbol));
		}
	}

	private function parseParamClose($symbol) {
		if ($symbol == ' ') {
			return;
		}
		// end params
		if (!$this->inQuotes()) {
			if ($symbol == ']' && $this->currExpr['part']['itemParam']) {
				// +1 because (parseParam is not counted this symbol yet)
				if ($this->symbols['params']['['] == ($this->symbols['params'][']'] + 1)) {
					$this->symbols['params'][$symbol]++;

					$this->writeParams();
					// count points to the last param index
					if ($this->currExpr['params']['count'] != $this->currExpr['params']['comma']) {
						throw new Exception(_('Incorrect item parameters syntax is used.'));
					}

					// do not turn of item part, till function is started
					$this->currExpr['part']['itemParam'] = false;
					$this->currExpr['params']['quoteClose'] = false;
					$this->currExpr['params']['count'] = 0;
					$this->currExpr['params']['comma'] = 0;
				}
			}

			if ($symbol == ')' && $this->currExpr['part']['functionParam']) {
				// +1 because (checkSequence is not counted this symbol yet)
				if ($this->symbols['params']['('] == ($this->symbols['params'][')'] + 1)) {
					$this->symbols['params'][$symbol]++;

					$this->writeParams();
					// count points to the last param index
					if ($this->currExpr['params']['count'] != $this->currExpr['params']['comma']) {
						throw new Exception(_('Incorrect trigger function parameters syntax is used.'));
					}

					// no need to close function part, it will be closed by expression end symbol
					$this->currExpr['part']['functionParam'] = false;
					$this->currExpr['params']['quoteClose'] = false;
					$this->currExpr['params']['count'] = 0;
					$this->currExpr['params']['comma'] = 0;
				}
			}
		}
	}

	// write expression parts
	private function writeParts($symbol) {
		if ($this->currExpr['part']['expression']) {
			$this->currExpr['object']['expression'] .= $symbol;
		}
		if ($this->currExpr['part']['usermacro']) {
			$this->currExpr['object']['usermacro'] .= $symbol;
		}
		if ($this->currExpr['part']['host']) {
			$this->currExpr['object']['host'] .= $symbol;
		}
		if ($this->currExpr['part']['item']) {
			$this->currExpr['object']['item'] .= $symbol;
		}
		if ($this->currExpr['part']['function']) {
			$this->currExpr['object']['function'] .= $symbol;
		}
		if ($this->currExpr['part']['itemParam']) {
			$this->currExpr['object']['itemParamReal'] .= $symbol;
		}
		if ($this->currExpr['part']['functionParam']) {
			$this->currExpr['object']['functionParamReal'] .= $symbol;
		}
		if ($symbol == ' ' && !$this->inParameter()) {
			return;
		}
		if ($this->currExpr['part']['itemParam']) {
			$this->currExpr['object']['itemParam'] .= $symbol;
		}
		if ($this->currExpr['part']['functionParam']) {
			$this->currExpr['object']['functionParam'] .= $symbol;
		}
	}

	private function writeParams($symbol = '') {
		if ($this->currExpr['part']['itemParam']) {
			if (!isset($this->currExpr['params']['item'][$this->currExpr['params']['count']])) {
				$this->currExpr['params']['item'][$this->currExpr['params']['count']] = '';
			}
			$this->currExpr['params']['item'][$this->currExpr['params']['count']] .= $symbol;
		}
		elseif ($this->currExpr['part']['functionParam']) {
			if (!isset($this->currExpr['params']['function'][$this->currExpr['params']['count']])) {
				$this->currExpr['params']['function'][$this->currExpr['params']['count']] = '';
			}
			$this->currExpr['params']['function'][$this->currExpr['params']['count']] .= $symbol;
		}
	}

	private function checkSymbolSequence($symbol) {
		// check close symbols
		if ($symbol == '}' && ($this->symbols['open']['{'] <= $this->symbols['close']['}'])) {
			throw new Exception(_('Incorrect closing curly braces in trigger expression.'));
		}

		if ($symbol == ')' && ($this->symbols['open']['('] <= $this->symbols['close'][')'])) {
			throw new Exception(_('Incorrect closing parenthesis in trigger expression.'));
		}

		// check symbol sequence
		if ($symbol != '-' && isset($this->symbols['linkage'][$symbol]) && isset($this->symbols['linkage'][$this->previous['lastNoSpace']])) {
			throw new Exception(_('Incorrect symbol sequence in trigger expression.'));
		}

		// we shouldn't count open braces in params
		if (!$this->currExpr['part']['itemParam'] && !$this->currExpr['part']['functionParam']) {
			if (isset($this->symbols['open'][$symbol])) $this->symbols['open'][$symbol]++;
		}

		if (isset($this->symbols['close'][$symbol])) {
			$this->symbols['close'][$symbol]++;
		}
		if (isset($this->symbols['expr'][$symbol])) {
			$this->symbols['expr'][$symbol]++;
		}
		if (isset($this->symbols['linkage'][$symbol])) {
			$this->symbols['linkage'][$symbol]++;
		}
	}

	private function setPreviousSymbol($symbol) {
		$this->previous['prelast'] = $this->previous['last'];

		if ($this->previous['last'] == $symbol) {
			$this->previous['sequence'] = $this->symbols['sequence'];
			$this->symbols['sequence']++;
		}
		else {
			$this->previous['sequence'] = $this->symbols['sequence'];
			$this->symbols['sequence'] = 1;

			$this->previous['last'] = $symbol;
		}

		if ($symbol != ' ') {
			$this->previous['preLastNoSpace'] = $this->previous['lastNoSpace'];
			$this->previous['lastNoSpace'] = $symbol;
		}
	}

	private function initializeVars() {
		$this->allowed = init_trigger_expression_structures();
		$this->errors = array();
		$this->expressions = array();
		$this->data = array(
			'hosts' => array(),
			'usermacros' => array(),
			'macros' => array(),
			'items' => array(),
			'itemParams' => array(),
			'functions' => array(),
			'functionParams' => array()
		);

		$this->newExpr = array(
			'part' => array(
				'expression' => false,
				'usermacro' => false,
				'host' => false,
				'item' => false,
				'itemParam' => false,
				'function' => false,
				'functionParam' => false
			),
			'object' => array(
				'expression' => '',
				'macro' => '',
				'usermacro' => '',
				'host' => '',
				'item' => '',
				'itemParam' => '',
				'itemParamReal' => '',
				'itemParamList' => '',
				'function' => '',
				'functionName' => '',
				'functionParam' => '',
				'functionParamReal' => '',
				'functionParamList' => ''
			),
			'params' => array(
				'quoteClose' => false,
				'comma' => 0,
				'count' => 0,
				'item' => array(),
				'function' => array()
			)
		);
		$this->currExpr = $this->newExpr;

		$this->symbols = array(
			'sequence' => 0,
			'open' => array(
				'(' => 0,	// parenthesis
				'{' => 0	// curly brace
			),
			'close' => array(
				')' => 0,	// parenthesis
				'}' => 0	// curly brace
			),
			'linkage' => array(
				'+' => 0,	// addition
				'-' => 0,	// subtraction
				'*' => 0,	// multiplication
				'/' => 0,	// division
				'#' => 0,	// not equals
				'=' => 0,	// equals
				'<' => 0,	// less than
				'>' => 0,	// greater than
				'&' => 0,	// logical and
				'|' => 0	// logical or
			),
			'expr' => array(
				'$' => 0,	// dollar
				'\\' => 0,	// backslash
				':' => 0,	// colon
				'.' => 0	// dot
			),
			'params' => array(
				'"' => 0,	// quote
				'[' => 0,	// open square brace
				']' => 0,	// close square brace
				'(' => 0,	// open brace
				')' => 0	// close brace
			)
		);

		$this->previous = array(
			'sequence' => '',
			'last' => '',
			'prelast' => '',
			'lastNoSpace' => '',
			'preLastNoSpace' => ''
		);
	}
}
