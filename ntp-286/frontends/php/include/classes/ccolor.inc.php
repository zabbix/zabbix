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
	require_once "include/classes/clink.inc.php";

?>
<?php

	function insert_show_color_picker_javascript()
	{
		global $SHOW_COLOR_PICKER_SCRIPT_ISERTTED;

		if($SHOW_COLOR_PICKER_SCRIPT_ISERTTED) return;
		$SHOW_COLOR_PICKER_SCRIPT_ISERTTED = true;
?>
<script language="JavaScript" type="text/javascript">
<!--

var color_table = <?php
	$table = '';

	$table .= '<table cellspacing="0" cellpadding="1">';
	$table .= '<tr>';
	/* gray colors */
	foreach(array('0','3','6','8','9','A','C','E','F') as $c)
	{
		$color = $c.$c.$c.$c.$c.$c;
		$table .= '<td>'.unpack_object(new CColorCell(null, $color, 'set_color(\\\''.$color.'\\\')')).'</td>';
	}
	$table .= '</tr>';
	
	/* other colors */
	$colors = array(
		array('r' => 0, 'g' => 0, 'b' => 1),
		array('r' => 0, 'g' => 1, 'b' => 0),
		array('r' => 1, 'g' => 0, 'b' => 0),
		array('r' => 0, 'g' => 1, 'b' => 1),
		array('r' => 1, 'g' => 0, 'b' => 1),
		array('r' => 1, 'g' => 1, 'b' => 0)
		);
	
	$brigs  = array(
		array(0 => '0', 1 => '3'),
		array(0 => '0', 1 => '6'),
		array(0 => '0', 1 => '9'),
		array(0 => '0', 1 => 'C'),
		array(0 => '0', 1 => 'F'),
		array(0 => '3', 1 => 'F'),
		array(0 => '6', 1 => 'F'),
		array(0 => '9', 1 => 'F'),
		array(0 => 'C', 1 => 'F')
		);

	foreach($colors as $c)
	{
		$table .= '<tr>';
		foreach($brigs as $br)
		{
			$r = $br[$c['r']];
			$g = $br[$c['g']];
			$b = $br[$c['b']];
			
			$color = $r.$r.$g.$g.$b.$b;

			$table .= '<td>'.unpack_object(new CColorCell(null, $color, 'set_color(\\\''.$color.'\\\')')).'</td>';
		}
		$table .= '</tr>';
	}
	$table .= '</table>';
	$cancel = '<a href="javascript:hide_color_picker()">'.S_CANCEL.'</a>';
	echo '\''.$table.$cancel.'\'';
	unset($table);
?>

function GetPos(obj)
{
	var left = obj.offsetLeft;
	var top  = obj.offsetTop;;
	while (obj = obj.offsetParent)
	{
		left	+= obj.offsetLeft
		top	+= obj.offsetTop
	}
	return [left,top];
}

var color_picker = null;
var curr_lbl = null;
var curr_txt = null;

function hide_color_picker()
{
	if(!color_picker) return;

	color_picker.style.visibility="hidden"
	color_picker.style.left	= "-" + ((color_picker.style.width) ? color_picker.style.width : 100) + "px";

	curr_lbl = null;
	curr_txt = null;
}

function show_color_picker(name)
{
	if(!color_picker) return;

	curr_lbl = document.getElementById('lbl_' + name);
	curr_txt = document.getElementById(name);
	
	var pos = GetPos(curr_lbl);

	color_picker.x	= pos[0];
	color_picker.y	= pos[1];

	color_picker.style.left	= color_picker.x + "px";
	color_picker.style.top	= color_picker.y + "px";

	color_picker.style.visibility = "visible";
}

function create_color_picker()
{
	if(color_picker) return;

	color_picker = document.createElement("div");
	color_picker.setAttribute("id", "color_picker");
	color_picker.innerHTML = color_table;
	document.body.appendChild(color_picker);

	hide_color_picker();
}

function set_color(color)
{
	if(curr_lbl)	curr_lbl.style.background = curr_lbl.style.color = '#' + color;
	if(curr_txt)	curr_txt.value = color;

	hide_color_picker();
}

function set_color_by_name(name, color)
{
	curr_lbl = document.getElementById('lbl_' + name);
	curr_txt = document.getElementById(name);
	
	set_color(color);
}

if (window.addEventListener)
{
	window.addEventListener("load", create_color_picker, false);
}
else if (window.attachEvent)
{
	window.attachEvent("onload", create_color_picker);
}
else if (document.getElementById)
{
	window.onload	= create_color_picker;
}

//-->
</script>
<?php
	}

	class CColorCell extends CLink
	{
		function	CColorCell($name, $value, $action=null)
		{
			parent::CLink(SPACE.SPACE.SPACE, null);
			$this->SetName($name);
			$this->AddOption('id', $name);
			$this->AddOption('title', '#'.$value);
			$this->AddOption('style', 'text-decoration: none; outline: 1px solid black; background-color: #'.$value);
			$this->SetAction($action);
		}
		function	SetAction($action=null)
		{
			if(!isset($action)) return false;
			
			return $this->SetUrl('javascript:'.$action);
		}
	}

	class CColor extends CObject
	{
/* public */
		function CColor($name,$value)
		{
			parent::CObject();

			$lbl = new CColorCell('lbl_'.$name, $value, 'show_color_picker(\''.$name.'\')');

			$txt = new CTextBox($name,$value,7);
			$txt->AddOption('id', $name);
			$txt->AddAction('onChange', 'set_color_by_name(\''.$name.'\',this.value)');
			$txt->AddOption('style', 'margin: 0px');
			$this->AddItem(array($txt, $lbl));
			
			insert_show_color_picker_javascript();
		}
	}
?>
