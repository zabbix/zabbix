<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CTextArea extends CTag {

	/**
	 * The "&" symbol in the textarea should be encoded.
	 *
	 * @var int
	 */
	protected $encStrategy = self::ENC_ALL;

	public function __construct($name = 'textarea', $value = '', $rows = ZBX_TEXTAREA_STANDARD_ROWS, $width = ZBX_TEXTAREA_STANDARD_WIDTH, $readonly = false) {
		parent::__construct('textarea', 'yes');
		$this->attr('class', 'input');
		$this->attr('id', zbx_formatDomId($name));
		$this->attr('name', $name);
		$this->attr('rows', $rows);
		$this->setReadonly($readonly);
		$this->addItem($value);

		// set width
		if ($width == ZBX_TEXTAREA_STANDARD_WIDTH) {
			$this->addClass('textarea_standard');
		}
		elseif ($width == ZBX_TEXTAREA_BIG_WIDTH) {
			$this->addClass('textarea_big');
		}
		else {
			$this->attr('style', 'width: '.$width.'px;');
		}
	}

	public function setReadonly($value = true) {
		if ($value) {
			$this->attr('readonly', 'readonly');
		}
		else {
			$this->removeAttribute('readonly');
		}
	}

	public function setValue($value = '') {
		return $this->addItem($value);
	}

	public function setRows($value) {
		$this->attr('rows', $value);
	}

	public function setCols($value) {
		$this->attr('cols', $value);
	}
}
