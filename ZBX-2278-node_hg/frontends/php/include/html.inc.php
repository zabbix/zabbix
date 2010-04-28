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
	function italic($str){
		if(is_array($str)){
			foreach($str as $key => $val)
				if(is_string($val)){
					$em = new CTag('em','yes');
					$em->addItem($val);
					$str[$key] = $em;
				}
		}
		else if(is_string($str)) {
			$em = new CTag('em','yes','');
			$em->addItem($str);
			$str = $em;
		}
	return $str;
	}

	function bold($str){
		if(is_array($str)){
			foreach($str as $key => $val)
				if(is_string($val)){
					$b = new CTag('strong','yes');
					$b->addItem($val);
					$str[$key] = $b;
				}
		}
		else if(is_string($str)) {
			$b = new CTag('strong','yes','');
			$b->addItem($str);
			$str = $b;
		}
	return $str;
	}

	function make_decoration($haystack, $needle, $class=null){
		$result = $haystack;

		$pos = stripos($haystack,$needle);
		if($pos !== FALSE){
			$start = zbx_substring($haystack, 0, $pos);
//			$middle = substr($haystack, $pos, zbx_strlen($needle));
			$middle = $needle;
			$end = substr($haystack, $pos+zbx_strlen($needle));

			if(is_null($class)){
				$result = array($start, bold($middle), $end);
			}
			else{
				$result = array($start, new CSpan($middle, $class), $end);
			}
		}

	return $result;
	}

	function bfirst($str){
// mark first symbol of string as bold
		$res = bold($str[0]);
		for($i=1,$max=zbx_strlen($str); $i<$max; $i++)	$res .= $str[$i];
		$str = $res;
		return $str;
	}

	function nbsp($str){
		return str_replace(" ",SPACE,$str);
	}

	function url1_param($parameter){
		if(isset($_REQUEST[$parameter])){
			return "$parameter=".$_REQUEST[$parameter];
		}
		else{
			return "";
		}
	}

	function prepare_url(&$var, $varname=null){
		$result = '';

		if(is_array($var)){
			foreach($var as $id => $par)
				$result .= prepare_url($par,isset($varname) ? $varname."[".$id."]": $id);
		}
		else{
			$result = '&'.$varname.'='.urlencode($var);
		}
		return $result;
	}

	function url_param($parameter,$request=true,$name=null){
		$result = '';
		if(!is_array($parameter)){
			if(is_null($name)){
				if(!$request) fatal_error('not request variable require url name [url_param]');

				$name = $parameter;
			}
		}

		if($request){
			$var =& $_REQUEST[$parameter];
		}
		else{
			$var =& $parameter;
		}

		if(isset($var)){
			$result = prepare_url($var,$name);
		}

	return $result;
	}

	function BR(){
		return new CTag('br','no');
	}

	function create_hat($caption,$items,$addicons=null,$id=null,$state=null){
		if(is_null($id)){
			list($usec, $sec) = explode(' ',microtime());
			$id = 'hat_'.((int)($sec % 10)).((int)($usec * 1000));
		}

		$td_l = new CCol(SPACE);
		$td_l->setAttribute('width','100%');

		$icons_row = array($td_l);
		if(!is_null($addicons)){
			if(!is_array($addicons)) $addicons = array($addicons);
			foreach($addicons as $value) $icons_row[] = $value;
		}

		if(!is_null($state)){
			$icon = new CDiv(SPACE, $state?'arrowup':'arrowdown');
			$icon->setAttribute('id',$id.'_icon');
			$icon->addAction('onclick',new CJSscript("javascript: change_hat_state(this,'".$id."');"));
			$icon->setAttribute('title',S_SHOW.'/'.S_HIDE);
			$icons_row[] = $icon;
		}
		else{
			$state = true;
		}

		$icon_tab = new CTable();
		$icon_tab->setAttribute('width','100%');

		$icon_tab->addRow($icons_row);

		$table = new CTable();
		$table->setAttribute('width','100%');
		$table->setCellPadding(0);
		$table->setCellSpacing(0);
		$table->addRow(get_table_header($caption,$icon_tab));

		$div = new CDiv($items);
		$div->setAttribute('id',$id);
		if(!$state) $div->setAttribute('style','display: none;');

		$table->addRow($div);
	return $table;
	}


/* Function:
 *	hide_form_items()
 *
 * Desc:
 *	Searches items/objects for Form tags like "<input"/Form classes like CForm, and makes it empty
 *
 * Author:
 *	Aly
 */
	function hide_form_items(&$obj){
		if(is_array($obj)){
			foreach($obj as $id => $item){
				hide_form_items($obj[$id]);			// Attention recursion;
			}
		}
		else if(is_object($obj)){
			$formObjects = array('cform','ccheckbox','cselect','cbutton','cbuttonqmessage','cbuttondelete','cbuttoncancel');
			if(is_object($obj) && str_in_array(zbx_strtolower(get_class($obj)), $formObjects)){
				$obj=SPACE;
			}

			if(isset($obj->items) && !empty($obj->items)){
				foreach($obj->items as $id => $item){
					hide_form_items($obj->items[$id]); 		// Recursion
				}
			}
		}
		else{
			foreach(array('<form','<input','<select') as $item){
				if(zbx_strpos($obj,$item) !== FALSE) $obj = SPACE;
			}
		}
	}

	function get_thin_table_header($col1, $col2=NULL){

		$table = new CTable(NULL,'thin_header');
//		$table->setAttribute('border',1);
		$table->setCellSpacing(0);
		$table->setCellPadding(1);

		if(!is_null($col2)){
			$td_r = new CCol($col2,'thin_header_r');
			$td_r->setAttribute('align','right');
			$table->addRow(array(new CCol($col1,'thin_header_l'), $td_r));
		}
		else{
			$td_c = new CCol($col1,'thin_header_c');
			$td_c->setAttribute('align','center');

			$table->addRow($td_c);
		}

	return $table;
	}

	function show_thin_table_header($col1, $col2=NULL){
		$table = get_thin_table_header($col1, $col2);
		$table->Show();
	}

	function get_table_header($col1, $col2=SPACE){
		if(isset($_REQUEST['print'])){
			hide_form_items($col1);
			hide_form_items($col2);
		//if empty header than do not show it
			if(($col1 == SPACE) && ($col2 == SPACE)) return new CJSscript('');
		}

		$td_l = new CCol(SPACE,'header_r');
		$td_l->setAttribute('width','100%');

		$right_row = array($td_l);

		if(!is_null($col2)){
			if(!is_array($col2)) $col2 = array($col2);

			foreach($col2 as $num => $r_item)
				$right_row[] = new CCol($r_item,'header_r');
		}

		$right_tab = new CTable(null,'nowrap');
		$right_tab->setAttribute('width','100%');

		$right_tab->addRow($right_row);

		$table = new CTable(NULL,'header');
//		$table->setAttribute('border',0);
		$table->setCellSpacing(0);
		$table->setCellPadding(1);

		$td_r = new CCol($right_tab,'header_r');
		$td_r->setAttribute('align','right');

		$table->addRow(array(new CCol($col1,'header_l'), $td_r));
	return $table;
	}

	function show_table_header($col1, $col2=SPACE){
		$table = get_table_header($col1, $col2);
		$table->Show();
	}
?>
