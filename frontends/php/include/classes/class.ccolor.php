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

		$lbl = new CColorCell('lbl_'.$name, $value, "javascript: show_color_picker('".$name."')");

		$txt = new CTextBox($name,$value,7);
		$txt->setAttribute('maxlength', 6);
		$txt->setAttribute('id', $name);

		$txt->addAction('onchange', "set_color_by_name('".$name."',this.value)");
		$txt->setAttribute('style', 'margin-top: 0px; margin-bottom: 0px');

		$this->addItem(array($txt, $lbl));

		insert_show_color_picker_javascript();
	}
}
?>
