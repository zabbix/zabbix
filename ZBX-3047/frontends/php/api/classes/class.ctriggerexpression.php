<?php
class CTriggerExpression{
public $errors;
public $data;
public $expressions;

private $symbol;
private $previous;
private $currExpr;
private $newExpr;

private $allowedFunctions;

	public function __construct($trigger){
		$this->initializeVars();
		$this->parseExpression($trigger['expression']);
	}

	public function parseExpression($expression){
		$length = zbx_strlen($expression);
		$symbolNum = 0;

		try{
			if(zbx_empty($expression))
				throw new Exception('Empty expression.');

			if(!$this->startExpression($expression))
				throw new Exception('Incorrect trigger expression.');

			for($symbolNum = 0; $symbolNum < $length; $symbolNum++){
				$symbol = zbx_substr($expression, $symbolNum, 1);

				$this->setExpressionParts($symbol);

				if($this->inQuotes($symbol)) continue;

				$this->checkSymbolPrevious($symbol);
				$this->checkSymbolClose($symbol);
				$this->checkSymbolSequence($symbol);

// set sequence of symbols
				$this->setPreviousSymbol($symbol);
			}

			$symbolNum = 0;
			$this->checkOverallExpression($expression);
		}
		catch(Exception $e){
			$symbolNum = ($symbolNum > 0) ? --$symbolNum : $symbolNum;

			$error = $e->getMessage();
			$this->errors[] = $error;
			$this->errors[] = 'Check expression part starting from " '.zbx_substr($expression, $symbolNum).' "';
		}
	}

// PRIVATE --------------------------------------------------------------------------------------------
	private function startExpression($expression){
		$startSymbol = zbx_substr($expression, 0, 1);
		return (($startSymbol == '(') || ($startSymbol == '{') || zbx_ctype_digit($startSymbol));
	}

	private function inQuotes($symbol){
		if(($symbol == '"') || ($symbol == '\\')) return false;

		return (bool)($this->symbols['expr']['"'] % 2);
	}

	private function checkSymbolClose($symbol){
		if(!isset($this->symbols['close'][$symbol])) return true;

		switch($symbol){
			case '}':
				if($this->symbols['open']['{'] <= $this->symbols['close']['}'])
					throw new Exception('Incorrect closing curly braces in trigger expression');
				break;
			case ')':
				if($this->symbols['open']['('] <= $this->symbols['close'][')'])
					throw new Exception('Incorrect closing parenthesis in trigger expression');
				break;
			default:
				return true;
		}
	}

	private function checkSymbolPrevious($symbol){
		if(!isset($this->symbols['linkage'][$symbol])) return true;

		if(isset($this->symbols['linkage'][$symbol]) && ($this->previous['last'] == $symbol)){
			throw new Exception('Incorrect symbol sequence in trigger expression');
		}
	}

	private function checkSymbolSequence($symbol){
		if(isset($this->symbols['open'][$symbol])) $this->symbols['open'][$symbol]++;
		if(isset($this->symbols['close'][$symbol])) $this->symbols['close'][$symbol]++;
		if(isset($this->symbols['linkage'][$symbol])) $this->symbols['linkage'][$symbol]++;
		if(isset($this->symbols['expr'][$symbol])) $this->symbols['expr'][$symbol]++;

		if($this->currExpr['part']['expression']){
			if($symbol == '"'){
				if(($this->previous['last'] == '\\') && ($this->symbols['sequence'] % 2 == 1)){
					$this->symbols['expr'][$symbol]--;
				}
			}

			if(isset($this->symbols['linkage'][$symbol]) && $this->currExpr['object']['functionParam']){
				$this->symbols['linkage'][$symbol]--;
			}
		}
		else{
/*
			if(isset($this->symbols['open'][$symbol])) return true;
			if(isset($this->symbols['close'][$symbol])) return true;
			if(isset($this->symbols['linkage'][$symbol])) return true;

			if(!isset($this->symbols['linkage'][$this->previous['last']])){
				if(!zbx_empty($this->previous['last']) && !zbx_ctype_digit($this->previous['last']))
					throw new Exception('Unexpected symbols " '.$symbol.' " in trigger expresion');
			}
//*/
		}
	}

	private function checkOverallExpression($expression){
		$simpleExpression = $expression;

		$this->checkExpressionBrackets($simpleExpression);
		$this->checkExpressionParts($simpleExpression);
		$this->checkSimpleExpression($simpleExpression);
	}

	private function checkExpressionBrackets(&$expression){
		if($this->symbols['open']['('] != $this->symbols['close'][')'])
			throw new Exception('Incorrect parenthesis count in expression');

		if($this->symbols['open']['{'] != $this->symbols['close']['}'])
			throw new Exception('Incorrect curly braces count in expression');

		if($this->symbols['expr']['"'] % 2)
			throw new Exception('Incorrect count of quotes in expression');
	}

	private function checkExpressionParts(&$expression){
		foreach($this->expressions as $enum => $expr){
			if($expr['expression']){}

			if(zbx_empty($expr['host'])){
				if(!preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/i', $expr['usermacro']))
					throw new Exception('Incorrect user macro format is used in expression');

				$this->data['usermacros'][] = $expr['usermacro'];
			}
			else{
				if(!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/i', $expr['host']))
					throw new Exception('Incorrect host name is used in expression');

				if(!check_item_key($expr['key']))
					throw new Exception('Incorrect item key is used in expression');

				$expr['functionName'] = strtolower($expr['functionName']);
				if(!isset($this->allowedFunctions[$expr['functionName']]))
					throw new Exception('Unknown trigger function is used in expression');

				if(!preg_match('/^'.ZBX_PREG_FUNCTION_FORMAT.'$/i', $expr['function']))
					throw new Exception('Incorrect trigger function format is used in expression');

				$this->checkFunctionParams($expr);


				$this->data['hosts'][] = $expr['host'];
				$this->data['items'][] = $expr['key'];
				$this->data['functions'][] = $expr['functionName'];
				$this->data['functionParams'][] = $expr['functionParam'];
			}

			$expression = str_replace($expr['expression'], '{expression}', $expression);
		}

		if(empty($this->data['hosts']) || empty($this->data['items']))
			throw new Exception('Trigger expression must contain at least one host:key reference');
	}

	private function checkFunctionParams($expr){
		$paramCount = 0;
		$inQuotes = false;
		$prevSymbol = '';
		$params = array();

		$length = zbx_strlen($expr['functionParam']);
		for($symbolNum = 0; $symbolNum < $length; $symbolNum++){
			$symbol = zbx_substr($expr['functionParam'], $symbolNum, 1);

			if(($symbol == '"') && ($prevSymbol != '\\')){
				$inQuotes = !$inQuotes;

				if($inQuotes && isset($params[$paramCount]))
					throw new Exception('Incorrect trigger function parameter syntax is used in "'.$expr['function'].'"');

				continue;
			}

			if(!$inQuotes){
				if(($symbol == ',') && ($prevSymbol != '\\')){
					$paramCount++;
					continue;
				}
				else if(($symbol == '\\') && ($symbol == $prevSymbol)){
					$prevSymbol = '';
					continue;
				}
			}

			if(!isset($params[$paramCount])) $params[$paramCount] = '';
			$params[$paramCount] .= $symbol;
			$prevSymbol = $symbol;
		}


		$functionParam = $params;
		if(!is_null($this->allowedFunctions[$expr['functionName']]['args'])){
			foreach($this->allowedFunctions[$expr['functionName']]['args'] as $anum => $arg){
// mandatory check
				if(isset($arg['mandat']) && $arg['mandat'] && !isset($functionParam[$anum]))
					throw new Exception('Incorrect number of agruments passed to function "'.$expr['function'].'"');
// type check
				if(isset($arg['type']) && isset($functionParam[$anum])){
					$typeFlag = true;
					if($arg['type'] == 'str') $typeFlag = is_string($functionParam[$anum]);
					if($arg['type'] == 'sec') $typeFlag = (validate_float($functionParam[$anum]) == 0);
					if($arg['type'] == 'sec_num') $typeFlag = (validate_ticks($functionParam[$anum]) == 0);
					if($arg['type'] == 'num') $typeFlag = is_numeric($functionParam[$anum]);

					if(!$typeFlag)
						throw new Exception('Incorrect type of agruments passed to function "'.$expr['function'].'"');
				}
			}
		}
	}

	private function checkSimpleExpression(&$expression){
		$expression = preg_replace('/(\d\.\d)/', '{expression}', $expression);
		$expression = preg_replace("/([0-9]+)/u", '{expression}', $expression);
//SDI($expression);

		$simpleExpr = str_replace('{expression}','1',$expression);
//SDI($simpleExpr);

		$linkageCount = 0;
		$linkageExpr = '';
		foreach($this->symbols['linkage'] as $symb => $count){
			if($symb == ' ') continue;

			$linkageCount += $count;
			$linkageExpr .= '\\'.$symb;
		}

		if(!preg_match('/^([\(\)\d\s'.$linkageExpr.']+)$/i', $simpleExpr))
			throw new Exception('Incorrect trigger expression format " '.$expression.' "');

		$exprCount = substr_count($expression, '{expression}');

		if($linkageCount != $exprCount-1)
			throw new Exception('Incorrect usage of expression logic linking symbols');
	}

	private function setPreviousSymbol($symbol){
		if($this->previous['last'] == $symbol){
			$this->symbols['sequence']++;
		}
		else{
			$this->symbols['sequence'] = 1;

			if($symbol != ' ')
				$this->previous['last'] = $symbol;
		}

		if(isset($this->symbols['open'][$symbol])){
			$this->previous['open'] = $symbol;
		}

		if(isset($this->symbols['close'][$symbol])){
			$this->previous['close'] = $symbol;
		}

		if(isset($this->symbols['linkage'][$symbol])){
			$this->previous['linkage'] = $symbol;
		}

		if(isset($this->symbols['expr'][$symbol])){
			$this->previous['expr'] = $symbol;
		}
	}

	private function setExpressionParts($symbol){
		if(!$this->inQuotes($symbol)){
			if(!$this->currExpr['part']['key']){

// new expression
				if($symbol == '{')
					$this->currExpr = $this->newExpr;

// open symbols
				switch($symbol){
					case '{':
						$this->currExpr['part']['expression'] = true;
						$this->currExpr['part']['host'] = true;
						break;
					case '$':
						if($this->previous['last'] == '{'){
							$this->currExpr['part']['usermacro'] = true;
							$this->currExpr['part']['host'] = false;

							$this->currExpr['object']['usermacro'] = $this->currExpr['object']['expression'];
						}
				}
			}

			if(!$this->currExpr['part']['usermacro']){
				switch($symbol){
					case ':':
						$this->currExpr['part']['host'] = false;
						$this->currExpr['part']['key'] = true;
						break;
					case '(':
						if($this->currExpr['part']['key']){
							$this->currExpr['part']['key'] = false;
							$this->currExpr['part']['function'] = true;
							$this->currExpr['part']['functionParam'] = true;


							$lastDot = strrpos($this->currExpr['object']['key'], '.');

							$this->currExpr['object']['functionName'] = substr($this->currExpr['object']['key'],$lastDot+1);
							$this->currExpr['object']['function'] = substr($this->currExpr['object']['key'],$lastDot+1);
							$this->currExpr['object']['key'] = substr($this->currExpr['object']['key'],0,$lastDot);
						}
						break;
					case ')':
						if($this->currExpr['part']['function']){
							$this->currExpr['part']['functionParam'] = false;
						}
				}
			}
		}

		if($this->currExpr['part']['expression'])
			$this->currExpr['object']['expression'] .= $symbol;

		if($this->currExpr['part']['usermacro'])
			$this->currExpr['object']['usermacro'] .= $symbol;

		if($this->currExpr['part']['host'])
			$this->currExpr['object']['host'] .= $symbol;

		if($this->currExpr['part']['key'])
			$this->currExpr['object']['key'] .= $symbol;

		if($this->currExpr['part']['function'])
			$this->currExpr['object']['function'] .= $symbol;

		if($this->currExpr['part']['functionParam'])
			$this->currExpr['object']['functionParam'] .= $symbol;

		if(!$this->inQuotes($symbol) && !$this->currExpr['part']['key']){
// close symbols
			switch($symbol){
				case '}':
					$this->currExpr['part']['usermacro'] = false;
					$this->currExpr['part']['expression'] = false;
					$this->currExpr['part']['host'] = false;
					$this->currExpr['part']['key'] = false;
					$this->currExpr['part']['function'] = false;
					break;
			}

			if($symbol == '}'){
				$this->currExpr['object']['host'] = substr($this->currExpr['object']['host'], 1);
				$this->currExpr['object']['key'] = substr($this->currExpr['object']['key'], 1);
				$this->currExpr['object']['function'] = rtrim($this->currExpr['object']['function'], '}');
				$this->currExpr['object']['functionParam'] = substr($this->currExpr['object']['functionParam'], 1);

				$this->expressions[] = $this->currExpr['object'];
				return true;
			}
		}
	}

	private function initializeVars(){
		$this->allowedFunctions = INIT_TRIGGER_EXPRESSION_STRUCTURES();

		$this->errors = array();
		$this->expressions = array();
		$this->data = array('hosts'=>array(),'usermacros'=>array(),'items'=>array(),'functions'=>array());

		$this->newExpr = array(
			'part' => array(
				'expression' => false,
				'usermacro' => false,
				'host' => false,
				'key' => false,
				'function' => false,
				'functionParam' => false,
			),
			'object' => array(
				'expression' => '',
				'usermacro' => '',
				'host' => '',
				'key' => '',
				'function' => '',
				'functionName' => '',
				'functionParam' => ''
			)
		);
		$this->currExpr = $this->newExpr;

		$this->symbols = array(
			'sequence' => 0,
			'open' => array(
				'(' => 0,		// parenthesis
				'{' => 0		// curlyBrace
			),
			'close' => array(
				')' => 0,		// parenthesis
				'}' => 0,		// curlyBrace
			),
			'linkage' => array(
				'+' => 0,		// plus
				'-' => 0,		// minus
				'*' => 0,		// multiplication
				'/' => 0,		// division
				'#' => 0,		// hash
				'=' => 0,		// equals
				'<' => 0,		// lower
				'>' => 0,		// greater
				'&' => 0,		// ampersand
				'|' => 0,		// vertivalBar
				' ' => 0,
			),
			'expr' => array(
				'$' => 0,		// dollar
				'\\' => 0,		// backslash
				'"' => 0,		// quote
				':' => 0,		// colon
				'.' => 0,		// dot
			)
		);

		$this->previous = array(
			'last' => '',
			'open' => '',
			'close' => '',
			'linkage' => '',
			'expr'=> '',
			'part' => ''
		);
	}
}
?>
