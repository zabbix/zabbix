<?php
class CItemKeyExpression extends CExpression {

	public function __construct($item){
		$this->initializeVars();
		$this->checkExpression($item['key_']);
	}


	protected function initializeVars(){
		$this->allowed = INIT_TRIGGER_EXPRESSION_STRUCTURES();

		$this->errors = array();
		$this->expressions = array();
		$this->data = array(
			'hosts'=>array(),
			'usermacros'=>array(),
			'macros'=>array(),
			'items'=>array(),
			'itemParams'=>array(),
			'functions'=>array(),
			'functionParams'=>array()
		);

		$this->newExpr = array(
			'part' => array(
				'expression' => false,
				'usermacro' => false,
				'host' => false,
				'item' => true,  // we are at item already
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


	public function checkExpression($expression){
		$length = zbx_strlen($expression);
		
		try{
			if(zbx_empty(trim($expression)))
				throw new Exception('Empty key.');

			// checking if item key is valid
			$check_result = check_item_key($expression);
			if (!$check_result['valid']) {
				throw new Exception($check_result['description']);
			}

			for($symbolNum = 0; $symbolNum < $length; $symbolNum++){
				$symbol = zbx_substr($expression, $symbolNum, 1);
				$this->parseOpenParts($this->previous['last']);
				$this->parseCloseParts($symbol);
				if($this->inParameter($symbol)){
					$this->setPreviousSymbol($symbol);
					continue;
				}

				$this->checkSymbolSequence($symbol);
				$this->setPreviousSymbol($symbol);
			}

			$this->expressions[] = $this->currExpr;
		}
		catch(Exception $e){
			$this->errors[] = $e->getMessage();
		}
	}




}
?>
