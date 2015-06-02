<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CColor extends CDiv {

	public function __construct($name, $value) {
		parent::__construct([
			(new CColorCell('lbl_'.$name, $value))
				->setTitle('#'.$value)
				->onClick('javascript: show_color_picker("'.$name.'")'),
			(new CTextBox($name, $value))
				->setAttribute('maxlength', 6)
				->onChange('set_color_by_name("'.$name.'", this.value)')
		]);

		$this->addClass(ZBX_STYLE_INPUT_COLOR_PICKER);

		insert_show_color_picker_javascript();
	}
}
