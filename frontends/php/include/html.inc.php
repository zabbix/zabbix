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
	function bold($str){
		if(is_array($str)){
			foreach($str as $key => $val)
				if(is_string($val)){
					$b = new CTag('strong','yes');
					$b->AddItem($val);
					$str[$key] = $b;
				}
		} 
		else if(is_string($str)) {
			$b = new CTag('strong','yes');
			$b->AddItem($str);
			$str = $b;
		}
	return $str;
	}

	function bfirst($str) // mark first symbol of string as bold
	{
		$res = bold($str[0]);
		for($i=1,$max=strlen($str); $i<$max; $i++)	$res .= $str[$i];
		$str = $res;
		return $str;	
	}

	function nbsp($str)
	{
		return str_replace(" ",SPACE,$str);
	}

	function url1_param($parameter)
	{
		if(isset($_REQUEST[$parameter]))
		{
			return "$parameter=".$_REQUEST[$parameter];
		}
		else
		{
			return "";
		}
	}

	function prepare_url(&$var, $varname=null)
	{
		$result = "";

		if(is_array($var))
		{
			foreach($var as $id => $par)
				$result .= prepare_url($par,
					isset($varname) ? $varname."[".$id."]": $id
					);
		}
		else
		{
			$result = "&".$varname."=".urlencode($var);
		}
		return $result;
	}

	function url_param($parameter,$request=true,$name=null){
		$result = '';
		if(!is_array($parameter)){
			if(!isset($name)){
				if(!$request)
					fatal_error('not request variable require url name [url_param]');
					
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
	
	function create_hat($caption,$items,$addicons=null,$id=null,$state=1){
	
		if(is_null($id)){
			list($usec, $sec) = explode(' ',microtime());	
			$id = 'hat_'.((int)($sec % 10)).((int)($usec * 1000));
		}

		$td_l = new CCol(SPACE);
		$td_l->AddOption('width','100%');

		$icons_row = array($td_l);
		if(!is_null($addicons)){
			if(!is_array($addicons)) $addicons = array($addicons);
			foreach($addicons as $value) $icons_row[] = $value;
		}

		$icon = new CDiv(SPACE,($state)?'arrowup':'arrowdown');
		$icon->AddAction('onclick',new CScript("javascript: change_hat_state(this,'".$id."');"));
		$icon->AddOption('title',S_SHOW.'/'.S_HIDE);

		$icons_row[] = $icon;

		$icon_tab = new CTable();
		$icon_tab->AddOption('width','100%');
		
		$icon_tab->AddRow($icons_row);
		
		$table = new CTable();
		$table->AddOption('width','100%');
		$table->SetCellPadding(0);
		$table->SetCellSpacing(0);
		$table->AddRow(get_table_header($caption,$icon_tab));

		$div = new CDiv($items);
		$div->AddOption('id',$id);
		if(!$state) $div->AddOption('style','display: none;');
		
		$table->AddRow($div);
	return $table;
	}
	
	function create_filter($col_l,$col_r,$items,$id='zbx_filter',$state=1){

		if(isset($_REQUEST['print'])) $state = 0;
		
		$table = new CTable();
		$table->AddOption('width','100%');
		$table->SetCellPadding(0);
		$table->SetCellSpacing(0);
		$table->AddOption('border',0);
		
		$icon = new CDiv(SPACE,($state)?'filteropened':'filterclosed');
		$icon->AddAction('onclick',new CScript("javascript: change_filter_state(this,'".$id."');"));
		$icon->AddOption('title',S_SHOW.'/'.S_HIDE.' '.S_FILTER);

		$td_icon = new CCol($icon);
		$td_icon->AddOption('valign','bottom');

		$icons_row = array($td_icon,SPACE);
		$icons_row[] = $col_l;

		$icon_tab = new CTable();
		$icon_tab->SetCellSpacing(0);
		$icon_tab->SetCellPadding(0);
		
		$icon_tab->AddRow($icons_row);
		
		$table->AddRow(get_thin_table_header($icon_tab,$col_r));

		$div = new CDiv($items);
		$div->AddOption('id',$id);
		if(!$state) $div->AddOption('style','display: none;');
		
		$tab = new CTable();
		$tab->AddRow($div);
		
//		$table->AddRow($tab);
		$table->AddRow($div);

	return $table;
	}
	
	function create_filter_hat($col_l,$col_r,$items,$id,$state=1){
		
		$table = new CTable(NULL,"filter");
		$table->SetCellSpacing(0);
		$table->SetCellPadding(1);



		$td_l = new CCol($icon_tab,"filter_l");
				
		$td_r = new CCol($col_r,"filter_r");
		$td_r->AddOption('align','right');
				
		$table->AddRow(array($td_l, $td_r));
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
			if(str_in_array(strtolower(get_class($obj)),array('cform','ccheckbox','cselect','cbutton','cbuttonqmessage','cbuttondelete','cbuttoncancel'))){
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
				if(strpos($obj,$item) !== FALSE) $obj = SPACE;
			}
		}
	}
	
	function get_thin_table_header($col1, $col2=SPACE){
		
		$table = new CTable(NULL,"filter");
//		$table->AddOption('border',1);
		$table->SetCellSpacing(0);
		$table->SetCellPadding(1);
		
		$td_r = new CCol($col2,"filter_r");
		$td_r->AddOption('align','right');
		
		$table->AddRow(array(new CCol($col1,"filter_l"), $td_r));
	return $table;
	}

	function	show_thin_table_header($col1, $col2=SPACE){
		$table = get_thin_table_header($col1, $col2);
		$table->Show();
	}

	function get_table_header($col1, $col2=SPACE){
		if(isset($_REQUEST['print'])){
			hide_form_items($col1);
			hide_form_items($col2);
		//if empty header than do not show it
			if(($col1 == SPACE) && ($col2 == SPACE)) return new CScript('');
		}
		
		$table = new CTable(NULL,"header");
//		$table->AddOption('border',1);
		$table->SetCellSpacing(0);
		$table->SetCellPadding(1);
		
		$td_r = new CCol($col2,"header_r");
		$td_r->AddOption('align','right');
		
		$table->AddRow(array(new CCol($col1,"header_l"), $td_r));
	return $table;
	}

	function	show_table_header($col1, $col2=SPACE)
	{
		$table = get_table_header($col1, $col2);
		$table->Show();
	}
?>