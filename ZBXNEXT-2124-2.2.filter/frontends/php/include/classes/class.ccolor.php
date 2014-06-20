<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php

class CColor extends CObject {

	public function __construct($name, $value) {
		parent::__construct();

		$txt = new CTextBox($name, $value);
		$txt->addStyle('width: 6em;');
		$txt->attr('maxlength', 6);
		$txt->attr('id', zbx_formatDomId($name));
		$txt->addAction('onchange', 'set_color_by_name("'.$name.'", this.value)');
		$txt->addStyle('style', 'margin-top: 0px; margin-bottom: 0px;');

		$lbl = new CColorCell('lbl_'.$name, $value, 'javascript: show_color_picker("'.$name.'")');

		$this->addItem(array($txt, $lbl));

		insert_show_color_picker_javascript();
	}
}
?>
