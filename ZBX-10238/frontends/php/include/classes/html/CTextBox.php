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


class CTextBox extends CInput {

	private $caption;

	public function __construct($name = 'textbox', $value = '', $size = 20, $readonly = false, $maxlength = 255) {
		parent::__construct('text', $name, $value);
		$this->setReadonly($readonly);
		$this->caption = null;
		$this->tag_body_start = '';
		$this->setAttribute('size', $size);
		$this->setAttribute('maxlength', $maxlength);

		// require for align input field using css width
		if ($size == ZBX_TEXTBOX_STANDARD_SIZE) {
			$this->setAttribute('style', 'width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;');
		}
	}
}
