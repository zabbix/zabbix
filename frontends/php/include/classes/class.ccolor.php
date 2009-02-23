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
class CColor extends CObject{
	public function __construct($name,$value){
		parent::__construct();

		$lbl = new CColorCell('lbl_'.$name, $value, "show_color_picker('".$name."')");

		$txt = new CTextBox($name,$value,7);
		$txt->addOption('maxlength', 6);
		$txt->addOption('id', $name);
		$txt->addAction('onchange', "set_color_by_name('".$name."',this.value)");
		$txt->addOption('style', 'margin-top: 0px; margin-bottom: 0px');
		$this->addItem(array($txt, $lbl));
		
		insert_show_color_picker_javascript();
	}
}

function insert_show_color_picker_javascript(){
	global $SHOW_COLOR_PICKER_SCRIPT_ISERTTED;

	if($SHOW_COLOR_PICKER_SCRIPT_ISERTTED) return;
	$SHOW_COLOR_PICKER_SCRIPT_ISERTTED = true;

	$table = '';

	$table .= '<table cellspacing="0" cellpadding="1">';
	$table .= '<tr>';
	/* gray colors */
	foreach(array('0','1','2','3','4','5','6','7','8','9','A','b','C','D','E','F') as $c){
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
		array(0 => '0', 1 => '4'),
		array(0 => '0', 1 => '5'),
		array(0 => '0', 1 => '6'),
		array(0 => '0', 1 => '7'),
		array(0 => '0', 1 => '8'),
		array(0 => '0', 1 => '9'),
		array(0 => '0', 1 => 'A'),
		array(0 => '0', 1 => 'B'),
		array(0 => '0', 1 => 'C'),
		array(0 => '0', 1 => 'D'),
		array(0 => '0', 1 => 'E'),
		array(0 => '3', 1 => 'F'),
		array(0 => '6', 1 => 'F'),
		array(0 => '9', 1 => 'F'),
		array(0 => 'C', 1 => 'F')
		);

	foreach($colors as $c){
		$table .= '<tr>';
		foreach($brigs as $br){
			$r = $br[$c['r']];
			$g = $br[$c['g']];
			$b = $br[$c['b']];
			
			$color = $r.$r.$g.$g.$b.$b;

			$table .= '<td>'.unpack_object(new CColorCell(null, $color, 'set_color(\\\''.$color.'\\\')')).'</td>';
		}
		$table .= '</tr>';
	}
	$table .= '</table>';
	$cancel = '<span onclick="javascript:hide_color_picker();" class="link">'.S_CANCEL.'</span>';


	$script = 'var color_picker = null;
				var curr_lbl = null;
				var curr_txt = null;'."\n";
	
	$script.= 'var color_table = "'.$table.$cancel.'"'."\n";
	insert_js($script);
	print('<script type="text/javascript" src="js/color_picker.js"></script>');

	zbx_add_post_js('create_color_picker();');
}
?>