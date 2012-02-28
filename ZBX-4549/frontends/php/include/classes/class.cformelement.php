<?php

class CFormElement{
	private $header = null;
	private $footer = null;
	private $body = null;

	public function __construct($header=null, $body=null, $footer=null){
		$this->header = $header;
		$this->body = $body;
		$this->footer = $footer;
	}

	public function setHeader($header){
		$this->header = $header;
	}

	public function setBody($body){
		$this->body = $body;
	}

	public function setFooter($footer){
		$this->footer = $footer;
	}

	public function toString(){

		$content = array();
		
		if(!is_null($this->header)){
			$header_div = new CDiv($this->header, 'formElement_header');
			$content[] = $header_div;
		}

		if(!is_null($this->body)){
			$body_div = new CDiv($this->body, 'formElement_body');
			$content[] = $body_div;
		}

		if(!is_null($this->footer)){
			$footer_div = new CDiv($this->footer, 'formElement_footer');
			$content[] = $footer_div;
		}

		$main_div = new CDiv($content, 'formElement');
		return unpack_object($main_div);
	}
}

?>
