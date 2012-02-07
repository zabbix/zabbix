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

		$this->attributes = array();

		if(!is_string($tagname)){
			return $this->error('Incorrect tagname for CTag ['.$tagname.']');
		}

		$this->tagname = $tagname;
		$this->paired = $paired;

		$this->tag_start = $this->tag_end = $this->tag_body_start = $this->tag_body_end = '';

		if(is_null($body)){
			$this->tag_end = $this->tag_body_start = '';
		}
		else{
			$this->addItem($body);
		}

		$this->setClass($class);
	}

	public function showStart(){	echo $this->startToString();}
	public function showBody(){	echo $this->bodyToString();	}
	public function showEnd(){		echo $this->endToString();	}

	// Do not put new line symbol (\n) before or after html tags,
	// it adds spaces in unwanted places
	public function startToString() {
		$res = $this->tag_start.'<'.$this->tagname;
		foreach ($this->attributes as $key => $value) {
			$res .= ' '.$key.'="'.$this->sanitize($value).'"';
		}
		$res .= ($this->paired === 'yes') ? '>' : ' />';

		return $res;
	}

	public function bodyToString(){
		$res = $this->tag_body_start;
	return $res.parent::toString(false);

		/*foreach($this->items as $item)
			$res .= $item;
		return $res;*/
	}

	public function endToString(){
		$res = ($this->paired==='yes') ? $this->tag_body_end.'</'.$this->tagname.'>' : '';
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
	return $this->setAttribute("name",$value);
	}

	public function getName(){
		if(isset($this->attributes['name']))
			return $this->attributes['name'];
	return NULL;
	}

	public function setClass($value){
		if(isset($value))
			$this->attributes['class'] = $value;
		else
			unset($this->attributes['class']);

	return $value;
	}

	public function getAttribute($name){
		$ret = NULL;
		if(isset($this->attributes[$name]))
			$ret = $this->attributes[$name];

	return $ret;
	}

	public function setAttribute($name, $value){
		if(is_object($value)){
			$this->attributes[$name] = unpack_object($value);
		}
		else if(isset($value))
			$this->attributes[$name] = $value;
		else
			unset($this->attributes[$name]);
	}

	public function removeAttribute($name){
		unset($this->attributes[$name]);
	}

	public function addAction($name, $value) {
		// strip new lines from scripts
		$value = str_replace(array("\n", "\r"), '', $value);

		$this->setAttribute($name, $value);
	}

	public function setHint($text, $width='', $class='', $byclick=true){
		if(empty($text)) return false;

		$text = unpack_object($text);

		$this->addAction('onmouseover',	"javascript: hintBox.showOver(event,this,".zbx_jsvalue($text).",'".$width."','".$class."');");
		$this->addAction('onmouseout',	"javascript: hintBox.hideOut(event,this);");
		if($byclick){
			$this->addAction('onclick',	"javascript: hintBox.onClick(event,this,".zbx_jsvalue($text).",'".$width."','".$class."');");
		}
	}

	public function onClick($handle_code){
		$this->addAction('onclick', $handle_code);
	}

	public function addStyle($value){
		if(!isset($this->attributes['style'])) $this->attributes['style'] = '';

		if(isset($value))
			$this->attributes['style'].= htmlspecialchars(strval($value));
		else
			unset($this->attributes['style']);
	}

	public function setEnabled($value='yes'){
		if((is_string($value) && ($value == 'yes' || $value == 'enabled' || $value=='on') || $value=='1') || (is_int($value) && $value<>0)){
			unset($this->attributes['disabled']);
		}
		else if((is_string($value) && ($value == 'no' || $value == 'disabled' || $value=='off') || $value=='0') || (is_int($value) && $value==0)){
			$this->attributes['disabled'] = 'disabled';
		}
	}

	public function error($value){
		error('class('.get_class($this).') - '.$value);
		return 1;
	}
}
?>
