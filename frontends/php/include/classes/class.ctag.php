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
class CTag extends CObject{
/* private *//*
	var $tagname;
	var $attributes = array();
	var $paired;*/
/* protected *//*
	var $items = array();

	var $tag_body_start;
	var $tag_body_end;
	var $tag_start;
	var $tag_end;*/

/* public */
	public function __construct($tagname=NULL, $paired='no', $body=NULL, $class=null){
		parent::__construct();

		$this->options = array();

		if(!is_string($tagname)){
			return $this->error('Incorrect tagname for CTag ['.$tagname.']');
		}
		
		$this->tagname = $tagname;
		$this->paired = $paired;

		$this->tag_start = $this->tag_end = $this->tag_body_start = $this->tag_body_end = '';

		if(is_null($body)){
			$this->tag_end = $this->tag_body_start = "\n";
		}
		else{
			CTag::addItem($body);
		}

		$this->setClass($class);
	}
	
	public function showStart(){	echo $this->startToString();}
	public function showBody(){	echo $this->bodyToString();	}
	public function showEnd(){		echo $this->endToString();	}

	public function startToString(){
		$res = $this->tag_start.'<'.$this->tagname;
		foreach($this->options as $key => $value){
			$res .= ' '.$key.'="'.$value.'"';
		}
		$res .= ($this->paired=='yes')?'>':' />';
	return $res;
	}

	public function bodyToString(){
		$res = $this->tag_body_start;
	return $res.parent::ToString(false);
		
		/*foreach($this->items as $item)
			$res .= $item;
		return $res;*/
	}
	
	public function endToString(){
		$res = ($this->paired=='yes') ? $this->tag_body_end.'</'.$this->tagname.'>' : '';
		$res .= $this->tag_end;
	return $res;
	}
	
	public function toString($destroy=true){
		$res  = $this->startToString();
		$res .= $this->bodyToString();
		$res .= $this->endToString();

		if($destroy) $this->destroy();

	return $res;
	}
	
	public function setName($value){
		if(is_null($value)) return $value;

		if(!is_string($value)){
			return $this->error("Incorrect value for SetName [$value]");
		}
	return $this->addOption("name",$value);
	}
	
	public function getName(){
		if(isset($this->options['name']))
			return $this->options['name'];
	return NULL;
	}
	
	public function setClass($value){
		if(isset($value))
			$this->options['class'] = $value;
		else
			unset($this->options['class']);

	return $value;
	}
	
	public function getOption($name){
		$ret = NULL;
		if(isset($this->options[$name]))
			$ret =& $this->options[$name];
	return $ret;
	}

	public function addOption($name, $value){
		if(is_object($value)){
			$this->options[$name] = unpack_object($value);
		}
		else if(isset($value))
			$this->options[$name] = htmlspecialchars(strval($value)); 
		else
			unset($this->options[$name]);
	}
	
	public function delOption($name){
		unset($this->options[$name]);
	}

	public function addAction($name, $value){
		if(is_object($value)){
			$this->options[$name] = unpack_object($value);
		}
		else if(!empty($value)){
			$this->options[$name] = htmlentities(str_replace(array("\r", "\n"), '', strval($value)),ENT_COMPAT,S_HTML_CHARSET);
		}
	}
	
	public function setHint($text, $width='', $class=''){
		if(empty($text)) return false;

		insert_showhint_javascript();

		$text = unpack_object($text);
		if($width != '' || $class != ''){
			$code = "show_hint_ext(this,event,'".$text."','".$width."','".$class."');";
		}
		else{
			$code = "show_hint(this,event,'".$text."');";
		}

		$this->addAction('onMouseOver',	$code);
		$this->addAction('onMouseMove',	'update_hint(this,event);');
	}

	public function onClick($handle_code){
		$this->addAction('onClick', $handle_code);
	}
	
	public function addStyle($value){
		if(!isset($this->options['style'])) $this->options['style'] = '';
		
		if(isset($value))
			$this->options['style'].= htmlspecialchars(strval($value)); 
		else
			unset($this->options['style']);
	}

	public function setEnabled($value='yes'){
		if((is_string($value) && ($value == 'yes' || $value == 'enabled' || $value=='on') || $value=='1') || (is_int($value) && $value<>0)){
			unset($this->options['disabled']);
		}
		else if((is_string($value) && ($value == 'no' || $value == 'disabled' || $value=='off') || $value=='0') || (is_int($value) && $value==0)){
			$this->options['disabled'] = 'disabled';
		}
	}
	
	public function error($value){
		error('class('.get_class($this).') - '.$value);
		return 1;
	}
}
?>