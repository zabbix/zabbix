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
		$this->checkExpression($trigger['expression']);
	}

	public function checkExpression($expression){
		$length = zbx_strlen($expression);
		$symbolNum = 0;

		try{
			if(zbx_empty(trim($expression)))
				throw new Exception('Empty expression.');

// Check expr start symbol
			$startSymbol = zbx_substr(trim($expression), 0, 1);
			if(($startSymbol != '(') && ($startSymbol != '{') && ($startSymbol != '-') && !zbx_ctype_digit($startSymbol))
				throw new Exception('Incorrect trigger expression.');


			for($symbolNum = 0; $symbolNum < $length; $symbolNum++){
				$symbol = zbx_substr($expression, $symbolNum, 1);
// SDI($symbol);
				$this->parseOpenParts($this->previous['last']);
				$this->parseCloseParts($symbol);
// SDII($this->currExpr);
				if($this->inParameter($symbol)){
					$this->setPreviousSymbol($symbol);
					continue;
				}

				$this->checkSymbolSequence($symbol);
				$this->setPreviousSymbol($symbol);
// SDII($this->symbols);
			}

			$symbolNum = 0;

			$simpleExpression = $expression;
			$this->checkBraces();
			$this->checkParts($simpleExpression);
			$this->checkSimpleExpression($simpleExpression);
		}
		catch(Exception $e){
			$symbolNum = ($symbolNum > 0) ? --$symbolNum : $symbolNum;

			$this->errors[] = $e->getMessage();
			$this->errors[] = 'Check expression part starting from " '.zbx_substr($expression, $symbolNum).' "';
		}
	}


	public function checkMacro($macro){
		if(!preg_match('/^'.ZBX_PREG_EXPRESSION_SIMPLE_MACROS.'$/i', $macro))
			throw new Exception('Incorrect macro "'.$macro.'" is used in expression.');
	}

	public function checkUserMacro($usermacro){
		return preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/i', $usermacro);
	}

	public function checkHost($host){
		if(zbx_empty($host))
			throw new Exception('Empty host name "'.$host.'" provided in expression.');

		if(!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/i', $host))
			throw new Exception('Incorrect host name "'.$host.'" provided in expression.');
	}

	public function checkItem($item){
		if(zbx_empty($item))
			throw new Exception('Empty item key "'.$item.'" is used in expression.');

		$itemCheck = check_item_key($item);
		if(!$itemCheck['valid'])
			throw new Exception('Incorrect item key "'.$expr['item'].'" is used in expression.'.$itemCheck['description']);
	}

	public function checkFunction($expression){
		if(!isset($this->allowed['functions'][$expression['functionName']]))
			throw new Exception('Incorrect trigger function "'.$expression['function'].'" provided in expression.');

		if(!preg_match('/^'.ZBX_PREG_FUNCTION_FORMAT.'$/u', $expression['function']))
			throw new Exception('Incorrect trigger function "'.$expression['function'].'" provided in expression.');

		if(is_null($this->allowed['functions'][$expression['functionName']]['args'])) return true;

		foreach($this->allowed['functions'][$expression['functionName']]['args'] as $anum => $arg){

// mandatory check
			if(isset($arg['mandat']) && $arg['mandat'] && (!isset($expression['functionParamList'][$anum]) || zbx_empty($expression['functionParamList'][$anum])))
				throw new Exception('Incorrect trigger function parameters count "'.$expression['function'].'" provided in expression.');


// type check
			if(isset($arg['type']) && isset($expression['functionParamList'][$anum])){
				if(($arg['type'] == 'str') && !is_string($expression['functionParamList'][$anum]))
					throw new Exception('Expected string for trigger function parameter in function "'.$expression['function'].'" provided in expression.');

				if(($arg['type'] == 'sec') && (validate_float($expression['functionParamList'][$anum]) != 0))
					throw new Exception('Expected counter or macro for trigger function parameter in function "'.$expression['function'].'" provided in expression.');

				if(($arg['type'] == 'sec_num') && (validate_ticks($expression['functionParamList'][$anum]) != 0))
					throw new Exception('Expected numeric or counter or macro for trigger function parameter in function "'.$expression['function'].'" provided in expression.');

				if(($arg['type'] == 'num') && !is_numeric($expression['functionParamList'][$anum]))
					throw new Exception('Expected numeric or macro for trigger function parameter in function "'.$expression['function'].'" provided in expression.');
			}
		}

		return 1;
	}

	public function checkSimpleExpression(&$expression){
// <expression> = <expression> [=#<>|&+-/*] <expression>
// <expression> = (<expression>)
// <expression> =  - <space>(0,N) <constant> | <constant>
// <constant> = <number> | function | macro | user macro
// <number> = <integer> | <integer><suffix>| <integer>.<integer> | <integer>.<integer><suffix>
// <suffix> = [KMGTsmhdw]

		$expression = preg_replace("/(\d+(\.\d+)?[KMGTsmhdw]?)/u", '{constant}', $expression);
		$simpleExpr = str_replace(' ','',$expression);

// constant => expression
		$start = '';
		while($start != $simpleExpr){
			$start = $simpleExpr;
			$simpleExpr = str_replace('({constant})','{expression}',$simpleExpr);
			$simpleExpr = str_replace('(-{constant})','{expression}',$simpleExpr);
			$simpleExpr = preg_replace("/([\(\=\#\<\>\|\&\+\-\/\*])\-\{constant\}/u", '$1{expression}', $simpleExpr);
		}

		$simpleExpr = preg_replace('/^\-\{constant\}(.*)$/u', '{constant}$1', $simpleExpr);
		$simpleExpr = str_replace('{constant}','{expression}',$simpleExpr);

// expression => expression
		$start = '';
		while($start != $simpleExpr){
			$start = $simpleExpr;
			$simpleExpr = str_replace('({expression})','{expression}',$simpleExpr);
			$simpleExpr = preg_replace("/\{expression\}([\=\#\<\>\|\&\+\-\/\*])\{expression\}/u", '{expression}', $simpleExpr);
		}

		$simpleExpr = str_replace('{expression}','1',$simpleExpr);

		if(strpos($simpleExpr,'()') !== false)
			throw new Exception('Incorrect trigger expression format " '.$expression.' "');
		if(strpos($simpleExpr,'11') !== false)
			throw new Exception('Incorrect trigger expression format " '.$expression.' "');

		$linkageCount = 0;
		$linkageExpr = '';
		foreach($this->symbols['linkage'] as $symb => $count){
			if($symb == ' ') continue;

			$linkageCount += substr_count($simpleExpr, $symb);
			$linkageExpr .= '\\'.$symb;

			if((strpos($simpleExpr, $symb.')') !== false) || (strpos($simpleExpr,'('.$symb.'1') !== false))
				throw new Exception('Incorrect trigger expression format " '.$expression.' "');
		}

		if(!preg_match('/^([\d'.$linkageExpr.']+)$/ui', $simpleExpr))
			throw new Exception('Incorrect trigger expression format " '.$expression.' "');

		$exprCount = substr_count($simpleExpr, '1');

		if($linkageCount != $exprCount-1)
			throw new Exception('Incorrect usage of expression logic linking symbols');
	}

// PRIVATE --------------------------------------------------------------------------------------------
	private function checkBraces(){
		if($this->symbols['params']['"'] % 2 != 0)
			throw new Exception('Incorrect count of quotes in expression');

		if($this->symbols['open']['('] != $this->symbols['close'][')']){
			throw new Exception('Incorrect parenthesis count in expression.');
		}

		if($this->symbols['open']['{'] != $this->symbols['close']['}'])
			throw new Exception('Incorrect curly braces count in expression.');
	}

	private function checkParts(&$expression){
		foreach($this->expressions as $enum => $expr){
			if(!zbx_empty($expr['macro'])){
				$this->checkMacro($expr['macro']);


				$this->data['macros'][] = $expr['macro'];
			}
			else if(!zbx_empty($expr['usermacro'])){
				if(!$this->checkUserMacro($expr['usermacro']))
					throw new Exception('Incorrect user macro "'.$expr['usermacro'].'" format is used in expression.');

				$this->data['usermacros'][] = $expr['usermacro'];
			}
			else{
				$this->checkHost($expr['host']);
				$this->checkItem($expr['item']);


				$this->checkFunction($expr);

				$this->data['hosts'][] = $expr['host'];
				$this->data['items'][] = $expr['item'];
				$this->data['functions'][] = $expr['functionName'];
				$this->data['functionParams'][] = $expr['functionParam'];

// ading user macros from item & trigger params
				foreach($expr['itemParamList'] as $itemParam){
					if($this->checkUserMacro($itemParam))
						$this->data['usermacros'][] = $itemParam;
				}

				foreach($expr['functionParamList'] as $funcParam){
					if($this->checkUserMacro($funcParam))
						$this->data['usermacros'][] = $funcParam;
				}
			}

			$expression = str_replace($expr['expression'], '{constant}', $expression);
		}

		if(empty($this->data['hosts']) || empty($this->data['items']))
			throw new Exception('Trigger expression must contain at least one host:key reference.');
	}

// STATE
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



// PARSING

// -----------------------------------------------------------------
// OPEN
	private function parseOpenParts($symbol){
		if(!$this->inQuotes($symbol)){

			if(!$this->currExpr['part']['item'] && !$this->currExpr['part']['functionParam']){
				$this->parseExpression($symbol);
			}

			if(!$this->currExpr['part']['usermacro'] &&
				!$this->currExpr['part']['itemParam'] &&
				!$this->currExpr['part']['functionParam']
			){
				$this->parseItem($symbol);
				$this->parseFunction($symbol);
			}
		}

		if(!$this->currExpr['part']['usermacro']){
			$this->parseParam($symbol);
		}
	}

	private function parseExpression($symbol){
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

	private function parseMacro($symbol){
		if(($symbol == '}') && isset($this->allowed['macros'][$this->currExpr['object']['expression']])){
			$this->currExpr['object']['macro'] = '{'.$this->currExpr['object']['host'].'}';
			$this->currExpr['object']['host'] = '';
		}
	}

	private function parseItem($symbol){
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

	private function parseFunction($symbol){
// start function
		if($symbol == '('){
			if(!$this->currExpr['part']['item']) return;

			$this->currExpr['part']['item'] = false;
			$this->currExpr['part']['itemParam'] = false;
			$this->currExpr['part']['function'] = true;

			$lastDot = strrpos($this->currExpr['object']['item'], '.');

			$this->currExpr['object']['functionName'] = substr($this->currExpr['object']['item'],$lastDot+1);
			$this->currExpr['object']['function'] = substr($this->currExpr['object']['item'],$lastDot+1);
			$this->currExpr['object']['item'] = substr($this->currExpr['object']['item'],0,$lastDot);
		}
	}

	private function parseParam($symbol){
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
					else if(($symbol == ')') && $this->currExpr['part']['functionParam']){
						$this->symbols['close'][$symbol]++;
						$this->symbols['params'][$symbol]++;
					}
					else if($symbol == ','){
						$this->currExpr['params']['count']++;
						$this->currExpr['params']['comma']++;
						$this->currExpr['params']['quoteClose'] = false;
					}
					else{
						if(($symbol == '"') && $this->emptyParameter())
							$this->symbols['params'][$symbol]++;

						if($this->currExpr['params']['quoteClose'])
							throw new Exception('Incorrect quote usage in trigger expression');

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
					$this->symbols['params'][$symbol]++;

					$this->currExpr['part']['functionParam'] = true;
					$this->writeParams();
				}
			}
		}
	}

// -----------------------------------------------------------------
// CLOSE

	private function parseCloseParts($symbol){
		if(!$this->inQuotes($symbol)){
			if(!$this->inParameter()){
// close symbols
				$this->parseExpressionClose($symbol);

				if($symbol == '}'){
					$this->expressions[] = $this->currExpr['object'];
					return ;
				}
			}

			if(!$this->currExpr['part']['usermacro']){
				$this->parseParamClose($symbol);
			}
		}

		$this->writeParts($symbol);
	}

	private function parseExpressionClose($symbol){
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
			$this->currExpr['object']['itemParamList'] = $this->currExpr['params']['item'];
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

		if($this->currExpr['part']['function'] && !$this->currExpr['part']['functionParam']){
//SDII($this->currExpr['part']);
			throw new Exception('Unexpected symbol "'.$symbol.'" in trigger function.');
		}

	}

	private function parseParamClose($symbol){
		if($symbol == ' ') return;
// end params
//		$this->writeParams();
		if(!$this->inQuotes()){
			if(($symbol == ']') && $this->currExpr['part']['itemParam']){
// +1 because (parseParam is not counted this symbol yet)
				if($this->symbols['params']['['] == ($this->symbols['params'][']'] + 1)){
					$this->symbols['params'][$symbol]++;

					$this->writeParams();
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

			if(($symbol == ')') && $this->currExpr['part']['functionParam']){
// +1 because (checkSequence is not counted this symbol yet)
				if($this->symbols['params']['('] == ($this->symbols['params'][')'] + 1)){
					$this->symbols['params'][$symbol]++;

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

// WRITE expression parts
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

// CHECKS
	private function checkSymbolSequence($symbol){
// Check close symbols
		if(($symbol == '}') && ($this->symbols['open']['{'] <= $this->symbols['close']['}']))
			throw new Exception('Incorrect closing curly braces in trigger expression.');

		if(($symbol == ')') && ($this->symbols['open']['('] <= $this->symbols['close'][')']))
			throw new Exception('Incorrect closing parenthesis in trigger expression.');

// check symbol sequence
		if(($symbol != '-') && isset($this->symbols['linkage'][$symbol]) && isset($this->symbols['linkage'][$this->previous['lastNoSpace']])){
			throw new Exception('Incorrect symbol sequence in trigger expression.');
		}

// we shouldn't count open braces in params
		if(!$this->currExpr['part']['itemParam'] && !$this->currExpr['part']['functionParam'])
			if(isset($this->symbols['open'][$symbol])) $this->symbols['open'][$symbol]++;

		if(isset($this->symbols['close'][$symbol])) $this->symbols['close'][$symbol]++;
		if(isset($this->symbols['expr'][$symbol])) $this->symbols['expr'][$symbol]++;
		if(isset($this->symbols['linkage'][$symbol])) $this->symbols['linkage'][$symbol]++;
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

// INIT
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
				'itemParamList' => '',
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
				'{' => 0		// curly brace
			),
			'close' => array(
				')' => 0,		// parenthesis
				'}' => 0,		// curly brace
			),
			'linkage' => array(
				'+' => 0,		// addition
				'-' => 0,		// subtraction
				'*' => 0,		// multiplication
				'/' => 0,		// division
				'#' => 0,		// not equals
				'=' => 0,		// equals
				'<' => 0,		// less than
				'>' => 0,		// greater than
				'&' => 0,		// logical and
				'|' => 0,		// logical or
			),
			'expr' => array(
				'$' => 0,		// dollar
				'\\' => 0,		// backslash
				':' => 0,		// colon
				'.' => 0,		// dot
			),
			'params' => array(
				'"' => 0,		// quote
				'[' => 0,		// open square brace
				']' => 0,		// close square brace
				'(' => 0,		// open brace
				')' => 0		// close brace
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
