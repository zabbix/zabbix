<?php
class CTriggerExpression{
public $errors;
public $data;
public $expressions;

private $symbol;
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

				$this->detectOpenParts($this->previous['last']);
				$this->detectCloseParts($symbol);

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

	private function inQuotes($symbol=''){
		if(($symbol == '"') || ($symbol == '\\')) return false;

		return (bool)($this->symbols['inquotes'] % 2);
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
// watch for closing brakets
		if(isset($this->symbols['close'][$symbol])) $this->symbols['close'][$symbol]++;
		if(isset($this->symbols['open'][$symbol])) $this->symbols['open'][$symbol]++;
		if(isset($this->symbols['expr'][$symbol])) $this->symbols['expr'][$symbol]++;
		if(isset($this->symbols['linkage'][$symbol])) $this->symbols['linkage'][$symbol]++;

		if($this->currExpr['part']['expression']){
			if(($symbol == '"') && ($this->previous['last'] == '\\') && ($this->symbols['sequence'] % 2 == 1)){
				$this->symbols['expr'][$symbol]--;
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
		if($this->symbols['inquotes'] % 2 != 0)
			throw new Exception('Incorrect count of quotes in expression');

		if($this->symbols['open']['('] != $this->symbols['close'][')'])
			throw new Exception('Incorrect parenthesis count in expression');

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
				if(!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/i', $expr['host']))
					throw new Exception('Incorrect host name is used in expression');

				$checkResult = check_item_key($expr['item']);
				if(!$checkResult['valid'])
					throw new Exception('Incorrect item key "'.$expr['item'].'" is used in expression, '.$checkResult['description']);

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
				throw new Exception('Incorrect number of agruments passed to function "'.$expr['function'].'"');
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

	private function parseParameter($symbol){
		if($this->currExpr['part']['itemParam'])
			$params = &$this->currExpr['object']['itemParamList'];
		else
			$params = &$this->currExpr['object']['functionParamList'];

		$paramCount = count($params);
		if($paramCount > 0) $paramCount--;

		if(($symbol == '"') && (($this->previous['last'] != '\\') || ($this->symbols['sequence'] % 2 != 1))){

			if($this->inQuotes()){
// closing quoted string
				$this->symbols['inquotes']++;
			}
			else{
// assuming starting quoted string

// param is not started or is empty
				if(!isset($params[$this->symbols['inquotes']/2]) || zbx_empty($params[$this->symbols['inquotes']/2])){
					$this->symbols['inquotes']++;
					$params[$paramCount] = '';
				}

				if(!$this->inQuotes() && zbx_empty($params[$paramCount])){
					if($this->currExpr['part']['itemParam'])
						throw new Exception('Incorrect item parameter syntax is used');
					else
						throw new Exception('Incorrect trigger function parameter syntax is used');
				}

// if open/close quotes more then parameters
				if(($this->symbols['inquotes']/2) > count($params)){
					if($this->currExpr['part']['itemParam'])
						throw new Exception('Incorrect item parameter syntax is used2');
					else
						throw new Exception('Incorrect trigger function parameter syntax is used2');
				}
			}
			return;
		}

		if(!$this->inQuotes()){
			if($symbol == ' ') return;
			if(($symbol == ',') && (($this->previous['last'] != '\\') || ($this->symbols['sequence'] % 2 != 1))){
				$params[$paramCount+1] = '';
				return;
			}
		}

		if(!isset($params[$paramCount]))
			$params[$paramCount] = '';

		$params[$paramCount] .= $symbol;
	}

	private function setPreviousSymbol($symbol){
		if($this->previous['last'] == $symbol){
			$this->symbols['sequence']++;
		}
		else{
			$this->symbols['sequence'] = 1;

			if($symbol != ' '){
				$this->previous['prelast'] = $this->previous['last'];
				$this->previous['last'] = $symbol;
			}
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

// PARSING

// -----------------------------------------------------------------
// OPEN
	private function detectOpenParts($symbol){
		if($symbol == '') return;

		if(!$this->inQuotes($symbol)){
			if(!$this->currExpr['part']['item']){
				$this->detectExpression($symbol);
				$this->detectUserMacro($symbol);
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
	}

	private function detectMacro($symbol){
		if(($symbol == '}') && isset($this->allowed['macros'][$this->currExpr['object']['expression']])){
			$this->currExpr['object']['macro'] = '{'.$this->currExpr['object']['host'].'}';
			$this->currExpr['object']['host'] = '';
		}
	}

	private function detectUserMacro($symbol){
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

	private function detectItem($symbol){
// start item
		if($symbol == ':'){
			$this->currExpr['part']['host'] = false;
			$this->currExpr['part']['item'] = true;
		}
	}

	private function detectFunction($symbol){
// start function, function params
		if($symbol == '('){
			if(!$this->currExpr['part']['item']) return;

			$this->currExpr['part']['item'] = false;
			$this->currExpr['part']['function'] = true;
			$this->currExpr['part']['functionParam'] = true;

			$lastDot = strrpos($this->currExpr['object']['item'], '.');

			$this->currExpr['object']['functionName'] = substr($this->currExpr['object']['item'],$lastDot+1);
			$this->currExpr['object']['function'] = substr($this->currExpr['object']['item'],$lastDot+1);
			$this->currExpr['object']['item'] = substr($this->currExpr['object']['item'],0,$lastDot);
		}
	}

	private function detectParam($symbol){
// start params
		if($this->currExpr['part']['item'] && ($symbol == '[')){
			$this->currExpr['part']['itemParam'] = true;
		}

		if($this->currExpr['part']['function']  && ($symbol == '(')){
			$this->currExpr['part']['functionParam'] = true;
		}
	}

// -----------------------------------------------------------------
// CLOSE

	private function detectCloseParts($symbol){
		if($symbol == '') return;

		if(!$this->inQuotes($symbol)){
			if(!$this->currExpr['part']['usermacro']){
				$this->detectFunctionClose($symbol);

				if(!$this->inQuotes())
					$this->detectParamClose($symbol);
			}

			if(!$this->currExpr['part']['item']){
// close symbols
				$this->detectExpressionClose($symbol);
				$this->detectMacroClose($symbol);
				$this->detectUserMacroClose($symbol);

				if($symbol == '}'){
					$this->expressions[] = $this->currExpr['object'];
					return true;
				}
			}

			if($this->currExpr['part']['itemParam'] || $this->currExpr['part']['functionParam']){
				$this->parseParameter($symbol);
			}
		}

		$this->writeParts($symbol);
	}

	private function detectExpressionClose($symbol){
// end expression
		if($symbol == '}'){
			$this->currExpr['part']['usermacro'] = false;
			$this->currExpr['part']['expression'] = false;
			$this->currExpr['part']['host'] = false;
			$this->currExpr['part']['item'] = false;
			$this->currExpr['part']['function'] = false;

			$this->currExpr['object']['expression'] = '{'.$this->currExpr['object']['expression'].'}';
			$this->currExpr['object']['host'] = rtrim($this->currExpr['object']['host'], ':');
			$this->currExpr['object']['item'] = $this->currExpr['object']['item'];
			$this->currExpr['object']['function'] = $this->currExpr['object']['function'];
			$this->currExpr['object']['functionName'] = rtrim($this->currExpr['object']['functionName'], '(');
			$this->currExpr['object']['functionParam'] = $this->currExpr['object']['functionParam'];
		}
	}

	private function detectMacroClose($symbol){
		if(($symbol == '}') && isset($this->allowed['macros'][$this->currExpr['object']['expression']])){
			$this->currExpr['object']['macro'] = '{'.$this->currExpr['object']['host'].'}';
			$this->currExpr['object']['host'] = '';
		}
	}

	private function detectUserMacroClose($symbol){
		if(($symbol == '}') && !zbx_empty($this->currExpr['object']['usermacro'])){
			$this->currExpr['object']['usermacro'] = '{'.$this->currExpr['object']['usermacro'].'}';
		}
	}

	private function detectFunctionClose($symbol){
// end function, functionParam
		if($symbol == ')'){
			if(!$this->currExpr['part']['function']) return;

			$this->currExpr['part']['functionParam'] = false;
		}
	}
	private function detectParamClose($symbol){
// end params
		if($this->currExpr['part']['itemParam'] && ($symbol == ']')){
			$this->currExpr['part']['itemParam'] = false;
			$this->symbols['inquotes'] = 0;
		}

		if($this->currExpr['part']['functionParam'] && ($symbol == ')')){
			$this->currExpr['part']['functionParam'] = false;
			$this->symbols['inquotes'] = 0;
		}
	}

	private function writeParts($symbol){
		if($this->currExpr['part']['expression'])
			$this->currExpr['object']['expression'] .= $symbol;

		if($this->currExpr['part']['usermacro'])
			$this->currExpr['object']['usermacro'] .= $symbol;

		if($this->currExpr['part']['host'])
			$this->currExpr['object']['host'] .= $symbol;

		if($this->currExpr['part']['item'])
			$this->currExpr['object']['item'] .= $symbol;

		if($this->currExpr['part']['function'])
			$this->currExpr['object']['function'] .= $symbol;

		if($this->currExpr['part']['functionParam'])
			$this->currExpr['object']['functionParam'] .= $symbol;
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
				'itemParamList' => array(),
				'function' => '',
				'functionName' => '',
				'functionParam' => '',
				'functionParamList' => array()
			)
		);
		$this->currExpr = $this->newExpr;

		$this->symbols = array(
			'sequence' => 0,
			'inquotes' => 0,
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
			'prelast' => '',
			'open' => '',
			'close' => '',
			'linkage' => '',
			'expr'=> '',
			'part' => ''
		);
	}
}
?>
