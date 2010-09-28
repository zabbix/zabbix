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
	function BR(){
		return new CTag('br','no');
	}
	
	function	bold($str)
	{
		if(is_array($str)){
			foreach($str as $key => $val)
				if(is_string($val))
					 $str[$key] = "<b>$val</b>";
		} elseif(is_string($str)) {
			$str = "<b>$str</b>";
		}
		return $str;
	}

	function	bfirst($str) // mark first symbol of string as bold
	{
		$res = bold($str[0]);
		for($i=1,$max=strlen($str); $i<$max; $i++)	$res .= $str[$i];
		$str = $res;
		return $str;	
	}

	function	nbsp($str)
	{
		return str_replace(" ",SPACE,$str);;
	}

	function utf8_strlen($s)
	{
		return preg_match_all('/([\x01-\x7f]|([\xc0-\xff][\x80-\xbf]{1,5}))/', $s, $tmp);
	}

	function utf8_strtop($s, $len)
	{
		preg_match('/^([\x01-\x7f]|([\xc0-\xff][\x80-\xbf]{1,5})){0,'.$len.'}/', $s, $tmp);
		return (isset($tmp[0])) ? $tmp[0] : false;
	}

	function form_select($var, $value, $label)
	{
		global $_REQUEST;

		$selected = "";
		if(!is_null($var))
		{
			if(isset($_REQUEST[$var])&&$_REQUEST[$var]==$value)
				$selected = "selected";
		}
		return "<option value=\"$value\" $selected>$label";
	}

	function form_input($name, $value, $size)
	{
		return "<input class=\"biginput\" name=\"$name\" size=$size value=\"$value\">";
	}

	function form_textarea($name, $value, $cols, $rows)
	{
		return "<textarea name=\"$name\" cols=\"$cols\" ROWS=\"$rows\" wrap=\"soft\">$value</TEXTAREA>";
	}

	function url1_param($parameter)
	{
		global $_REQUEST;
	
		if(isset($_REQUEST[$parameter]))
		{
			return "$parameter=".$_REQUEST[$parameter];
		}
		else
		{
			return "";
		}
	}

	function	prepare_url(&$var, $varname)
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

	function url_param($parameter,$request=true,$name=null)
	{
		$result = "";

		
		if(!is_array($parameter))
		{
			if(!isset($name))
			{
				if(!$request)
					fatal_error('not request variable require url name [url_param]');

				$name = $parameter;
			}
		}
		
		if($request)
		{
			global $_REQUEST;
			
			$var =& $_REQUEST[$parameter];
		}
		else
		{
			$var =& $parameter;
		}
		
		if(isset($var))
		{
			$result = prepare_url($var,$name);
		}
		return $result;
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
		$icon->AddOption('id',$id.'_icon');
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
		$icon->AddOption('title',S_MAXIMIZE.'/'.S_MINIMIZE);
		$icon->AddAction('id','filter_icon');

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

	function table_begin($class="tableinfo")
	{
		echo "<table class=\"$class\" border=0 width=\"100%\" bgcolor='#AAAAAA' cellspacing=1 cellpadding=3>";
		echo "\n";
	}

	function table_header($elements)
	{
		echo "<tr bgcolor='#CCCCCC'>";
		while(list($num,$element)=each($elements))
		{
			echo "<td><b>".$element."</b></td>";
		}
		echo "</tr>";
		echo "\n";
	}

	function table_row($elements, $rownum)
	{
		if($rownum%2 == 1)	{ echo "<TR BGCOLOR=\"#DDDDDD\">"; }
		else			{ echo "<TR BGCOLOR=\"#EEEEEE\">"; }

		while(list($num,$element)=each($elements))
		{
			if(is_array($element)&&isset($element["hide"])&&($element["hide"]==1))	continue;
			if(is_array($element))
			{
				if(isset($element["class"]))
					echo "<td class=\"".$element["class"]."\">".$element["value"]."</td>";
				else
					echo "<td>".$element["value"]."</td>";
			}
			else
			{
				echo "<td>".$element."</td>";
			}
		}
		echo "</tr>";
		echo "\n";
	}


	function table_end()
	{
		echo "</table>";
		echo "\n";
	}

	function table_td($text,$attr)
	{
		echo "<td $attr>$text</td>";
	}

	function table_nodata($text="...")
	{
		echo "<TABLE BORDER=0 align=center WIDTH=\"100%\" BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
		echo "<TR BGCOLOR=\"#DDDDDD\">";
		echo "<TD ALIGN=CENTER>";
		echo $text;
		echo "</TD>";
		echo "</TR>";
		echo "</TABLE>";
	}
?>
