<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
function destroy_objects(){
	if(isset($GLOBALS)) foreach($GLOBALS as $name => $value){
		if(!is_object($GLOBALS[$name])) continue;
		unset($GLOBALS[$name]);
	}
}

function unpack_object(&$item){
	$res = '';

	if(is_object($item)){
		$res = $item->ToString(false);
	}
	else if(is_array($item)){
		foreach($item as $id => $dat)	
			$res .= unpack_object($item[$id]); // Attention, recursion !!!
	}
	else if(!is_null($item)){
		$res = strval($item);
		unset($item);
	}
return $res;
}

function implode_objects($glue, &$pieces){
	if( !is_array($pieces) )	return unpack_object($pieces);

	foreach($pieces as $id => $piece)
		$pieces[$id] = unpack_object($piece);

	return implode($glue, $pieces);
}

class CObject{
	function CObject($items=null){
		$this->items = array();
		if(isset($items)){
			$this->AddItem($items);
		}
	}
	
	function toString($destroy=true){
		$res = implode('',$this->items);
		if($destroy) $this->Destroy();
		return $res;
	}

	function show($destroy=true){
		echo $this->toString($destroy);			
	}

	function destroy(){
// TODO Problem under PHP 5.0  "Fatal error: Cannot re-assign $this in ..."
//			$this = null;
		$this->cleanItems();
	}

	function cleanItems(){	
		$this->items = array();	
	}
	
	function itemsCount(){	
		return count($this->items);	
	}
	
	function addItem($value){
		if(is_object($value)){
			array_push($this->items,unpack_object($value));
		}
		else if(is_string($value)){
			array_push($this->items,str_replace(array('<','>','"'),array('&lt;','&gt;','&quot;'),$value));
//				array_push($this->items,htmlspecialchars($value));
		}
		else if(is_array($value)){
			foreach($value as $item){
				$this->AddItem($item);			 // Attention, recursion !!!
			}
		}
		else if(!is_null($value)){
			array_push($this->items,unpack_object($value));
		}
	}
}

class CTag extends CObject{
/* private *//*
	var $tagname;
	var $options = array();
	var $paired;*/
/* protected *//*
	var $items = array();

	var $tag_body_start;
	var $tag_body_end;
	var $tag_start;
	var $tag_end;*/

/* public */
	function CTag($tagname=NULL, $paired='no', $body=NULL, $class=null){
		parent::CObject();

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
	
	function showStart()	{	echo $this->StartToString();	}
	function showBody()	{	echo $this->BodyToString();	}
	function showEnd()	{	echo $this->EndToString();	}

	function startToString(){
		$res = $this->tag_start.'<'.$this->tagname;
		foreach($this->options as $key => $value){
			$res .= ' '.$key.'="'.$value.'"';
		}
		$res .= ($this->paired=='yes')?'>':' />';
	return $res;
	}

	function BodyToString(){
		$res = $this->tag_body_start;
	return $res.parent::ToString(false);
		
		/*foreach($this->items as $item)
			$res .= $item;
		return $res;*/
	}
	
	function EndToString(){
		$res = ($this->paired=='yes') ? $this->tag_body_end.'</'.$this->tagname.'>' : '';
		$res .= $this->tag_end;
	return $res;
	}
	
	function toString($destroy=true){
		$res  = $this->StartToString();
		$res .= $this->BodyToString();
		$res .= $this->EndToString();

		if($destroy) $this->destroy();

	return $res;
	}
	
	function setName($value){
		if(is_null($value)) return $value;

		if(!is_string($value)){
			return $this->error("Incorrect value for SetName [$value]");
		}
	return $this->addOption("name",$value);
	}
	
	function getName(){
		if(isset($this->options['name']))
			return $this->options['name'];
	return NULL;
	}
	
	function setClass($value){
		if(isset($value))
			$this->options['class'] = $value;
		else
			unset($this->options['class']);

	return $value;
	}
	
	function delOption($name){
		unset($this->options[$name]);
	}
	
	function getOption($name){
		$ret = NULL;
		if(isset($this->options[$name]))
			$ret =& $this->options[$name];
	return $ret;
	}

	function setHint($text, $width='', $class=''){
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

	function onClick($handle_code){
		$this->addAction('onClick', $handle_code);
	}

	function addAction($name, $value){
		if(is_object($value)){
			$this->options[$name] = unpack_object($value);
		}
		else if(!empty($value)){
			$this->options[$name] = htmlentities(str_replace(array("\r", "\n"), '', strval($value)),ENT_COMPAT,S_HTML_CHARSET);
		}
	}

	function addOption($name, $value){
		if(is_object($value)){
			$this->options[$name] = unpack_object($value);
		}
		else if(isset($value))
			$this->options[$name] = htmlspecialchars(strval($value)); 
		else
			unset($this->options[$name]);
	}
	
	function addStyle($value){
		if(!isset($this->options['style'])) $this->options['style'] = '';
		
		if(isset($value))
			$this->options['style'].= htmlspecialchars(strval($value)); 
		else
			unset($this->options['style']);
	}

	function setEnabled($value='yes'){
		if((is_string($value) && ($value == 'yes' || $value == 'enabled' || $value=='on') || $value=='1') || (is_int($value) && $value<>0)){
			unset($this->options['disabled']);
		}
		else if((is_string($value) && ($value == 'no' || $value == 'disabled' || $value=='off') || $value=='0') || (is_int($value) && $value==0)){
			$this->options['disabled'] = 'disabled';
		}
	}
	
	function error($value){
		error('class('.get_class($this).') - '.$value);
		return 1;
	}
}
?>
