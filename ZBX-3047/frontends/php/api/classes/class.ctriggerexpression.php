<?php
class CTriggerExpression{
public $errors;
public $data;
public $expressions;

private $symbols;
private $previous;
private $currExpr;
private $newExpr;

private $allowed;

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
// SDI($symbol);

				$this->detectOpenParts($this->previous['last']);
				$this->detectCloseParts($symbol);
// SDII($this->currExpr);
				if($this->inParameter($symbol)){
					$this->setPreviousSymbol($symbol);
					continue;
				}

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

	private function isSlashed($pre=false){
		if($pre)
			return (($this->previous['prelast'] == '\\') && ($this->previous['sequence'] % 2 == 1));
		else
			return (($this->previous['last'] == '\\') && ($this->symbols['sequence'] % 2 == 1));
	}

	private function inQuotes($symbol=''){
		if(($symbol == '"') || ($symbol == '\\')) return false;

		return (bool)($this->symbols['params']['"'] % 2);
	}

	private function inParameter($symbol=''){
		if($this->inQuotes($symbol)) return true;

		if($this->currExpr['part']['itemParam']){
			if(($symbol == '\\') && $this->inQuotes()) return false;
			if(!isset($this->currExpr['params']['item'][$this->currExpr['params']['count']])) return false;
			return true;
		}

		if($this->currExpr['part']['functionParam']){
			if(($symbol == '\\') && $this->inQuotes()) return false;
			if(!isset($this->currExpr['params']['function'][$this->currExpr['params']['count']])) return false;
			return true;
		}

	return false;
	}

	private function emptyParameter(){
		if($this->currExpr['part']['itemParam']){
			if(!isset($this->currExpr['params']['item'][$this->currExpr['params']['count']])) return true;
			if(zbx_empty($this->currExpr['params']['item'][$this->currExpr['params']['count']])) return true;
		}

		if($this->currExpr['part']['functionParam']){
			if(!isset($this->currExpr['params']['function'][$this->currExpr['params']['count']])) return true;
			if(zbx_empty($this->currExpr['params']['function'][$this->currExpr['params']['count']])) return true;
		}

	return false;
	}

	private function checkSymbolClose($symbol){
		if(!isset($this->symbols['close'][$symbol])) return true;

		switch($symbol){
			case '}':
				if($this->symbols['open']['{'] <= $this->symbols['close']['}'])
					throw new Exception('Incorrect closing curly braces in trigger expression.');
				break;
			case ')':
				if($this->symbols['open']['('] <= $this->symbols['close'][')'])
					throw new Exception('Incorrect closing parenthesis in trigger expression.');
				break;
			default:
				return true;
		}
	}

	private function checkSymbolPrevious($symbol){
		if(!isset($this->symbols['linkage'][$symbol])) return;

		if(isset($this->symbols['linkage'][$symbol]) &&
			isset($this->symbols['linkage'][$this->previous['lastNoSpace']]))
		{
			throw new Exception('Incorrect symbol sequence in trigger expression.');
		}
	}

	private function checkSymbolSequence($symbol){
// watch for closing brakets
		if(isset($this->symbols['close'][$symbol])) $this->symbols['close'][$symbol]++;
		if(isset($this->symbols['open'][$symbol])) $this->symbols['open'][$symbol]++;
		if(isset($this->symbols['expr'][$symbol])) $this->symbols['expr'][$symbol]++;
		if(isset($this->symbols['linkage'][$symbol])) $this->symbols['linkage'][$symbol]++;

		if($this->currExpr['part']['expression']){
			if(($symbol == '"') && !$this->currExpr['part']['itemParam'] && !$this->currExpr['part']['functionParam']){
				throw new Exception('Incorrect symbol sequence in trigger expression');
			}
		}
	}

	private function checkOverallExpression($expression){
		$simpleExpression = $expression;

		$this->checkExpressionBrackets($simpleExpression);
		$this->checkExpressionParts($simpleExpression);
		$this->checkSimpleExpression($simpleExpression);
//SDII($this->data);
//SDII($this->expressions);
	}

	private function checkExpressionBrackets(&$expression){
		if($this->symbols['params']['"'] % 2 != 0)
			throw new Exception('Incorrect count of quotes in expression');

		if($this->symbols['open']['('] != $this->symbols['close'][')']){
			throw new Exception('Incorrect parenthesis count in expression');
		}

		if($this->symbols['open']['{'] != $this->symbols['close']['}'])
			throw new Exception('Incorrect curly braces count in expression');
	}

	private function checkExpressionParts(&$expression){
		foreach($this->expressions as $enum => $expr){
			if($expr['expression']){}


			if(!zbx_empty($expr['macro'])){
				if(!preg_match('/^'.ZBX_PREG_EXPRESSION_SIMPLE_MACROS.'$/i', $expr['macro']))
					throw new Exception('Incorrect macro is used in expression');

				$this->data['macros'][] = $expr['macro'];
			}
			else if(!zbx_empty($expr['usermacro'])){
				if(!preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/i', $expr['usermacro']))
					throw new Exception('Incorrect user macro format is used in expression');

				$this->data['usermacros'][] = $expr['usermacro'];
			}
			else{
				if(zbx_empty($expr['host'])) throw new Exception('Incorrect host name provided in expression');
				if(zbx_empty($expr['item'])) throw new Exception('Incorrect item key provided in expression');
// host
				if(!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/i', $expr['host']))
					throw new Exception('Incorrect host name is used in expression');
// item
				$checkResult = check_item_key($expr['item']);
				if(!$checkResult['valid'])
					throw new Exception('Incorrect item key "'.$expr['item'].'" is used in expression, '.$checkResult['description']);
// function
				$expr['functionName'] = strtolower($expr['functionName']);
				if(!isset($this->allowed['functions'][$expr['functionName']]))
					throw new Exception('Unknown trigger function is used in expression "'.$expr['functionName'].'"');

				if(!preg_match('/^'.ZBX_PREG_FUNCTION_FORMAT.'$/i', $expr['function']))
					throw new Exception('Incorrect trigger function format is used in expression');

				$this->checkFunctionParams($expr);


				$this->data['hosts'][] = $expr['host'];
				$this->data['items'][] = $expr['item'];
				$this->data['functions'][] = $expr['functionName'];
				$this->data['functionParams'][] = $expr['functionParam'];
			}

			$expression = str_replace($expr['expression'], '{expression}', $expression);
		}

		if(empty($this->data['hosts']) || empty($this->data['items']))
			throw new Exception('Trigger expression must contain at least one host:key reference');
	}

	private function checkFunctionParams($expr){
		if(is_null($this->allowed['functions'][$expr['functionName']]['args'])) return;

		foreach($this->allowed['functions'][$expr['functionName']]['args'] as $anum => $arg){
// mandatory check
			if(isset($arg['mandat']) && $arg['mandat'] && !isset($expr['functionParamList'][$anum]))
				throw new Exception('Incorrect number of agruments passed to function "'.$expr['functionParamList'].'"');
// type check
			if(isset($arg['type']) && isset($expr['functionParamList'][$anum])){
				$typeFlag = true;
				if($arg['type'] == 'str') $typeFlag = is_string($expr['functionParamList'][$anum]);
				if($arg['type'] == 'sec') $typeFlag = (validate_float($expr['functionParamList'][$anum]) == 0);
				if($arg['type'] == 'sec_num') $typeFlag = (validate_ticks($expr['functionParamList'][$anum]) == 0);
				if($arg['type'] == 'num') $typeFlag = is_numeric($expr['functionParamList'][$anum]);

				if(!$typeFlag)
					throw new Exception('Incorrect type of agruments passed to function "'.$expr['function'].'"');
			}
		}
	}

	private function checkSimpleExpression(&$expression){
		$expression = preg_replace("/(\d+(\.\d+)?[KMGTsmhdw]?)/u", '{expression}', $expression);
		$expression = preg_replace("/(\(\-?\{expression\}\))/u", '{expression}', $expression);
// SDI($expression);
		$simpleExpr = str_replace(' ','',$expression);
		$simpleExpr = str_replace('{expression}','1',$simpleExpr);
// SDI($simpleExpr);

		if(strpos($simpleExpr,'11') !== false)
			throw new Exception('Incorrect trigger expression format " '.$expression.' "');

		$linkageCount = 0;
		$linkageExpr = '';
		foreach($this->symbols['linkage'] as $symb => $count){
			if($symb == ' ') continue;

//			$linkageCount += $count;
			$linkageCount += substr_count($expression, $symb);
			$linkageExpr .= '\\'.$symb;
		}

		if(!preg_match('/^([\(\)\d\s'.$linkageExpr.']+)$/i', $simpleExpr))
			throw new Exception('Incorrect trigger expression format " '.$expression.' "');

		$exprCount = substr_count($expression, '{expression}');

		if($linkageCount != $exprCount-1)
			throw new Exception('Incorrect usage of expression logic linking symbols');
	}

	private function setPreviousSymbol($symbol){
		$this->previous['prelast'] = $this->previous['last'];

		if($this->previous['last'] == $symbol){
			$this->previous['sequence'] = $this->symbols['sequence'];
			$this->symbols['sequence']++;
		}
		else{
			$this->previous['sequence'] = $this->symbols['sequence'];
			$this->symbols['sequence'] = 1;

			$this->previous['last'] = $symbol;
		}

		if($symbol != ' '){
			$this->previous['preLastNoSpace'] = $this->previous['lastNoSpace'];
			$this->previous['lastNoSpace'] = $symbol;
		}
	}

// PARSING

// -----------------------------------------------------------------
// OPEN
	private function detectOpenParts($symbol){
		if($symbol == '') return;

		if(!$this->inQuotes($symbol)){

			if(!$this->currExpr['part']['item']){
				$this->detectExpression($symbol);
			}

			if(!$this->currExpr['part']['usermacro']){
				$this->detectItem($symbol);
				$this->detectFunction($symbol);
				$this->detectParam($symbol);
			}
		}
	}

	private function detectExpression($symbol){
// start expression
		if($symbol == '{'){
			$this->currExpr = $this->newExpr;

			$this->currExpr['part']['expression'] = true;
			$this->currExpr['part']['host'] = true;
		}

// start usermacro
		if($symbol == '$'){
			if($this->previous['prelast'] == '{'){
				$this->currExpr['part']['usermacro'] = true;
				$this->currExpr['part']['host'] = false;

				$this->currExpr['object']['host'] = '';
				$this->currExpr['object']['usermacro'] = $this->currExpr['object']['expression'];
			}
		}
	}

	private function detectMacro($symbol){
		if(($symbol == '}') && isset($this->allowed['macros'][$this->currExpr['object']['expression']])){
			$this->currExpr['object']['macro'] = '{'.$this->currExpr['object']['host'].'}';
			$this->currExpr['object']['host'] = '';
		}
	}

	private function detectItem($symbol){
// start item
		if($symbol == ':'){
			$this->currExpr['part']['host'] = false;
			$this->currExpr['part']['item'] = true;
		}

		if($symbol == ']'){
			if(!$this->inParameter() && !$this->currExpr['part']['item'])
				throw new Exception('Unexpected Square Bracket symbol in trigger expression.');
		}

	}

	private function detectFunction($symbol){
// start function, function params
		if($symbol == '('){
			if(!$this->currExpr['part']['item']) return;

			$this->currExpr['part']['item'] = false;
			$this->currExpr['part']['itemParam'] = false;
			$this->currExpr['part']['function'] = true;
			$this->currExpr['part']['functionParam'] = true;

			$lastDot = strrpos($this->currExpr['object']['item'], '.');

			$this->currExpr['object']['functionName'] = substr($this->currExpr['object']['item'],$lastDot+1);
			$this->currExpr['object']['function'] = substr($this->currExpr['object']['item'],$lastDot+1);
			$this->currExpr['object']['item'] = substr($this->currExpr['object']['item'],0,$lastDot);
		}

		if((($symbol != ' ') && ($symbol != ')')) &&
			$this->currExpr['part']['function'] &&
			!$this->currExpr['part']['functionParam'])
		{
			throw new Exception('Unexpected symbol "'.$symbol.'" in trigger function.');
		}
	}

	private function detectParam($symbol){
		if($symbol == ' ') return;

// start params
		if($this->currExpr['part']['itemParam'] || $this->currExpr['part']['functionParam']){
			if($this->inParameter()){
				if($this->inQuotes()){
// SDI('Open.inParameter.inQuotes: '.$symbol.' ');

 					if(($symbol == '"') && !$this->isSlashed(true)){
						$this->symbols['params'][$symbol]++;
						$this->currExpr['params']['quoteClose'] = true;
					}
					else{
						$this->writeParams($symbol);
					}
				}
				else{
// SDI('Open.inParameter: '.$symbol.' ');

					if(($symbol == ']') && $this->currExpr['part']['itemParam'])
						$this->symbols['params'][$symbol]++;
					else if(($symbol == ')') && $this->currExpr['part']['functionParam'])
						$this->symbols['close'][$symbol]++;
					else if($symbol == ','){
						$this->currExpr['params']['count']++;
						$this->currExpr['params']['comma']++;
						$this->currExpr['params']['quoteClose'] = false;
					}
					else if($symbol == '"'){
						if($this->emptyParameter())
							$this->symbols['params'][$symbol]++;

						if($this->currExpr['params']['quoteClose'])
							throw new Exception('Incorrect quote usage in trigger expression');
					}
					else{
						$this->writeParams($symbol);
					}
				}
			}
			else{
// SDI('Open: '.$symbol.' ');
				if(isset($this->symbols['params'][$symbol]))
					$this->symbols['params'][$symbol]++;

				if($symbol == ','){
					$this->writeParams();
					$this->currExpr['params']['count']++;
					$this->currExpr['params']['comma']++;
				}
				else if($this->currExpr['params']['count'] > 0){
					$this->writeParams($symbol);
				}
			}
		}

		if(!$this->inParameter()){
			if($this->currExpr['params']['count'] == 0){
				if(($symbol == '[') && $this->currExpr['part']['item']){
					$this->symbols['params'][$symbol]++;

					$this->currExpr['part']['itemParam'] = true;
					$this->writeParams();
				}

				if(($symbol == '(') && $this->currExpr['part']['function']){

					$this->currExpr['part']['functionParam'] = true;
					$this->writeParams();
				}
			}
		}
	}

// -----------------------------------------------------------------
// CLOSE

	private function detectCloseParts($symbol){
		if($symbol == '') return;

		if(!$this->inQuotes($symbol)){
			if(!$this->currExpr['part']['usermacro']){
				$this->detectParamClose($symbol);
			}

			if(!$this->currExpr['part']['item']){
// close symbols
				$this->detectExpressionClose($symbol);

				if($symbol == '}'){
					$this->expressions[] = $this->currExpr['object'];
					return true;
				}
			}
		}

		$this->writeParts($symbol);
	}

	private function detectExpressionClose($symbol){
// end expression
		if($symbol == '}'){
			$this->currExpr['part']['expression'] = false;
			$this->currExpr['part']['usermacro'] = false;
			$this->currExpr['part']['host'] = false;
			$this->currExpr['part']['item'] = false;
			$this->currExpr['part']['itemParam'] = false;
			$this->currExpr['part']['function'] = false;
			$this->currExpr['part']['functionParam'] = false;

			$this->currExpr['object']['expression'] = '{'.$this->currExpr['object']['expression'].'}';
			$this->currExpr['object']['host'] = rtrim($this->currExpr['object']['host'], ':');
			$this->currExpr['object']['item'] = $this->currExpr['object']['item'];
			$this->currExpr['object']['function'] = $this->currExpr['object']['function'];
			$this->currExpr['object']['functionName'] = rtrim($this->currExpr['object']['functionName'], '(');
			$this->currExpr['object']['functionParam'] = $this->currExpr['object']['functionParam'];
			$this->currExpr['object']['functionParamList'] = $this->currExpr['params']['function'];
		}

		if(($symbol == '}') && isset($this->allowed['macros'][$this->currExpr['object']['expression']])){
			$this->currExpr['object']['macro'] = '{'.$this->currExpr['object']['host'].'}';
			$this->currExpr['object']['host'] = '';
		}

		if(($symbol == '}') && !zbx_empty($this->currExpr['object']['usermacro'])){
			$this->currExpr['object']['usermacro'] = '{'.$this->currExpr['object']['usermacro'].'}';
		}
	}

	private function detectParamClose($symbol){
		if($symbol == ' ') return;
// end params
//		$this->writeParams();
		if(!$this->inQuotes()){
			if(($symbol == ']') && $this->currExpr['part']['item']){
// +1 because (detectParam is not counted this symbol yet)
				if($this->symbols['params']['['] == ($this->symbols['params'][']'] + 1)){
					$this->symbols['params'][$symbol]++;
// count points to the last param index
					if($this->currExpr['params']['count'] != $this->currExpr['params']['comma']){
						throw new Exception('Incorrect item parameters syntax is used');
					}

// do not turn of item part, till function is started
//					$this->currExpr['part']['item'] = false;
					$this->currExpr['part']['itemParam'] = false;
					$this->currExpr['params']['quoteClose'] = false;
					$this->currExpr['params']['count'] = 0;
					$this->currExpr['params']['comma'] = 0;
				}
			}

			if(($symbol == ')') && $this->currExpr['part']['function']){
// +1 because (checkSequence is not counted this symbol yet)
				if($this->symbols['open']['('] == ($this->symbols['close'][')'] + 1)){
					$this->writeParams();
// count points to the last param index
					if($this->currExpr['params']['count'] != $this->currExpr['params']['comma']){
						throw new Exception('Incorrect trigger function parameters syntax is used');
					}

// no need to close function part, it will be closed by expression end symbol
//					$this->currExpr['part']['function'] = false;
					$this->currExpr['part']['functionParam'] = false;
					$this->currExpr['params']['quoteClose'] = false;
					$this->currExpr['params']['count'] = 0;
					$this->currExpr['params']['comma'] = 0;
				}
			}
		}
	}

	private function writeParts($symbol){
		if($this->currExpr['part']['expression'])
			$this->currExpr['object']['expression'] .= $symbol;

		if($this->currExpr['part']['usermacro'])
			$this->currExpr['object']['usermacro'] .= $symbol;

		if($this->currExpr['part']['host'])
			$this->currExpr['object']['host'] .= $symbol;

		if(($symbol == ' ') && !$this->inParameter()) return;

		if($this->currExpr['part']['item'])
			$this->currExpr['object']['item'] .= $symbol;

		if($this->currExpr['part']['itemParam'])
			$this->currExpr['object']['itemParam'] .= $symbol;

		if($this->currExpr['part']['function'])
			$this->currExpr['object']['function'] .= $symbol;

		if($this->currExpr['part']['functionParam'])
			$this->currExpr['object']['functionParam'] .= $symbol;
	}

	private function writeParams($symbol=''){
		if($this->currExpr['part']['itemParam']){
			if(!isset($this->currExpr['params']['item'][$this->currExpr['params']['count']]))
				$this->currExpr['params']['item'][$this->currExpr['params']['count']] = '';

			$this->currExpr['params']['item'][$this->currExpr['params']['count']] .= $symbol;
		}
		else if($this->currExpr['part']['functionParam']){
			if(!isset($this->currExpr['params']['function'][$this->currExpr['params']['count']]))
				$this->currExpr['params']['function'][$this->currExpr['params']['count']] = '';

			$this->currExpr['params']['function'][$this->currExpr['params']['count']] .= $symbol;
		}
	}

	private function initializeVars(){
		$this->allowed = INIT_TRIGGER_EXPRESSION_STRUCTURES();

		$this->errors = array();
		$this->expressions = array();
		$this->data = array('hosts'=>array(),'usermacros'=>array(),'macros'=>array(),'items'=>array(),'functions'=>array());

		$this->newExpr = array(
			'part' => array(
				'expression' => false,
				'usermacro' => false,
				'host' => false,
				'item' => false,
				'itemParam' => false,
				'function' => false,
				'functionParam' => false,
			),
			'object' => array(
				'expression' => '',
				'macro' => '',
				'usermacro' => '',
				'host' => '',
				'item' => '',
				'itemParam' => '',
				'function' => '',
				'functionName' => '',
				'functionParam' => '',
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
			),
			'expr' => array(
				'$' => 0,		// dollar
				'\\' => 0,		// backslash
				':' => 0,		// colon
				'.' => 0,		// dot
			),
			'params' => array(
				'"' => 0,		// quote
				'[' => 0,		// open brace
				']' => 0,		// closebrace
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
?>
