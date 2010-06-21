<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
class CTable extends CTag{
 public $headerClass;
 public $footerClass;

 protected $oddRowClass;
 protected $evenRowClass;

 protected $header;
 protected $footer;

 protected $colnum;
 protected $rownum;

 protected $message;

	public function __construct($message=NULL,$class=NULL){
		parent::__construct('table','yes');
		$this->setClass($class);

		$this->rownum = 0;
		$this->oddRowClass = NULL;
		$this->evenRowClass = NULL;

		$this->header = '';
		$this->headerClass = NULL;
		$this->footer = '';
		$this->footerClass = NULL;
		$this->colnum = 1;

		$this->message = $message;
	}

	public function setOddRowClass($value=NULL){
		$this->oddRowClass = $value;
	}

	public function setEvenRowClass($value=NULL){
		$this->evenRowClass = $value;
	}

	public function setAlign($value){
		return $this->attributes['align'] = $value;
	}

	public function setCellPadding($value){
		return $this->attributes['cellpadding'] = strval($value);
	}

	public function setCellSpacing($value){
		return $this->attributes['cellspacing'] = strval($value);
	}

	public function prepareRow($item,$rowClass=NULL){
		if(is_null($item)) return NULL;

		if(is_object($item) && zbx_strtolower(get_class($item))=='ccol') {
			if(isset($this->header) && !isset($item->attributes['colspan']))
				$item->attributes['colspan'] = $this->colnum;

			$item = new CRow($item,$rowClass);
		}
		if(is_object($item) && zbx_strtolower(get_class($item))=='crow') {
			if(isset($rowClass))
				$item->setClass($rowClass);
		}
		else{
			$item = new CRow($item,$rowClass);
		}
		if(!isset($item->attributes['class']) || is_array($item->attributes['class'])){
			$class = ($this->rownum % 2)?$this->oddRowClass:$this->evenRowClass;
			$item->setClass($class);
			$item->setAttribute('origClass', $class);
		}

	return $item;
	}

	public function setHeader($value=NULL,$class='header'){
		if(isset($_REQUEST['print'])) hide_form_items($value);
		if(is_null($class)) $class = $this->headerClass;

		if(is_object($value) && zbx_strtolower(get_class($value))=='crow') {
			if(!is_null($class)) $value->setClass($class);
		}
		else{
			$value = new CRow($value,$class);
		}

		$this->colnum = $value->itemsCount();
		$this->header = $value->toString();
	}

	public function setFooter($value=NULL,$class='footer'){
		if(isset($_REQUEST['print'])) hide_form_items($value);
		if(is_null($class)) $class = $this->footerClass;

		$this->footer = $this->prepareRow($value,$class);
		$this->footer = $this->footer->toString();
	}

	public function addRow($item,$rowClass=NULL){
		$item = $this->addItem($this->prepareRow($item,$rowClass));
		++$this->rownum;
	return $item;
	}

	public function showRow($item,$rowClass=NULL){
		echo $this->prepareRow($item,$rowClass)->toString();
		//---------------
		++$this->rownum;
	}

	public function getNumRows(){
		return $this->rownum;
	}

/* protected */
	public function startToString(){
		$ret = parent::startToString();
		$ret .= $this->header;
	return $ret;
	}

	public function endToString(){
		$ret = '';
		if($this->rownum == 0 && isset($this->message)) {
			$ret = $this->prepareRow(new CCol($this->message,'message'));
			$ret = $ret->toString();
		}
		$ret .= $this->footer;
		$ret .= parent::endToString();
	return $ret;
	}
}
?>
